<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\PaymentAccount;
use App\Services\DropdownOptionService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;

            return redirect()->back()->with('toast', [
                'title' => 'Invalid Action',
                'type' => 'warning'
            ]);
        }

        $dial_code = $request->dial_code;

        $user->update([
            'dial_code' => $dial_code['phone_code'],
            'phone' => $request->phone,
            'phone_number' => $request->phone_number,
        ]);

        return redirect()->back()->with('toast', [
            'title' => trans('public.toast_update_profile_success'),
            'type' => 'success'
        ]);
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    public function updateKyc(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kyc_verification' => ['required', 'max:10000'],
        ])->setAttributeNames([
            'kyc_verification' => trans('public.kyc_verification')
        ]);
        $validator->validate();

        $user = $request->user();

        if ($request->hasFile('kyc_verification')) {
            $user->clearMediaCollection('kyc_verification');
            foreach ($request->file('kyc_verification') as $image) {
                $user->addMedia($image)->toMediaCollection('kyc_verification');
            }

            $user->kyc_status = 'pending';
            $user->save();
        }

        return redirect()->back()->with('toast', [
            'title' => trans("public.toast_update_kyc_success"),
            'type' => 'success',
        ]);
    }

    public function updateProfilePhoto(Request $request)
    {
        $user = $request->user();

        if ($request->action == 'upload' && $request->hasFile('profile_photo')) {
            $user->clearMediaCollection('profile_photo');
            $user->addMedia($request->profile_photo)->toMediaCollection('profile_photo');
        }

        if ($request->action == 'remove') {
            $user->clearMediaCollection('profile_photo');
        }

        return redirect()->back()->with('toast', [
            'title' => trans('public.toast_update_profile_photo_success'),
            'type' => 'success'
        ]);
    }

    public function updateCryptoWalletInfo(Request $request)
    {
        $account_types = $request->account_type;
        $wallet_names = $request->wallet_name;
        $token_addresses = $request->token_address;

        $errors = [];
        foreach ($wallet_names as $index => $wallet_name) {
            $token_address = $token_addresses[$index] ?? '';
            $account_type = $account_types[$index] ?? '';
        
            if (empty($wallet_name) && (!empty($token_address) || !empty($account_type))) {
                $errors["wallet_name.$index"] = trans('validation.required', ['attribute' => trans('public.wallet_name') . ' #' . ($index + 1)]);
            }
        
            if (empty($token_address) && (!empty($wallet_name) || !empty($account_type))) {
                $errors["token_address.$index"] = trans('validation.required', ['attribute' => trans('public.token_address') . ' #' . ($index + 1)]);
            }
        
            if (empty($account_type) && (!empty($wallet_name) || !empty($token_address))) {
                $errors["account_type.$index"] = trans('validation.required', ['attribute' => trans('public.account_type') . ' #' . ($index + 1)]);
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        if ($wallet_names && $token_addresses && $account_types) {
            foreach ($wallet_names as $index => $wallet_name) {
                $token_address = $token_addresses[$index] ?? null;
                $account_type = $account_types[$index] ?? null;

                // Skip iteration if mandatory fields are null
                if (is_null($wallet_name) || is_null($token_address) || is_null($account_type)) {
                    continue;
                }

                $conditions = [
                    'user_id' => $request->user_id,
                ];

                // Check if 'id' is set and valid
                if (!empty($request->id[$index])) {
                    $conditions['id'] = $request->id[$index];
                } else {
                    $conditions['id'] = 0;
                }

                PaymentAccount::updateOrCreate(
                    $conditions,
                    [
                        'user_id' => $request->user_id,
                        'status' => 'active',
                        'payment_account_name' => $wallet_name,
                        'payment_platform' => 'crypto',
                        'payment_platform_name' => 'USDT (' . strtoupper($account_type) . ')',
                        'account_no' => $token_addresses[$index],
                        'payment_account_type' => $account_type,
                        'currency' => 'USDT'
                    ]
                );
            }
        }

        return redirect()->back()->with('toast', [
            'title' => trans('public.toast_update_crypto_wallet_success'),
            'type' => 'success'
        ]);
    }

    public function getFilterData()
    {
        return response()->json([
            'countries' => (new DropdownOptionService())->getCountries(),
            'banks' => (new DropdownOptionService())->getBanks(),
            'bankOptions' => (new DropdownOptionService())->getBankOptions(),
        ]);
    }

    public function getKycVerification()
    {
        return response()->json([
            'kyc_status' => Auth::user()->kyc_status,
            'kycVerification' => Auth::user()->getMedia('kyc_verification'),
        ]);
    }

    public function addPaymentAccount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_account_name' => ['required'],
            'payment_account_type' => ['required'],
            'payment_platform_name' => ['required'],
            'account_no' => ['required_if:payment_account_type,account'],
            'card_no' => ['required_if:payment_account_type,card'],
        ])->setAttributeNames([
            'payment_account_name' => trans('public.account_name'),
            'payment_account_type' => trans('public.account_type'),
            'payment_platform_name' => trans('public.bank'),
            'account_no' => trans('public.account_no'),
            'card_no' => trans('public.card_no'),
        ]);
        $validator->validate();
        $user = Auth::user();

        $data = [
            'user_id' => $user->id,
            'payment_account_name' => $request->payment_account_name,
            'payment_platform' => 'bank',
            'payment_platform_name' => $request->payment_platform_name,
            'account_no' => $request->payment_account_type == 'account' ? $request->account_no : $request->card_no,
            'payment_account_type' => $request->payment_account_type,
            'bank_code' => $request->bank_code,
            'country_id' => 240,
            'currency' => 'VND',
            'status' => 'active',
        ];
        Log::debug($data);
        PaymentAccount::create($data);

        return redirect()->back()->with('toast', [
            'title' => trans('public.toast_payment_account_success'),
            'type' => 'success'
        ]);
    }

    public function updateBankInfo(Request $request)
    {
        

        // return redirect()->back()->with('toast', [
        //     'title' => trans('public.toast_update_bank_info_success'),
        //     'type' => 'success'
        // ]);
    }
}
