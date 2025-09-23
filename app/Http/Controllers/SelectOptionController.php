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
                'currency_rate' => "$method->currency_symbol$currency_rate",
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
            'conversionRate' => $gatewayMethodData[$targetMethod->payment_method_id]['currency_rate'],
        ]);
    }

    public function getPaymentGateways(Request $request)
    {
        $method = PaymentMethod::where('slug', $request->slug)->firstOrFail();

        // Find all methods of the same type (Bank + VietQR)
        $methodIds = PaymentMethod::where('type', $method->type)->pluck('id');

        if ($method->type == 'bank') {
            // Get all gateways that support any of those methods
            $payments = PaymentGateway::whereHas('methods', function ($q) use ($methodIds) {
                $q->whereIn('payment_method_id', $methodIds);
            })
                ->with(['methods' => function ($q) use ($methodIds) {
                    $q->whereIn('payment_methods.id', $methodIds);
                }])
                ->select([
                    'id',
                    'name',
                    'platform',
                    'environment',
                    'payment_url',
                    'can_deposit',
                    'can_withdraw',
                    'status',
                    'position'
                ])
                ->orderBy('position')
                ->get();
        } else {
            $payments = PaymentGateway::whereHas('methods', function ($q) use ($request) {
                $q->where('slug', $request->slug);
            })
                ->select([
                    'id',
                    'name',
                    'platform',
                    'environment',
                    'payment_url',
                    'can_withdraw',
                    'status',
                    'position'
                ])
                ->orderBy('position')
                ->get();
        }

        return response()->json([
            'payment_gateways' => $payments,
        ]);
    }

    public function getWithdrawalPaymentAccounts(Request $request)
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

    public function getWithdrawalCondition(Request $request)
    {
        if ($request->type == 'bank') {
            $typeMethodIds = PaymentMethod::where('type', $request->type)->pluck('id');
        } else {
            $typeMethodIds = PaymentMethod::where('slug', $request->type)->pluck('id');
        }

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
                )->withdrawal_rate;
            }

            $gatewayMethodData[$method->payment_method_id] = [
                'txn_charge' => $method->withdraw_fee,
                'min_amount' => round($method->min_withdraw_amount / $currency_rate, 2),
                'max_amount' => round($method->max_withdraw_amount / $currency_rate, 2),
                'currency_rate' => "$method->currency_symbol$currency_rate",
            ];
        }

        $targetMethod = $paymentGatewayMethods->firstWhere('payment_gateway_id', $request->payment_gateway_id);

        if (!$targetMethod) {
            return response()->json([
                'error' => 'Payment method not supported by this gateway',
            ], 422);
        }

        return response()->json([
            'fee'       => $gatewayMethodData[$targetMethod->payment_method_id]['txn_charge'],
            'minAmount' => $gatewayMethodData[$targetMethod->payment_method_id]['min_amount'],
            'maxAmount' => $gatewayMethodData[$targetMethod->payment_method_id]['max_amount'],
            'conversionRate' => $gatewayMethodData[$targetMethod->payment_method_id]['currency_rate'],
        ]);
    }
}
