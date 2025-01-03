<?php

namespace App\Http\Controllers\Auth;

use App\Models\Country;
use App\Models\User;
use App\Services\DropdownOptionService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;
use App\Services\CTraderService;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Auth\Events\Registered;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create($referral = null): Response
    {
        return Inertia::render('Auth/Register', [
            'referral_code' => $referral,
        ]);
    }

    public function validateStep(Request $request)
    {
        $rules = [
            'name' => ['required', 'regex:/^[a-zA-Z0-9\p{Han}. ]+$/u', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:' . User::class],
            'dial_code' => ['required'],
            'phone' => ['required', 'max:255', 'unique:' . User::class],
        ];

        $attributes = [
            'name'=> trans('public.full_name'),
            'email' => trans('public.email'),
            'phone' => trans('public.phone_number'),
        ];

        $validator = Validator::make($request->all(), $rules);
        $validator->setAttributeNames($attributes);

        if ($request->step == 1) {
            $validator->validate();
        } elseif ($request->step == 2) {
            $additionalRules = [
                'password' => ['required', 'confirmed', Password::min(8)->letters()->symbols()->numbers()->mixedCase()],
            ];
            $rules = array_merge($rules, $additionalRules);

            $additionalAttributes = [
                'password' => trans('public.password'),
            ];
            $attributes = array_merge($attributes, $additionalAttributes);

            $validator = Validator::make($request->all(), $rules);
            $validator->setAttributeNames($attributes);
            $validator->validate();
        }

        return back();
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // $validator = Validator::make($request->all(), [
        //     'kyc_verification' => ['required', 'file', 'max:10000'],
        // ])->setAttributeNames([
        //     'kyc_verification' => trans('public.kyc_verification')
        // ]);
        // $validator->validate();

        $dial_code = $request->dial_code;
        $country = Country::find($dial_code['id']);
        $default_agent_id = User::where('id_number', 'AID00000')->first()->id;

        $userData = [
            'name' => $request->name,
            'email' => $request->email,
            'dial_code' => $dial_code['phone_code'],
            'phone' => $request->phone,
            'phone_number' => $request->phone_number,
            'country_id' => $country->id,
            'nationality' => $country->nationality,
            'password' => Hash::make($request->password),
            'kyc_approval' => 'verified',
        ];

        $check_referral_code = null;
        if ($request->referral_code) {
            $referral_code = $request->input('referral_code');
            $check_referral_code = User::where('referral_code', $referral_code)->first();

            if ($check_referral_code) {
                $upline_id = $check_referral_code->id;
                $hierarchyList = empty($check_referral_code['hierarchyList']) ? "-" . $upline_id . "-" : $check_referral_code['hierarchyList'] . $upline_id . "-";

                $userData['upline_id'] = $upline_id;
                $userData['hierarchyList'] = $hierarchyList;
                $userData['role'] = $upline_id == $default_agent_id ? 'ib' : 'member';
            }
        } else {
            $default_upline = User::find(3);
            $default_upline_id = $default_upline->id;
            $newHierarchyList = $default_upline->hierarchyList . $default_upline_id . "-";

            $userData['upline_id'] = $default_upline_id;
            $userData['hierarchyList'] = $newHierarchyList;
            $userData['role'] = 'member';
        }

        $user = User::create($userData);

        $user->setReferralId();

        $id_no = ($user->role == 'ib' ? 'AID' : 'MID') . Str::padLeft($user->id - 2, 5, "0");
        $user->id_number = $id_no;
        $user->save();

        if ($check_referral_code && $check_referral_code->groupHasUser) {
            $user->assignedGroup($check_referral_code->groupHasUser->group_id);
        }

        // create ct id to link ctrader account
        if (App::environment('production') || App::environment('staging')) {
            $ctUser = (new CTraderService)->CreateCTID($user->email);
            $user->ct_user_id = $ctUser['userId'];
            $user->save();
        }

        // if ($request->hasFile('kyc_verification')) {
        //     $user->clearMediaCollection('kyc_verification');
        //     $user->addMedia($request->kyc_verification)->toMediaCollection('kyc_verification');
        // }

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }

    public function getFilterData()
    {
        return response()->json([
            'countries' => (new DropdownOptionService())->getCountries(),
        ]);
    }
}
