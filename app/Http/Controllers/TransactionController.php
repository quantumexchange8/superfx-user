<?php

namespace App\Http\Controllers;

use App\Mail\WithdrawalRequestMail;
use App\Mail\WithdrawalRequestUsdtMail;
use Illuminate\Support\Facades\Mail;
use App\Models\AccountType;
use App\Models\PaymentAccount;
use App\Models\TradingAccount;
use App\Models\TradingUser;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\ChangeTraderBalanceType;
use App\Services\MetaFourService;
use App\Services\RunningNumberService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TransactionDetailExport;

class TransactionController extends Controller
{
    public function index()
    {
        return Inertia::render('Transaction/Transaction');
    }

    public function getTotal()
    {
        $total_deposit = Auth::user()->transactions->where('transaction_type', 'deposit')->where('status', 'successful')->sum('transaction_amount');
        $total_withdrawal = Auth::user()->transactions->where('transaction_type', 'withdrawal')->where('status', 'successful')->sum('transaction_amount');
        $max_account_amount = Auth::user()->transactions->where('category', '!=', 'rebate_wallet')->max('amount');
        $max_rebate_amount = Auth::user()->transactions->where('category', '=', 'rebate_wallet')->max('amount');

        return response()->json([
            'totalDeposit' => $total_deposit,
            'totalWithdrawal' => $total_withdrawal,
            'maxAccountAmount' => $max_account_amount,
            'maxRebateAmount' => $max_rebate_amount,
        ]);
    }

    public function getTransactions(Request $request)
    {
        if ($request->has('lazyEvent')) {
            $data = json_decode($request->only(['lazyEvent'])['lazyEvent'], true);
            $query = Transaction::where('user_id', Auth::id())
            ->where('category', '!=', 'rebate_wallet');

            if ($data['filters']['global']['value']) {
                $keyword = $data['filters']['global']['value'];

                $query->where(function ($q) use ($keyword) {
                    $q->where('transaction_number', 'like', '%' . $keyword . '%')
                    ->orWhere('from_meta_login', 'like', '%' . $keyword . '%')
                    ->orWhere('to_meta_login', 'like', '%' . $keyword . '%');
                });
            }

            if (!empty($data['filters']['start_date']['value']) && !empty($data['filters']['end_date']['value'])) {
                $start_date = Carbon::parse($data['filters']['start_date']['value'])->addDay()->startOfDay();
                $end_date = Carbon::parse($data['filters']['end_date']['value'])->addDay()->endOfDay();

                $query->whereBetween('created_at', [$start_date, $end_date]);
            }

            if (!empty($data['filters']['transaction_type']['value'])) {
                $query->where('transaction_type', $data['filters']['transaction_type']['value']);
            }
            else {
                $query->where(function ($q) {
                    $q->where('transaction_type', 'deposit')
                      ->orWhere('transaction_type', 'withdrawal');
                });
            }

            if (isset($data['filters']['amount']['value'][0], $data['filters']['amount']['value'][1])) {
                $minAmount = $data['filters']['amount']['value'][0];
                $maxAmount = $data['filters']['amount']['value'][1];

                $query->whereBetween('amount', [$minAmount, $maxAmount]);
            }

            if (!empty($data['filters']['status']['value'])) {
                $query->where('status', $data['filters']['status']['value']);
            }

            if ($data['sortField'] && $data['sortOrder']) {
                $order = $data['sortOrder'] == 1 ? 'asc' : 'desc';
                $query->orderBy($data['sortField'], $order);
            } else {
                $query->orderByDesc('id');
            }

            if ($request->has('exportStatus') && $request->exportStatus) {
                return Excel::download(new TransactionDetailExport($query), now() . '-account-transactions.xlsx');
            }

            $transactions = $query->paginate($data['rows']);

            $transactions->getCollection()->transform(function ($transaction) {
                return [
                    'category' => $transaction->category,
                    'transaction_type' => $transaction->transaction_type,
                    'from_meta_login' => $transaction->from_meta_login,
                    'to_meta_login' => $transaction->to_meta_login,
                    'transaction_number' => $transaction->transaction_number,
                    'payment_account_id' => $transaction->payment_account_id,
                    'payment_platform' => $transaction->payment_platform ?? $transaction->payment_account->platform ?? '-',
                    'payment_platform_name' => $transaction->payment_platform_name,
                    'payment_account_no' => $transaction->payment_account_no,
                    'payment_account_type' => $transaction->payment_account_type,
                    'bank_code' => $transaction->bank_code,
                    'from_wallet_address' => $transaction->from_wallet_address,
                    'to_wallet_address' => $transaction->to_wallet_address,
                    'txn_hash' => $transaction->txn_hash,
                    'amount' => $transaction->amount,
                    'transaction_charges' => $transaction->transaction_charges,
                    'transaction_amount' => $transaction->transaction_amount,
                    'status' => $transaction->status,
                    'comment' => $transaction->comment,
                    'remarks' => $transaction->remarks,
                    'created_at' => $transaction->created_at,
                    'wallet_name' => $transaction->payment_account_name ?? $transaction->payment_account->payment_account_name ?? '-',
                ];
            });

            return response()->json([
                'success' => true,
                'transactions' => $transactions,
            ]);
        }

        return response()->json(['success' => false, 'transactions' => []]);
    }

