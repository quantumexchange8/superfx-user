<?php

namespace App\Http\Controllers\Auth;

use App\Models\Country;
use App\Models\User;
use App\Models\Wallet;
use App\Models\RebateAllocation;
use App\Models\MarkupProfile;
use App\Models\MarkupProfileToAccountType;
use App\Models\UserToMarkupProfile;
use App\Services\DropdownOptionService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;
use App\Services\MetaFourService;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Auth\Events\Registered;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

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
            'country_id' => ['required'],
            'nationality' => ['required'],
        ];

        $attributes = [
            'name'=> trans('public.full_name'),
            'email' => trans('public.email'),
            'phone' => trans('public.phone_number'),
            'country_id' => trans('public.country'),
            'nationality' => trans('public.nationality'),
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
     * @throws FileDoesNotExist
     * @throws FileIsTooBig
     */
    public function store(Request $request): RedirectResponse
    {
        // $validator = Validator::make($request->all(), [
        //     'kyc_verification' => ['required', 'max:10000'],
        // ])->setAttributeNames([
        //     'kyc_verification' => trans('public.kyc_verification')
        // ]);
        // $validator->validate();

        $dial_code = $request->dial_code;
        $default_agent_id = User::where('id_number', 'IB00000')->first()->id;

        $userData = [
            'name' => $request->name,
            'email' => $request->email,
            'dial_code' => $dial_code['phone_code'],
            'phone' => $request->phone,
            'phone_number' => $request->phone_number,
            'country_id' => $request->country_id,
            'nationality' => $request->nationality,
            'password' => Hash::make($request->password),
            'kyc_status' => 'unverified',
        ];

        $check_referral_code = null;
        $default_markup_profile = MarkupProfile::where('slug', 'markup0')->first();

        $markup_profile_id = $default_markup_profile->id;
        if ($request->referral_code) {
            $referral_code = $request->input('referral_code');
            $check_referral_code = UserToMarkupProfile::where('referral_code', $referral_code)->first();

            if ($check_referral_code) {
                $upline_id = $check_referral_code->user_id;
                $markup_profile_id = $check_referral_code->markup_profile_id;
                $hierarchyList = empty($check_referral_code->user['hierarchyList']) ? "-" . $upline_id . "-" : $check_referral_code->user['hierarchyList'] . $upline_id . "-";

                $userData['upline_id'] = $upline_id;
                $userData['hierarchyList'] = $hierarchyList;
                $userData['role'] = $upline_id == $default_agent_id ? 'ib' : 'member';
            }
        } else {
            $default_upline = User::find(16);
            $default_upline_id = $default_upline->id;
            $newHierarchyList = empty($default_upline->hierarchyList) ? "-" . $default_upline_id . "-" : $default_upline->hierarchyList . $default_upline_id . "-";

            $userData['upline_id'] = $default_upline_id;
            $userData['hierarchyList'] = $newHierarchyList;
            $userData['role'] = 'member';
        }

        $user = User::create($userData);
        $user_markup_profile = UserToMarkupProfile::create([
            'user_id' => $user->id,
            'markup_profile_id' => $markup_profile_id,
        ]);

        // $user->setReferralId();

        $id_no = ($user->role == 'ib' ? 'IB' : 'MB') . Str::padLeft($user->id - 2, 5, "0");
        $user->id_number = $id_no;
        if ($request->kyc_verification) {
            foreach ($request->file('kyc_verification') as $image) {
                $user->addMedia($image)->toMediaCollection('kyc_verification');
            }
            $user->kyc_status = 'pending';
        }
        $user->save();

        if ($check_referral_code && $check_referral_code->user->groupHasUser) {
            $user->assignedGroup($check_referral_code->user->groupHasUser->group_id);
        }

        if ($user->role == 'ib') {
            do {
                $referralCode = Str::random(10);
            } while (UserToMarkupProfile::where('referral_code', $referralCode)->exists());

            $user_markup_profile->update(['referral_code' => $referralCode]);

            Wallet::create([
                'user_id' => $user->id,
                'type' => 'rebate_wallet',
                'address' => str_replace('IB', 'RB', $user->id_number),
                'balance' => 0
            ]);

            $uplineRebates = RebateAllocation::where('user_id', $user->upline_id)->get();

            foreach ($uplineRebates as $uplineRebate) {
                RebateAllocation::create([
                    'user_id' => $user->id,
                    'account_type_id' => $uplineRebate->account_type_id,
                    'symbol_group_id' => $uplineRebate->symbol_group_id,
                    'amount' => 0,
                ]);
            }
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
        $countries = (new DropdownOptionService())->getCountries();
        $nationalities = $countries->map(function ($country) {
            return [
                'id' => $country['id'],
                'nationality' => $country['nationality'],
            ];
        });

        return response()->json([
            'countries' => $countries,
            'nationalities' => $nationalities,
        ]);
    }
}
