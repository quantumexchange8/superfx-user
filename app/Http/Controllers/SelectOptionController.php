<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\PaymentAccount;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayHasBank;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SelectOptionController extends Controller
{
    public function getPaymentAccounts(Request $request)
    {
        $paymentAccounts = PaymentAccount::where('user_id', Auth::id())
            ->latest()
            ->get();

        if ($request->type) {
            $cryptoOptions = Setting::where('slug', 'LIKE', "%$request->type%")
                    ->get()
                    ->map(function ($setting) {
                        $type = explode('_', $setting->slug)[0];
                        return [
                            'type' => strtoupper($type),
                            'fee' => floatval($setting->value),
                        ];
                    });

            return response()->json([
                'payment_accounts' => $paymentAccounts,
                'crypto_options' => $cryptoOptions,
            ]);
        }
        else{
            return response()->json([
                'payment_accounts' => $paymentAccounts,
            ]);
        }
    }

    public function getPaymentGateways(Request $request)
    {
        $environment = in_array(app()->environment(), ['local', 'staging']) ? 'staging' : 'production';

        $payments = PaymentGateway::where([
            'environment' => $environment,
            'platform' => $request->platform
        ])
            ->get();

        return response()->json([
            'payment_gateways' => $payments,
        ]);
    }

    public function getWithdrawalPaymentAccounts(Request $request)
    {
        $query = PaymentAccount::where('user_id', Auth::id());

        if ($request->payment_platform == 'crypto') {
            $paymentAccounts = $query->where('payment_account_type', $request->payment_account_type)
                ->latest()
                ->get();
        } elseif ($request->payment_platform == 'bank') {
            $paymentAccounts = $query->where('payment_platform', 'bank')
                ->latest()
                ->get();

            // Filter payment accounts that have a PaymentGatewayHasBank match
            $paymentAccounts = $paymentAccounts->filter(function ($account) use ($request) {
                $bank = Bank::firstWhere('bank_code', $account->bank_code);

                if (!$bank) {
                    return false;
                }

                return PaymentGatewayHasBank::where('payment_gateway_id', $request->payment_account_type)
                    ->where('bank_id', $bank->id)
                    ->exists();
            })->values();
        } else {
            // If neither crypto nor bank — return empty collection
            $paymentAccounts = collect();
        }

        $cryptoOptions = Setting::where('slug', $request->payment_account_type . "_withdrawal_charge")
            ->get()
            ->map(function ($setting) {
                $type = explode('_', $setting->slug)[0];
                return [
                    'type' => strtoupper($type),
                    'fee' => floatval($setting->value),
                ];
            });

        return response()->json([
            'payment_accounts' => $paymentAccounts,
            'crypto_options' => $cryptoOptions,
        ]);
    }
}
