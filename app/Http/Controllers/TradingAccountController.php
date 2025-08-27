<?php

namespace App\Http\Controllers;

use App\Mail\CreateAccountMail;
use App\Mail\DepositSuccessMail;
use App\Mail\TransferMoneySuccessMail;
use App\Mail\WithdrawalRequestMail;
use App\Mail\WithdrawalRequestUsdtMail;
use App\Mail\ChangePasswordMail;
use App\Models\OpenTrade;
use App\Models\PaymentGatewayMethod;
use App\Models\PaymentMethod;
use App\Models\Term;
use App\Models\User;
use App\Services\PaymentService;
use DB;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use App\Models\AccountType;
use App\Models\AssetRevoke;
use App\Models\TradingUser;
use App\Models\Transaction;
use App\Models\Bank;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\PaymentAccount;
use App\Models\TradingAccount;
use Illuminate\Support\Carbon;
use App\Services\MetaFourService;
use App\Models\AssetSubscription;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use App\Services\RunningNumberService;
use App\Services\DropdownOptionService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\PaymentGateway;
use App\Models\CurrencyConversionRate;
use Storage;
use Throwable;

class TradingAccountController extends Controller
{
    public function index()
    {
        $terms = Term::where('slug', 'trading-account-agreement')->get();

        $structuredTerms = [];

        foreach ($terms as $term) {
            $locale = $term->locale;
            $structuredTerms[$locale] = [
                'title' => $term->title,
                'contents' => $term->contents,
            ];
        }

        $methods = PaymentMethod::query()
            ->where('type', '!=', 'bank')
            ->union(
                PaymentMethod::query()
                    ->where('type', 'bank')
                    ->orderBy('id')
                    ->limit(1)
            )
            ->orderBy('id')
            ->get();

        return Inertia::render('TradingAccount/Account', [
            'terms' => $structuredTerms,
            'methods' => $methods,
        ]);
    }

    public function getOptions()
    {
        $accountOptions = AccountType::query()
            ->whereNot('account_group', 'Demo Account')
            ->where('status', 'active')
            ->whereHas('markupProfileToAccountTypes.markupProfile.userToMarkupProfiles', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->select([
                'id',
                'name',
                'slug',
                DB::raw('member_display_name as member_display_name'),
                'account_group',
                'leverage'
            ])
            ->get()
            ->toArray();

        $conversionRate = CurrencyConversionRate::firstWhere('base_currency', 'VND')->deposit_rate;

        return response()->json([
            'leverages' => (new DropdownOptionService())->getLeveragesOptions(),
            'transferOptions' => (new DropdownOptionService())->getInternalTransferOptions(),
            'walletOptions' => (new DropdownOptionService())->getWalletOptions(),
            'downlineOptions' => (new DropdownOptionService())->getDownlines(),
            'accountOptions' => $accountOptions,
            'conversionRate' => $conversionRate,
        ]);
    }

    public function create_live_account(Request $request)
    {
        // Validate the request data
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'accountType' => 'required|exists:account_types,account_group',
            'leverage' => 'required|integer|min:1',
        ]);

        $user = User::with('country')->find($request->user_id);

        // Retrieve the account type by account_group
        $accountType = AccountType::where('account_group', $request->accountType)->first();

        // Check the number of existing trading accounts for this user and account type
        $existingAccountsCount = TradingAccount::where('user_id', $user->id)
            ->where('account_type_id', $accountType->id)
            ->count();

        // Check if the user has reached the maximum number of accounts
        if ($existingAccountsCount >= $accountType->maximum_account_number) {
            return back()->with('toast', [
                'title' => trans("public.account_limit_reach"),
                'message' => trans("public.account_limit_reach_message"),
                'type' => 'warning',
            ]);
        }

        $mainPassword = Str::random(8);
        $investorPassword = Str::random(8);
        $data = (new MetaFourService)->createUser($user, $accountType->account_group, $request->leverage, $mainPassword, $investorPassword);

        Mail::to($user->email)->send(new CreateAccountMail($user, $mainPassword, $investorPassword, $data['meta_login'], 'SuperFin-Live'));

