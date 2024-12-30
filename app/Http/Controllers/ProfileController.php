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
            'kyc_verification' => ['required', 'file', 'max:10000'],
        ])->setAttributeNames([
            'kyc_verification' => trans('public.kyc_verification')
        ]);
        $validator->validate();

        $user = $request->user();

        if ($request->hasFile('kyc_verification')) {
            $user->clearMediaCollection('kyc_verification');
            $user->addMedia($request->kyc_verification)->toMediaCollection('kyc_verification');

            $user->kyc_approved_at = now();
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
        $wallet_names = $request->wallet_name;
        $token_addresses = $request->token_address;

        $errors = [];

        // Validate wallets and addresses
        foreach ($wallet_names as $index => $wallet_name) {
            $token_address = $token_addresses[$index] ?? '';

            if (empty($wallet_name) && !empty($token_address)) {
                $errors["wallet_name.$index"] = trans('validation.required', ['attribute' => trans('public.wallet_name') . ' #' . ($index + 1)]);
            }

            if (!empty($wallet_name) && empty($token_address)) {
                $errors["token_address.$index"] = trans('validation.required', ['attribute' => trans('public.token_address') . ' #' . ($index + 1)]);
            }
        }

        foreach ($token_addresses as $index => $token_address) {
            $wallet_name = $wallet_names[$index] ?? '';

            if (empty($token_address) && !empty($wallet_name)) {
                $errors["token_address.$index"] = trans('validation.required', ['attribute' => trans('public.token_address') . ' #' . ($index + 1)]);
            }

            if (!empty($token_address) && empty($wallet_name)) {
                $errors["wallet_name.$index"] = trans('validation.required', ['attribute' => trans('public.wallet_name') . ' #' . ($index + 1)]);
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        if ($wallet_names && $token_addresses) {
            foreach ($wallet_names as $index => $wallet_name) {
                // Skip iteration if id or token_address is null
                if (is_null($token_addresses[$index])) {
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
                        'payment_platform_name' => 'USDT (TRC20)',
                        'account_no' => $token_addresses[$index],
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
        ]);
    }

    public function getKycVerification()
    {
        return response()->json([
            'kycVerification' => Auth::user()->getFirstMedia('kyc_verification'),
        ]);
    }
}
