<?php

namespace App\Http\Controllers;

use App\Models\PaymentAccount;
use Illuminate\Support\Facades\Auth;

class SelectOptionController extends Controller
{
    public function getPaymentAccounts()
    {
        $paymentAccounts = PaymentAccount::where('user_id', Auth::id())
            ->latest()
            ->get();

        return response()->json([
            'payment_accounts' => $paymentAccounts,
        ]);
    }
}