    public function walletTransfer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'wallet_id' => ['required', 'exists:wallets,id'],
            'amount' => ['required', 'numeric', 'gte:30'],
            'meta_login' => ['required']
        ])->setAttributeNames([
            'wallet_id' => trans('public.wallet'),
            'amount' => trans('public.amount'),
            'meta_login' => trans('public.transfer_to'),
        ]);
        $validator->validate();

        $amount = $request->amount;
        $wallet = Wallet::find($request->wallet_id);

        $tradingAccount = TradingAccount::with('account_type')
                ->where('meta_login', $request->meta_login)
                ->first();
        (new MetaFourService)->getUserInfo($tradingAccount->meta_login);

        if ($wallet->balance < $amount) {
            throw ValidationException::withMessages(['amount' => trans('public.insufficient_balance')]);
        }

        $multiplier = $tradingAccount->account_type->balance_multiplier;
        $adjusted_amount = $amount * $multiplier;
        try {
            $trade = (new MetaFourService)->createTrade($tradingAccount->meta_login, $adjusted_amount, "Rebate to account", 'balance', '');
        } catch (\Throwable $e) {
            if ($e->getMessage() == "Not found") {
                TradingUser::firstWhere('meta_login', $tradingAccount->meta_login)->update(['acc_status' => 'Inactive']);
            } else {
                Log::error($e->getMessage());
            }
            return back()
                ->with('toast', [
                    'title' => 'Trading account error',
                    'type' => 'error'
                ]);
        }

        Transaction::create([
            'user_id' => Auth::id(),
            'category' => 'rebate_wallet',
            'transaction_type' => 'transfer_to_account',
            'from_wallet_id' => $wallet->id,
            'to_meta_login' => $tradingAccount->meta_login,
            'transaction_number' => RunningNumberService::getID('transaction'),
            'ticket' => $trade['ticket'] ?? null,
            'from_currency' => 'USD',
            'to_currency' => 'USD',
            'amount' => $amount,
            'transaction_charges' => 0,
            'transaction_amount' => $amount,
            'status' => 'successful',
            'old_wallet_amount' => $wallet->balance,
            'new_wallet_amount' => $wallet->balance -= $amount,
        ]);

        $wallet->save();

        return back()->with('toast', [
            'title' => trans("public.toast_transfer_success"),
            'type' => 'success',
        ]);
    }

    public function walletWithdrawal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'wallet_id' => ['required', 'exists:wallets,id'],
            'amount' => ['required', 'numeric', 'gte:50'],
            'payment_account_id' => ['required']
        ])->setAttributeNames([
            'wallet_id' => trans('public.wallet'),
            'amount' => trans('public.amount'),
            'payment_account_id' => trans('public.receiving_wallet'),
        ]);
        $validator->validate();

        $user = Auth::user();
        $amount = $request->amount;
        $fee = $request->fee ?? 0;
        $transaction_amount = $amount - $fee;
        $wallet = Wallet::find($request->wallet_id);
        $paymentWallet = PaymentAccount::find($request->payment_account_id);

        if ($wallet->balance < $amount) {
            throw ValidationException::withMessages(['amount' => trans('public.insufficient_balance')]);
        }

        $transaction_number = RunningNumberService::getID('transaction');

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'category' => $wallet->type,
            'transaction_type' => 'withdrawal',
            'from_wallet_id' => $wallet->id,
            'transaction_number' => $transaction_number,
            'payment_account_id' => $paymentWallet->id,
            'payment_account_name' => $paymentWallet->payment_account_name,
            'payment_platform' => $paymentWallet->payment_platform,
            'payment_platform_name' => $paymentWallet->payment_platform_name,
            'payment_account_no' => $paymentWallet->account_no,
            'payment_account_type' => $paymentWallet->payment_account_type,
            'bank_code' => $paymentWallet->bank_code,
            'from_currency' => 'USD',
            'to_currency' => $paymentWallet->currency,
            'to_wallet_address' => $paymentWallet->account_no,
            'amount' => $amount,
            'transaction_charges' => $fee,
            'transaction_amount' => $transaction_amount,
            'old_wallet_amount' => $wallet->balance,
            'new_wallet_amount' => $wallet->balance -= $amount,
        ]);

        $wallet->save();

        if ($paymentWallet->payment_platform == 'crypto') {
            $transaction->update(['status' => 'required_confirmation']);
            Mail::to($user->email)->queue(new WithdrawalRequestUsdtMail($user, null, $amount, $transaction->created_at, $paymentWallet->account_no, $transaction_number, md5($user->email . $transaction_number . $paymentWallet->account_no)));
        } else {
            $transaction->update(['status' => 'processing']);
            Mail::to($user->email)->queue(new WithdrawalRequestMail($user, $transaction));
        }

        return redirect()->back()->with('notification', [
            'details' => $transaction,
            'type' => 'withdrawal',
            'withdrawal_type' => $transaction->category == 'rebate_wallet' ? 'rebate' : 'bonus'
        ]);
    }

    public function getRebateTransactions(Request $request)
    {
        if ($request->has('lazyEvent')) {
            $data = json_decode($request->only(['lazyEvent'])['lazyEvent'], true);
            $query = Transaction::where('user_id', Auth::id())
            ->where('category', '=', 'rebate_wallet');

            if ($data['filters']['global']['value']) {
                $keyword = $data['filters']['global']['value'];

                $query->where(function ($q) use ($keyword) {
                    $q->where('transaction_number', 'like', '%' . $keyword . '%')
                    ->orWhere('from_meta_login', 'like', '%' . $keyword . '%')
                    ->orWhere('to_meta_login', 'like', '%' . $keyword . '%');
                });
            }

            if (!empty($data['filters']['start_date']['value']) && !empty($data['filters']['end_date']['value'])) {
                $start_date = Carbon::parse($data['filters']['start_date']['value'])->addDay()->startOfDay();
                $end_date = Carbon::parse($data['filters']['end_date']['value'])->addDay()->endOfDay();

                $query->whereBetween('created_at', [$start_date, $end_date]);
            }

            if (!empty($data['filters']['transaction_type']['value'])) {
                $query->where('transaction_type', $data['filters']['transaction_type']['value']);
            }
            else {
                $query->where(function ($q) {
                    $q->where('transaction_type', 'deposit')
                      ->orWhere('transaction_type', 'withdrawal');
                });
            }

            if (isset($data['filters']['amount']['value'][0], $data['filters']['amount']['value'][1])) {
                $minAmount = $data['filters']['amount']['value'][0];
                $maxAmount = $data['filters']['amount']['value'][1];

                $query->whereBetween('amount', [$minAmount, $maxAmount]);
            }

            if (!empty($data['filters']['status']['value'])) {
                $query->where('status', $data['filters']['status']['value']);
            }

            if ($data['sortField'] && $data['sortOrder']) {
                $order = $data['sortOrder'] == 1 ? 'asc' : 'desc';
                $query->orderBy($data['sortField'], $order);
            } else {
                $query->orderByDesc('id');
            }

            if ($request->has('exportStatus') && $request->exportStatus) {
                return Excel::download(new TransactionDetailExport($query), now() . '-rebate-transactions.xlsx');
            }

            $transactions = $query->paginate($data['rows']);

            $transactions->getCollection()->transform(function ($transaction) {
                return [
                    'category' => $transaction->category,
                    'transaction_type' => $transaction->transaction_type,
                    'from_meta_login' => $transaction->from_meta_login,
                    'to_meta_login' => $transaction->to_meta_login,
                    'transaction_number' => $transaction->transaction_number,
                    'payment_account_id' => $transaction->payment_account_id,
                    'payment_platform' => $transaction->payment_platform ?? $transaction->payment_account->platform ?? '-',
                    'payment_platform_name' => $transaction->payment_platform_name,
                    'payment_account_no' => $transaction->payment_account_no,
                    'payment_account_type' => $transaction->payment_account_type,
                    'bank_code' => $transaction->bank_code,
                    'from_wallet_address' => $transaction->from_wallet_address,
                    'to_wallet_address' => $transaction->to_wallet_address,
                    'txn_hash' => $transaction->txn_hash,
                    'amount' => $transaction->amount,
                    'transaction_charges' => $transaction->transaction_charges,
                    'transaction_amount' => $transaction->transaction_amount,
                    'status' => $transaction->status,
                    'comment' => $transaction->comment,
                    'remarks' => $transaction->remarks,
                    'created_at' => $transaction->created_at,
                    'wallet_name' => $transaction->payment_account_name ?? $transaction->payment_account->payment_account_name ?? '-',
                ];
            });

            return response()->json([
                'success' => true,
                'transactions' => $transactions,
            ]);
        }

        return response()->json(['success' => false, 'transactions' => []]);
    }

    public function applyRebate()
    {
        $user = Auth::user();

        if ($user->rebate_amount > 0) {
            if ($user->role == 'ib' && !$user->rebate_wallet()->exists()) {
                Log::debug("Rebate Wallet did not exist for User ID : " . $user->id);
                Wallet::create([
                    'user_id' => $user->id,
                    'type' => 'rebate_wallet',
                    'address' => str_replace('IB', 'RB', $user->id_number),
                    'balance' => 0
                ]);
            }
            $rebate_wallet = $user->rebate_wallet;

            Transaction::create([
                'user_id' => $user->id,
                'category' => 'rebate_wallet',
                'transaction_type' => 'apply_rebate',
                'to_wallet_id' => $rebate_wallet->id,
                'transaction_number' => RunningNumberService::getID('transaction'),
                'amount' => $user->rebate_amount,
                'transaction_charges' => 0,
                'transaction_amount' => $user->rebate_amount,
                'old_wallet_amount' => $rebate_wallet->balance,
                'new_wallet_amount' => $rebate_wallet->balance += $user->rebate_amount,
                'status' => 'successful',
                'approved_at' => now(),
            ]);

            $rebate_wallet->save();

            $user->rebate_amount = 0;
            $user->save();

            return back()->with('toast', [
                'title' => trans("public.toast_apply_rebate_success"),
                'type' => 'success',
            ]);
        } else {
            return back()->with('toast', [
                'title' => trans("public.unable_to_apply_rebate"),
                'message' => trans("public.toast_apply_rebate_error"),
                'type' => 'error',
            ]);
        }
    }

    public function cancelWithdrawal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'remarks' => ['required']
        ])->setAttributeNames([
            'remarks' => trans('public.remarks'),
        ]);
        $validator->validate();

        $user = Auth::user();

        $transaction = Transaction::where('transaction_number', $request->transaction_number)->first();

        if ($transaction->status == 'processing' || $transaction->status == 'required_confirmation') {
            $transaction->update([
                'remarks' => $request->remarks,
                'status' => 'cancelled',
            ]);

            if ($transaction->category == 'rebate_wallet') {
                $rebate_wallet = Wallet::where('user_id', $transaction->user_id)
                    ->where('type', 'rebate_wallet')
                    ->first();

                $transaction->update([
                    'old_wallet_amount' => $rebate_wallet->balance,
                    'new_wallet_amount' => $rebate_wallet->balance += $transaction->amount,
                ]);

                $rebate_wallet->save();
            }

            if ($transaction->category == 'bonus_wallet') {
                $bonus_wallet = Wallet::where('user_id', $transaction->user_id)
                    ->where('type', 'bonus_wallet')
                    ->first();

                $transaction->update([
                    'old_wallet_amount' => $bonus_wallet->balance,
                    'new_wallet_amount' => $bonus_wallet->balance += $transaction->amount,
                ]);

                $bonus_wallet->save();
            }

            if ($transaction->category == 'trading_account') {
                $tradingAccount = TradingAccount::with('account_type') 
                    ->where('meta_login', $transaction->from_meta_login)
                    ->first();
                $multiplier = $tradingAccount->account_type->balance_multiplier;
                $adjusted_amount = $transaction->amount * $multiplier;

                try {
                    $trade = (new MetaFourService)->createTrade($transaction->from_meta_login, $adjusted_amount, 'Cancelled from ' . $transaction->transaction_number, 'balance', '');

                    $transaction->update([
                        'ticket' => $trade['ticket'] ?? null,
                    ]);
                } catch (\Throwable $e) {
                    // Log the main error
                    Log::error('Error creating trade: ' . $e->getMessage());

                    // Attempt to get the account and mark account as inactive if not found
                    $account = (new MetaFourService())->getUser($transaction->meta_login);
                    if (!$account) {
                        TradingUser::where('meta_login', $transaction->meta_login)
                            ->update(['acc_status' => 'inactive']);
                    }

                    return back()
                        ->with('toast', [
                            'title' => 'Trading account error',
                            'type' => 'error'
                        ]);
                }
            }

            return back()->with('toast', [
                'title' => trans("public.toast_cancel_withdrawal_success"),
                'type' => 'success',
            ]);
        }
        else {
            return back()->with('toast', [
                'title' => trans("public.unable_to_cancel_withdrawal"),
                'message' => trans("public.toast_cancel_withdrawal_error"),
                'type' => 'error',
            ]);
        }
    }

    public function confirmWithdrawal($transaction_number, $token)
    {
        $transaction = Transaction::with('user:id,email')
                        ->where('transaction_number', $transaction_number)
                        ->first();

        if ($transaction && $transaction->status == 'required_confirmation') {
            $dataToHash = md5($transaction->user->email . $transaction_number . $transaction->payment_account_no);

            if ($dataToHash === $token) {
                $transaction->status = 'processing';
                $transaction->save();

                return redirect()->route('transaction')->with('toast', [
                    'title' => trans('public.withdrawal_confirmed'),
                    'type' => 'success'
                ]);
            }
        }

        return redirect()->route('transaction')->with('toast', [
            'title' => trans('public.invalid_token'),
            'type' => 'error'
        ]);
    }
}