        return back()->with('toast', [
            'title' => trans("public.toast_open_live_account_success"),
            'type' => 'success',
        ]);
    }

    public function create_demo_account(Request $request)
    {
        Validator::make($request->all(), [
            'amount' => ['required'],
            'leverage' => ['required'],
        ])->setAttributeNames([
            'amount' => trans('public.amount'),
            'leverage' => trans('public.leverage'),
        ])->validate();

        $user = $request->user();

        $mainPassword = Str::random(8);
        $investorPassword = Str::random(8);
        $data = (new MetaFourService)->createDemoUser($user, 'Standard', $request->leverage, $mainPassword, $investorPassword);

        (new MetaFourService)->createDemoTrade($data['meta_login'], $request->amount, "Demo Deposit #" . $data['meta_login'], 'balance', '');

        Mail::to($user->email)->send(new CreateAccountMail($user, $mainPassword, $investorPassword, $data['meta_login'], 'SuperFin-Demo'));

        return back()->with('toast', [
            'title' => trans("public.toast_open_demo_account_success"),
            'type' => 'success',
        ]);
    }

    public function getLiveAccount(Request $request)
    {
        $user = Auth::user();
        $accountType = $request->input('accountType');

        $trading_accounts = $user->tradingAccounts()
            ->whereHas('account_type', function($q) use ($accountType) {
                $q->where('category', $accountType);
            })
            ->latest()
            ->get();

        try {
            foreach ($trading_accounts as $trading_account) {
                (new MetaFourService)->getUserInfo($trading_account->meta_login);
            }
        } catch (Throwable $e) {
            Log::error($e->getMessage());
        }

        $liveAccounts = TradingAccount::with('account_type')
            ->where('user_id', $user->id)
            ->when($accountType, function ($query) use ($accountType) {
                return $query->whereHas('account_type', function ($query) use ($accountType) {
                    $query->where('category', $accountType);
                });
            })
            ->latest()
            ->get()
            ->map(function ($account) {

                $following_master = AssetSubscription::with('asset_master:id,asset_name')
                    ->where('meta_login', $account->meta_login)
                    ->whereIn('status', ['ongoing', 'pending'])
                    ->first();

                $remaining_days = null;

                if ($following_master && $following_master->matured_at) {
                    $matured_at = Carbon::parse($following_master->matured_at);
                    $remaining_days = Carbon::now()->diffInDays($matured_at);
                }

                return [
                    'id' => $account->id,
                    'user_id' => $account->user_id,
                    'meta_login' => $account->meta_login,
                    'balance' => $account->balance,
                    'credit' => $account->credit,
                    'leverage' => $account->margin_leverage,
                    'equity' => $account->equity,
                    'account_type' => $account->account_type->slug,
                    'account_type_name' => $account->account_type->name,
                    'member_display_name' => $account->account_type->member_display_name ?? null,
                    'account_type_leverage' => $account->account_type->leverage,
                    'account_type_color' => $account->account_type->color,
                    'group' => $account->account_type->account_group,
                    'category' => $account->account_type->category,
                    'minimum_deposit' => $account->account_type->minimum_deposit,
                    'balance_multiplier' => $account->account_type->balance_multiplier,
                    'asset_master_id' => $following_master->asset_master->id ?? null,
                    'asset_master_name' => $following_master->asset_master->asset_name ?? null,
                    'remaining_days' => intval($remaining_days),
                    'status' => $following_master->status ?? null,
                ];
            });

        return response()->json($liveAccounts);
    }

    public function getAccountReport(Request $request)
    {
        $meta_login = $request->query('meta_login');
        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');
        $type = $request->query('type');

        $query = Transaction::query()
            ->where('status', 'successful');

        if ($meta_login) {
            $query->where(function($subQuery) use ($meta_login) {
                $subQuery->where('from_meta_login', $meta_login)
                    ->orWhere('to_meta_login', $meta_login);
            });
        }

        if ($startDate && $endDate) {
            $start_date = Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
            $end_date = Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay();

            $query->whereBetween('created_at', [$start_date, $end_date]);
        }

        // Apply type filter
        if ($type && $type !== 'all') {
            // Filter based on specific transaction types directly
            if ($type === 'deposit') {
                $query->where('transaction_type', 'deposit');
            } elseif ($type === 'withdrawal') {
                $query->where('transaction_type', 'withdrawal');
            } elseif ($type === 'transfer') {
                $query->whereIn('transaction_type', ['transfer_to_account', 'account_to_account']);
            }
        }

        $transactions = $query
            ->latest()
            ->get()
            ->map(function ($transaction) {
                return [
                    'category' => $transaction->category,
                    'transaction_type' => $transaction->transaction_type,
                    'from_meta_login' => $transaction->from_meta_login,
                    'to_meta_login' => $transaction->to_meta_login,
                    'transaction_number' => $transaction->transaction_number,
                    'payment_account_id' => $transaction->payment_account_id,
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
                    'wallet_name' => $transaction->payment_account->payment_account_name ?? '-',
                    'wallet_type' => $transaction->from_wallet->type ?? '-',
                ];
            });

        return response()->json($transactions);
    }

    public function withdrawal_from_account(Request $request)
    {
        $tradingAccount = TradingAccount::find($request->account_id)->load('account_type');

        $minAmount = $tradingAccount->account_type->category === 'cent' ? 50 * $tradingAccount->account_type->balance_multiplier : 50;

        $validator = Validator::make($request->all(), [
            'account_id' => ['required', 'exists:trading_accounts,id'],
            'amount' => ['required', 'numeric', "gte:$minAmount"],
            'payment_account_id' => ['required']
        ])->setAttributeNames([
            'account_id' => trans('public.account'),
            'amount' => trans('public.amount'),
            'payment_account_id' => trans('public.receiving_wallet'),
        ]);
        $validator->validate();

        $amount = $request->amount;
        $fee = $request->fee ?? 0;

        (new MetaFourService)->getUserInfo($tradingAccount->meta_login);

        $floating = OpenTrade::where('meta_login', $tradingAccount->meta_login)
            ->sum('trade_profit_usd');

        $equity = $tradingAccount->balance + $floating;

        if ($equity < $amount) {
            throw ValidationException::withMessages([
                'amount' => trans('public.insufficient_balance'),
            ]);
        }

        $transaction_number = RunningNumberService::getID('transaction');

        $multiplier = $tradingAccount->account_type->balance_multiplier;
        $adjusted_amount = $amount / $multiplier;
        $transaction_amount = $adjusted_amount - $fee;
        try {
            $trade = (new MetaFourService)->createTrade($tradingAccount->meta_login, -$amount, "Withdraw From Account: " . $transaction_number, 'balance', '');
        } catch (Throwable $e) {
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

        $paymentWallet = PaymentAccount::find($request->payment_account_id);

        $user = Auth::user();

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'category' => 'trading_account',
            'transaction_type' => 'withdrawal',
            'from_meta_login' => $tradingAccount->meta_login,
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
            'ticket' => $trade['ticket'] ?? null,
            'amount' => $adjusted_amount,
            'transaction_charges' => $fee,
            'transaction_amount' => $transaction_amount,
        ]);

        if ($paymentWallet->payment_platform == 'crypto') {
            $transaction->update(['status' => 'required_confirmation']);
            Mail::to($user->email)->queue(new WithdrawalRequestUsdtMail($user, $tradingAccount->meta_login, $amount, $transaction->created_at, $paymentWallet->account_no, $transaction_number, md5($user->email . $transaction_number . $paymentWallet->account_no)));
        } else {
            $transaction->update(['status' => 'processing']);
            Mail::to($user->email)->queue(new WithdrawalRequestMail($user, $transaction));
        }

        // Set notification data in the session
        return redirect()->back()->with('notification', [
            'details' => $transaction,
            'type' => 'withdrawal',
            // 'withdrawal_type' => 'rebate' this not put show meta_login put rebate show Rebate put bonus show Bonus
        ]);
    }

    public function internal_transfer(Request $request)
    {
        $tradingAccount = TradingAccount::find($request->account_id)->load('account_type');

        $validator = Validator::make($request->all(), [
            'account_id' => ['required', 'exists:trading_accounts,id'],
            'to_meta_login' => ['required', 'exists:trading_accounts,meta_login'], // Ensure `to_meta_login` exists
        ])->setAttributeNames([
            'account_id' => trans('public.account'),
            'to_meta_login' => trans('public.transfer_to'),
        ]);
        $validator->validate();

        $to_meta_login = $request->input('to_meta_login');
        $to_tradingAccount = TradingAccount::with('account_type')
            ->where('meta_login', $to_meta_login)
            ->first();

        $minAmount = $to_tradingAccount->account_type->account_group === 'PRIME' ? $to_tradingAccount->account_type->minimum_deposit : 0;

        $minAmount = $tradingAccount->account_type->category === 'cent' ? $minAmount * $tradingAccount->account_type->balance_multiplier : $minAmount;

        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', "gte:$minAmount"],
        ])->setAttributeNames([
            'amount' => trans('public.amount'),
        ]);
        $validator->validate();

         (new MetaFourService)->getUserInfo($tradingAccount->meta_login);

         $amount = $request->input('amount');

        $floating = OpenTrade::where('meta_login', $tradingAccount->meta_login)
            ->sum('trade_profit_usd');

        $equity = $tradingAccount->balance + $floating;

        if ($equity < $amount) {
            throw ValidationException::withMessages([
                'amount' => trans('public.insufficient_balance'),
            ]);
        }

        $multiplier = $tradingAccount->account_type->balance_multiplier;
        $adjusted_amount = $amount / $multiplier;

        $to_multiplier = $to_tradingAccount->account_type->balance_multiplier;
        $to_adjusted_amount = $adjusted_amount * $to_multiplier;

        try {
            $tradeFrom = (new MetaFourService)->createTrade($tradingAccount->meta_login, -$amount, "Transfer to #$to_meta_login", 'balance', '');

            $tradeTo = (new MetaFourService)->createTrade($to_meta_login, $to_adjusted_amount, "Transfer from #$tradingAccount->meta_login", 'balance', '');
        } catch (Throwable $e) {
             if ($e->getMessage() == "Not found") {
                 TradingUser::firstWhere('meta_login', $tradingAccount->meta_login)->update(['acc_status' => 'Inactive']);
             } else {
                 Log::error($e->getMessage());
             }
             return response()->json(['success' => false, 'message' => $e->getMessage()]);
         }

         $ticketFrom = $tradeFrom['ticket'] ?? null;
         $ticketTo = $tradeTo['ticket'] ?? null;
         Transaction::create([
            'user_id' => Auth::id(),
            'category' => 'trading_account',
            'transaction_type' => 'account_to_account',
            'from_meta_login' => $tradingAccount->meta_login,
            'to_meta_login' => $to_meta_login,
            'ticket' => $ticketFrom . ','. $ticketTo,
            'transaction_number' => RunningNumberService::getID('transaction'),
            'from_currency' => 'USD',
            'to_currency' => 'USD',
            'amount' => $adjusted_amount,
            'transaction_charges' => 0,
            'transaction_amount' => $adjusted_amount,
            'status' => 'successful',
            'comment' => 'Transfer from ' . $tradingAccount->meta_login . ' to ' . $to_meta_login
         ]);

         $user = Auth::user();

         Mail::to($user->email)->send(new TransferMoneySuccessMail($user, $tradingAccount->meta_login, $to_meta_login, $adjusted_amount));

        return back()->with('toast', [
            'title' => trans('public.toast_internal_transfer_success'),
            'type' => 'success',
        ]);
    }

    public function change_leverage(Request $request)
    {
        $request->validate([
            'account_id' => 'required',
        ]);

        $account = TradingAccount::find($request->account_id);

        try {
            (new MetaFourService)->updateLeverage($account->meta_login, $request->leverage);
        } catch (Throwable $e) {
            Log::error($e->getMessage());
            return back()
                ->with('toast', [
                    'title' => 'Update leverage error',
                    'type' => 'error'
                ]);
        }

        // Check if the account exists
        if ($account) {
            // Redirect back with success message
            return back()->with('toast', [
                'title' => trans('public.toast_change_leverage_success'),
                'type' => 'success',
            ]);
        }
    }

    public function change_password(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password_type' => ['required'],
            'password' => ['required', 'confirmed'],
            'password_confirmation' => ['required', 'same:password'],
        ])->setAttributeNames([
            'password_type' => trans('public.password_type'),
            'password' => trans('public.password'),
        ]);
        $validator->validate();

        $account = TradingAccount::find($request->account_id);
        $user = Auth::user();

        try {
            if ($account) {
                if ($request->password_type == 'master') {
                    (new MetaFourService)->changeMasterPassword($account->meta_login, $request->password);
                    Mail::to($user->email)->send(new ChangePasswordMail($user, $request->password_type, $request->password, $account->meta_login));

                    return back()->with('toast', [
                        'title' => trans('public.toast_change_master_password_success'),
                        'type' => 'success',
                    ]);

                }
                else {
                    (new MetaFourService)->changeInvestorPassword($account->meta_login, $request->password);
                    Mail::to($user->email)->send(new ChangePasswordMail($user, $request->password_type, $request->password, $account->meta_login));

                    return back()->with('toast', [
                        'title' => trans('public.toast_change_investor_password_success'),
                        'type' => 'success',
                    ]);
                }
            }
        } catch (Throwable $e) {
            Log::error($e->getMessage());
            return back()
                ->with('toast', [
                    'title' => 'Change Password error',
                    'type' => 'error'
                ]);
        }

    }

    public function revoke_account(Request $request)
    {
        // Validate the request
        $request->validate([
            'account_id' => 'required|exists:trading_accounts,id',
        ]);


        // Retrieve the TradingAccount by its ID
        $tradingAccount = TradingAccount::findOrFail($request->account_id);

        // Find the AssetSubscription record
        $assetSubscription = AssetSubscription::with('asset_master')
            ->where('user_id', $tradingAccount->user_id)
            ->where('meta_login', $tradingAccount->meta_login)
            ->where('status', 'ongoing')
            ->first();

        // Check if the AssetSubscription is null
        if ($assetSubscription === null) {
            return back()->with('toast', [
                'title' => trans('public.toast_revoke_account_error_title'),
                'message' => trans('public.toast_revoke_account_error_message'),
                'type' => 'warning',
            ]);
        }

        try {
            // Get latest user info from MetaFourService and update the TradingAccount
            (new MetaFourService)->getUserInfo($tradingAccount);

            // Retrieve the updated TradingAccount to get the latest balance
            $tradingAccount = TradingAccount::findOrFail($request->account_id);

            // Calculate the penalty fee
            $penaltyPercentage = $assetSubscription->asset_master->penalty_fee;
            $penaltyFee = ($penaltyPercentage / 100) * $tradingAccount->balance;

            // Create a new AssetRevoke record
            AssetRevoke::create([
                'user_id' => $tradingAccount->user_id,
                'asset_subscription_id' => $assetSubscription->id,
                'asset_master_id' => $assetSubscription->asset_master->id,
                'meta_login' => $tradingAccount->meta_login,
                'balance_on_revoke' => $tradingAccount->balance,
                'penalty_percentage' => $penaltyPercentage,
                'penalty_fee' => $penaltyFee,
                'status' => 'pending',
            ]);

            // Update the status of the AssetSubscription to 'pending'
            $assetSubscription->update(['status' => 'pending']);

            // Redirect back with success message
            return back()->with('toast', [
                'title' => trans('public.toast_revoke_account_success'),
                'type' => 'success',
            ]);
        } catch (Exception $e) {
            Log::error('Revoke Account Error: ' . $e->getMessage());
        }
    }

    public function delete_account(Request $request)
    {
        $request->validate([
            'account_id' => ['required', 'exists:trading_accounts,id'],
            'type' => ['nullable', 'string']
        ]);

        $account = TradingAccount::find($request->account_id);
        $trading_user = TradingUser::where('meta_login', $account->meta_login)
            ->first();

        try {
            (new MetaFourService)->deleteTrader($account->meta_login);

            $account->delete();
            $trading_user->delete();
        } catch (Throwable $e) {
            Log::error($e->getMessage());

            return back()->with('toast', [
                'title' => 'CTrader connection error',
                'type' => 'error',
            ]);
        }

        $successTitle = trans('public.toast_delete_account_success');
        if ($request->type === 'demo') {
            $successTitle = trans('public.toast_delete_demo_account_success');
        }

        return back()->with('toast', [
            'title' => $successTitle,
            'type' => 'success',
        ]);
    }

    public function deposit_to_account(Request $request)
    {
        $tradingAccount = TradingAccount::with('account_type')
                ->where('meta_login', $request->meta_login)
                ->first();

        $minAmount = $tradingAccount->account_type->minimum_deposit > 0 ? $tradingAccount->account_type->minimum_deposit : ($request->min_amount < 50 ? 50 : $request->min_amount);

        //change cryptoType validation as bank won't work
        Validator::make($request->all(), [
            'meta_login' => ['required', 'exists:trading_accounts,meta_login'],
            'payment_method' => ['required'],
            'payment_gateway' => ['required'],
            'amount' => ['required', 'numeric', "gte:" . round($minAmount)],
        ])->setAttributeNames([
            'meta_login' => trans('public.account'),
            'payment_method' => trans('public.select_payment'),
            'payment_gateway' => trans('public.payment_gateway'),
            'amount' => trans('public.amount'),
        ])->validate();

        $user = Auth::user();

        $payment_method = $request->payment_method;
        $payment_gateway = PaymentGateway::find($request->payment_gateway);

        $latest_transaction = Transaction::where('user_id', $user->id)
            ->where('category', 'trading_account')
            ->where('transaction_type', 'deposit')
            ->where('status', 'processing')
            ->latest()
            ->first();

        $amount = number_format(floatval($request->amount), 2, '.', '');

        if ($request->amount > $request->max_amount) {
            throw ValidationException::withMessages([
                'amount' => trans('public.amount_cannot_larger_than_max_amount', [
                    'max_amount' => floor($request->max_amount),
                ]),
            ]);
        }

        // Check if the latest transaction exists and its created_at time is within the last 30 seconds
        if ($latest_transaction && Carbon::parse($latest_transaction->created_at)->diffInSeconds(Carbon::now()) < 30) {

            $remainingSeconds = 30 - Carbon::parse($latest_transaction->created_at)->diffInSeconds(Carbon::now());

            return redirect()->back()
                ->with('title', trans('public.invalid_action'))
                ->with('warning', trans('public.please_wait_for_seconds', ['seconds' => $remainingSeconds]));
        }

        $conversion_rate = null;
        $conversion_amount = null;
        $fee = $request->txn_fee ?? 0;

        $baseMethod = PaymentMethod::select(['id', 'type'])->findOrFail($payment_method['id']);

        $typeMethodIds = PaymentMethod::where('type', $baseMethod->type)->pluck('id');

        $paymentGatewayMethod = PaymentGatewayMethod::with([
            'payment_gateway:id,name',
            'payment_method:id,name,slug,type',
        ])
            ->where('payment_gateway_id', $payment_gateway->id)
            ->whereIn('payment_method_id', $typeMethodIds)
            ->firstOrFail();

        if ($payment_gateway->platform == 'bank') {
            $conversion_rate = CurrencyConversionRate::firstWhere('base_currency', $paymentGatewayMethod->currency)->deposit_rate;
            $conversion_amount = round($amount * $conversion_rate, 2);
        }

        $transaction = Transaction::create([
            'category' => 'trading_account',
            'user_id' => $user->id,
            'to_meta_login' => $request->meta_login,
            'transaction_number' => RunningNumberService::getID('transaction'),
            'payment_platform' => $request->payment_platform,
            'transaction_type' => 'deposit',
            'amount' => $amount,
            'from_currency' => $conversion_rate ? $paymentGatewayMethod->currency : 'USD',
            'to_currency' => 'USD',
            'conversion_rate' => $conversion_rate ?? null,
            'conversion_amount' => $conversion_amount ?? null,
            'transaction_amount' => $amount - $fee,
            'transaction_charges' => $fee,
            'status' => 'processing',
            'payment_gateway_id' => $payment_gateway->id,
            'payment_account_type' => strtolower($request->cryptoType) ?? null,
        ]);

        try {
            $redirect_url = (new PaymentService())->getPaymentUrl($payment_gateway, $transaction, $paymentGatewayMethod);

            if ($redirect_url) {
                Log::debug("Payment URL: " . $redirect_url);

                return response()->json([
                    'success'       => true,
                    'payment_url'   => $redirect_url,
                    'toast_title'   => trans('public.successful'),
                    'toast_message' => trans('public.toast_deposit_request_success_message'),
                    'toast_type'    => 'success'
                ]);
            }

            return response()->json([
                'success'       => false,
                'toast_title'   => trans('public.gateway_error'),
                'toast_message' => trans('public.please_try_again_later'),
                'toast_type'    => 'error'
            ]);
        } catch (Exception $e) {
            // Log server-side for debugging
            Log::error('Deposit error: ' . $e->getMessage());

            // Send back error to frontend
            return response()->json([
                'success'       => false,
                'toast_title'   => trans('public.gateway_error'),
                'toast_message' => $e->getMessage(),
                'toast_type'    => 'error'
            ], 400);
        }
    }

    public function depositCallback(Request $request)
    {
        $rawBody = $request->getContent();

        $response = json_decode($rawBody, true);

        Log::debug("Callback Response: " , $response);

        $transaction = Transaction::with('payment_gateway')
            ->where('transaction_number', $response['partner_order_code'])
            ->where('status', 'processing')
            ->first();
            // ->whereHas('payment_gateway', function ($query) use ($result) {
            //     $query->where('payment_app_number', $result['partner_id']);
            // })
            // ->first();

        if ($transaction->payment_gateway->platform === 'crypto') {
            //crypto
            $result = [
                'partner_id' =>  $response['partner_id'],
                'system_order_code' => $response['system_order_code'],
                'partner_order_code' => $response['partner_order_code'],
                'guest_id' => $response['guest_id'],
                'amount' => $response['amount'],
                'amount_cny' => $response['amount_cny'],
                'otc_usdt_cny' => $response['otc_usdt_cny'],
                'request_time' => $response['request_time'] ?? null,
                'extra_data' => $response['extra_data'] ?? null,
                'txid' => $response['payment']['txid'] ?? null,
                'paid_amount' => $response['payment']['paid_amount'] ?? null,
                'fees' => $response['payment']['fees'] ?? null,
                'payment_time' => $response['payment']['payment_time'] ?? null,
                'callback_time' => $response['payment']['callback_time'] ?? null,
                'erc20address' => $response['payment']['erc20address'] ?? null,
                'trc20address' => $response['payment']['trc20address'] ?? null,
                'status' => $response['payment']['status'] ?? null,
                'sign' => $response['sign'] ?? null,
            ];
        }
        else {
            //bank
            $result = [
                'partner_id' =>  $response['partner_id'],
                'system_order_code' => $response['system_order_code'],
                'partner_order_code' => $response['partner_order_code'],
                'channel_code' => $response['channel_code'],
                'amount' => $response['amount'],
                'request_time' => $response['request_time'] ?? null,
                'extra_data' => $response['extra_data'] ?? null,
                'txid' => $response['payment']['payment_id'] ?? null,
                'paid_amount' => $response['payment']['paid_amount'] ?? null,
                'fees' => $response['payment']['fees'] ?? null, // remember to convert back from vnd to usd
                'payment_time' => $response['payment']['payment_time'] ?? null,
                'bank_code' => $response['payment']['bank_code'] ?? null,
                'bank_account_no' => $response['payment']['bank_account_no'] ?? null,
                'bank_account_name' => $response['payment']['bank_account_name'] ?? null,
                'callback_time' => $response['payment']['callback_time'] ?? null,
                'status' => $response['payment']['status'] == 4 ? 'success' : 'fail',
                'sign' => $response['sign'] ?? null,
            ];
        }

        $status = $result['status'] == 'success' ? 'successful' : 'failed';

        if($transaction->payment_gateway->platform === 'crypto') {
            $to_wallet_address = $transaction->payment_account_type == 'trc20' ?  $result['trc20address'] : $result['erc20address'];

            $transaction->update([
                'to_wallet_address' => $to_wallet_address ?? null,
                'txn_hash' => $result['txid'],
                'from_currency' => 'USDT',
                'to_currency' => 'USD',
                'transaction_amount' => $transaction->amount,
                'status' => $status,
                'comment' => $result['system_order_code'] ?? null,
                'approved_at' => now()
            ]);
        } else {
            $to_wallet_address = $result['bank_account_no'];
            $fees = round($result['fees'] / $transaction->conversion_rate, 2);

            $payment_platform_name = Bank::where('bank_code', $result['bank_code'])
                ->orWhere('alias_bank_code', $result['bank_code'])
                ->first();

            $transaction->update([
                'payment_platform_name' => $payment_platform_name->bank_name ?? null,
                'payment_account_no' => $result['bank_account_no'] ?? null,
                'payment_account_type' => 'account',
                'bank_code' => $result['bank_code'] ?? null,
                'to_wallet_address' => $to_wallet_address ?? null,
                'txn_hash' => $result['txid'],
                'transaction_charges' => $fees,
                'transaction_amount' => $transaction->amount,
                'from_currency' => 'VND',
                'to_currency' => 'USD',
                'status' => $status,
                'comment' => $result['system_order_code'] ?? null,
                'approved_at' => now()
            ]);
        }

        if ($transaction->status == 'successful') {
            if ($transaction->transaction_type == 'deposit') {
                $this->proceed_deposit_to_account($transaction);

                return response()->json(['success' => true, 'message' => 'Deposit Success']);
            }
        }

        return response()->json(['success' => false, 'message' => 'Deposit Failed']);
    }

    //payment gateway return function
    public function depositReturn(Request $request)
    {
        return to_route('dashboard');
    }

    public function generate_link(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'accountType' => ['required', 'exists:account_types,account_group'],
            'downline_id' => ['required']
        ])->setAttributeNames([
            'accountType' => trans('public.account_type'),
            'downline_id' => trans('public.downline'),
        ]);
        $validator->validate();

        // Retrieve the account type by account_group
        $accountType = AccountType::where('account_group', $request->accountType)->first();
        $user = User::where('id', $request->downline_id)->first();

        $access = AccountTypeAccess::firstOrCreate([
            'account_type_id' => $accountType->id,
            'user_id' => $request->downline_id,
        ]);

        $link = md5($user->email . $accountType->account_group);

        return redirect()->back()->with([
            'success' => true,
            'message' => 'Link generated successfully.',
            'notification' => [
                'link' => $link,
            ],
        ]);
    }

    public function hypay_deposit_callback(Request $request)
    {
        $bodyContent = $request->getContent();
        $dataArray = json_decode($bodyContent, true);

        Log::debug("Callback Response: " , $dataArray);

        $transaction = Transaction::firstWhere('transaction_number', $dataArray['mch_no']);

        $payment_gateway = PaymentGateway::find($transaction->payment_gateway_id);

        if ($dataArray['status'] == 'cancel') {
            $transaction->update([
                'status' => 'failed',
                'comment' => 'Transaction has been cancelled.',
                'approved_at' => now()
            ]);
        }

        $timestamp = $request->header('ACCESS-TIMESTAMP');
        $signature = $request->header('ACCESS-SIGN');

        $concatenatedString = $bodyContent . $timestamp;
        $hashedSign = hash_hmac('sha256', $concatenatedString, $payment_gateway->secondary_key);

        if ($signature != $hashedSign) {
            return response()->json(['message' => 'Invalid JSON body'], 400);
        }

        $status = $dataArray['status'] == 'success' ? 'successful' : 'failed';

        $transaction->update([
            'transaction_amount' => $transaction->amount,
            'from_currency' => 'CNY',
            'to_currency' => 'USD',
            'status' => $status,
            'comment' => $transaction->transaction_number,
            'approved_at' => now()
        ]);

        if ($transaction->status == 'successful') {
            if ($transaction->transaction_type == 'deposit') {
                $this->proceed_deposit_to_account($transaction);

                return response()->json(['success' => true, 'message' => 'Deposit Success']);
            }
        }

        return response()->json(['success' => false, 'message' => 'Deposit Failed']);
    }

    /**
     * @throws Exception
     */
    public function psp_deposit_callback(Request $request)
    {
        $dataArray = $request->all();

        Log::debug("Callback Response: ", $dataArray);

        // Extract the signature
        $signature = $dataArray['sign'] ?? null;

        if (!$signature) {
            throw new Exception('Missing signature in callback');
        }

        unset($dataArray['sign']);

        $filtered = array_filter($dataArray, fn($v) => $v !== null && $v !== '');
        ksort($filtered);

        $stringA = urldecode(http_build_query($filtered));

        $publicKeyPath = storage_path('app/keys/psp_public.pem');
        $publicKey = file_get_contents($publicKeyPath);

        $isValid = openssl_verify($stringA, base64_decode($signature), $publicKey);

        if ($isValid !== 1) {
            Log::error('Signature verification failed', ['stringA' => $stringA, 'signature' => $signature]);
            return response()
                ->json([
                    'success' => false,
                    'message' => 'Signature verification failed.',
                ]);
        }

        $transaction = Transaction::firstWhere('transaction_number', $dataArray['seqId']);

        $status = $dataArray['stat'] == '0000' ? 'successful' : 'failed';

        $transaction->update([
            'transaction_amount' => $transaction->amount,
            'status' => $status,
            'comment' => $dataArray['tradeNo'] ?? null,
            'approved_at' => now()
        ]);

        if ($transaction->status == 'successful') {
            if ($transaction->transaction_type == 'deposit') {
                $this->proceed_deposit_to_account($transaction);

                return response("SUCCESS", 200)
                    ->header('Content-Type', 'text/plain');
            }
        }

        return response()->json(['success' => false, 'message' => 'Deposit Failed']);
    }

    private function proceed_deposit_to_account($transaction)
    {
        $trade = null;

        $tradingAccount = TradingAccount::with('account_type')
            ->where('meta_login', $transaction->to_meta_login)
            ->first();
        $multiplier = $tradingAccount->account_type->balance_multiplier;
        $adjusted_amount = $transaction->transaction_amount * $multiplier;

        try {
            $trade = (new MetaFourService)->createTrade($tradingAccount->meta_login, $adjusted_amount, "Deposit: " . $transaction->comment, 'balance', '');
        } catch (Throwable $e) {
            if ($e->getMessage() == "Not found") {
                TradingUser::firstWhere('meta_login', $transaction->to_meta_login)->update(['acc_status' => 'Inactive']);
            } else {
                Log::error($e->getMessage());
            }
        }
        $ticket = $trade['ticket'] ?? null;
        $transaction->ticket = $ticket;
        $transaction->save();

        $user = User::firstWhere('id', $transaction->user_id);

        Mail::to($user->email)->send(new DepositSuccessMail($user, $transaction->to_meta_login, $transaction->amount, $transaction->created_at));
    }
}
