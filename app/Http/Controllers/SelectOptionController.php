<?php

namespace App\Http\Controllers;

use App\Models\PaymentAccount;
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
}
