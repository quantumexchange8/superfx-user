<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Bank;
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
        $paymentAccountsCount = PaymentAccount::where('user_id', Auth::id())
            ->count();

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'paymentAccountsCount' => $paymentAccountsCount,
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
            'country_id' => $request->country_id,
            'nationality' => $request->nationality,
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
            'banks' => (new DropdownOptionService())->getBanks(),
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
        $attributeNames = [
            'payment_platform' => trans('public.payment_account_type'),
            'payment_account_name' => $request->payment_platform == 'crypto'
                ? trans('public.wallet_name')
                : ($request->payment_account_type == 'account' ? trans('public.account_name') : trans('public.card_name')),
            'payment_account_type' => trans('public.account_type'),
            'payment_platform_name' => trans('public.bank'),
            'account_no' => $request->payment_platform == 'crypto'
                ? trans('public.token_address')
                : ($request->payment_account_type == 'account' ? trans('public.account_no') : trans('public.card_no')),
            'bank_code' => trans('public.bank_code'),
            'bank_bin_code' => trans('public.bank_bin'),
        ];

        Validator::make($request->all(), [
            'payment_platform' => ['required'],
            'payment_account_name' => ['required'],
            'payment_account_type' => ['required'],
            'payment_platform_name' => ['required'],
            'account_no' => ['required'],
            'bank_code' => ['required_if:payment_platform,bank'],
            'bank_bin_code' => ['required_if:payment_platform,bank'],
        ])->setAttributeNames($attributeNames)
            ->validate();

        $payment_account = PaymentAccount::create([
            'user_id' => Auth::id(),
            'payment_account_name' => $request->payment_account_name,
            'payment_platform' => $request->payment_platform,
            'payment_platform_name' => $request->payment_platform_name,
            'account_no' => $request->account_no,
            'payment_account_type' => $request->payment_account_type,
            'status' => 'active',
        ]);

        if ($payment_account->payment_platform == 'bank') {
            $bank = Bank::firstWhere('bank_code', $request->bank_code);

            $payment_account->update([
                'bank_code' => $bank->bank_code,
                'bank_bin_code' => $request->bank_bin_code,
                'country_id' => $bank->country_id,
                'currency' => $bank->currency,
            ]);
        } else {
            $payment_account->update([
                'payment_platform_name' => 'USDT (' . strtoupper($payment_account->payment_account_type) . ')',
                'currency' => 'USDT',
            ]);
        }

        return redirect()->back()->with('toast', [
            'title' => trans('public.toast_payment_account_success'),
            'type' => 'success'
        ]);
    }

    public function getPaymentAccounts(Request $request)
    {
        $paymentAccounts = PaymentAccount::where([
            'user_id' => Auth::id(),
        ])
            ->latest()
            ->get();

        return response()->json([
            'paymentAccounts' => $paymentAccounts,
        ]);
    }

    public function updatePaymentAccount(Request $request)
    {
        $attributeNames = [
            'payment_platform' => trans('public.payment_account_type'),
            'payment_account_name' => $request->payment_platform == 'crypto'
                ? trans('public.wallet_name')
                : ($request->payment_account_type == 'account' ? trans('public.account_name') : trans('public.card_name')),
            'payment_account_type' => trans('public.account_type'),
            'payment_platform_name' => trans('public.bank'),
            'account_no' => $request->payment_platform == 'crypto'
                ? trans('public.token_address')
                : ($request->payment_account_type == 'account' ? trans('public.account_no') : trans('public.card_no')),
            'bank_code' => trans('public.bank_code'),
            'bank_bin_code' => trans('public.bank_bin'),
        ];

        Validator::make($request->all(), [
            'payment_platform' => ['required'],
            'payment_account_name' => ['required'],
            'payment_account_type' => ['required'],
            'payment_platform_name' => ['required'],
            'account_no' => ['required'],
            'bank_code' => ['required_if:payment_platform,bank'],
            'bank_bin_code' => ['required_if:payment_platform,bank'],
        ])->setAttributeNames($attributeNames)
            ->validate();

        $payment_account = PaymentAccount::find($request->payment_account_id);

        $payment_account->update([
            'payment_account_name' => $request->payment_account_name,
            'payment_platform' => $request->payment_platform,
            'payment_platform_name' => $request->payment_platform_name,
            'account_no' => $request->account_no,
            'payment_account_type' => $request->payment_account_type,
            'status' => 'active',
        ]);

        if ($payment_account->payment_platform == 'bank') {
            $bank = Bank::firstWhere('bank_code', $request->bank_code);

            $payment_account->update([
                'bank_code' => $bank->bank_code,
                'bank_bin_code' => $request->bank_bin_code,
                'country_id' => $bank->country_id,
                'currency' => $bank->currency,
            ]);
        } else {
            $payment_account->update([
                'bank_code' => null,
                'country_id' => null,
                'payment_platform_name' => 'USDT (' . strtoupper($payment_account->payment_account_type) . ')',
                'currency' => 'USDT',
            ]);
        }

        return redirect()->back()->with('toast', [
            'title' => trans('public.toast_update_payment_account_success'),
            'type' => 'success'
        ]);
    }
}
