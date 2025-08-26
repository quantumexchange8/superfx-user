<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\CurrencyConversionRate;
use App\Models\PaymentAccount;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayHasBank;
use App\Models\PaymentGatewayMethod;
use App\Models\PaymentMethod;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SelectOptionController extends Controller
{
    public function getPaymentMethodRule(Request $request)
    {
        $baseMethod = PaymentMethod::select(['id', 'type'])->findOrFail($request->payment_method_id);

        $typeMethodIds = PaymentMethod::where('type', $baseMethod->type)->pluck('id');

        $paymentGatewayMethods = PaymentGatewayMethod::with([
            'payment_gateway:id,name',
            'payment_method:id,name,slug,type',
        ])
            ->where('payment_gateway_id', $request->payment_gateway_id)
            ->whereIn('payment_method_id', $typeMethodIds)
            ->get();

        $gatewayMethodData = [];

        foreach ($paymentGatewayMethods as $method) {
            $currency_rate = 1;
            if ($method->currency !== 'USD') {
                $currency_rate = CurrencyConversionRate::firstWhere(
                    'base_currency',
                    $method->currency
                )->{$request->type . '_rate'};
            }

            $gatewayMethodData[$method->payment_method_id] = [
                'txn_charge' => $method->{$request->type . '_fee'},
                'min_amount' => round($method->min_amount / $currency_rate, 2),
                'max_amount' => round($method->max_amount / $currency_rate, 2),
            ];
        }

        if ($baseMethod->type == 'bank') {
            $targetMethod = $paymentGatewayMethods->firstWhere('payment_gateway_id', $request->payment_gateway_id);
        } else {
            $targetMethod = $paymentGatewayMethods->firstWhere('payment_method_id', $request->payment_method_id);
        }

        if (!$targetMethod) {
            return response()->json([
                'error' => 'Payment method not supported by this gateway',
            ], 422);
        }

        return response()->json([
            'fee'       => $gatewayMethodData[$targetMethod->payment_method_id]['txn_charge'],
            'minAmount' => $gatewayMethodData[$targetMethod->payment_method_id]['min_amount'],
            'maxAmount' => $gatewayMethodData[$targetMethod->payment_method_id]['max_amount'],
        ]);
    }

    public function getPaymentGateways(Request $request)
    {
        $method = PaymentMethod::where('slug', $request->slug)->firstOrFail();

        // Find all methods of the same type (Bank + VietQR)
        $methodIds = PaymentMethod::where('type', $method->type)->pluck('id');

        // Get all gateways that support any of those methods
        $payments = PaymentGateway::whereHas('methods', function ($q) use ($methodIds) {
            $q->whereIn('payment_method_id', $methodIds);
        })
            ->with(['methods' => function ($q) use ($methodIds) {
                $q->whereIn('payment_methods.id', $methodIds);
            }])
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
