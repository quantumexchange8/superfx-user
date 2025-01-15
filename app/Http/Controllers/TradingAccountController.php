<?php

namespace App\Http\Controllers;

use App\Mail\CreateAccountMail;
use App\Mail\DepositSuccessMail;
use App\Mail\TransferMoneySuccessMail;
use App\Mail\WithdrawalRequestMail;
// use App\Mail\WithdrawalRequestUsdtMail;
use App\Models\Term;
use App\Models\User;
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
use Illuminate\Validation\ValidationException;
use App\Models\PaymentGateway;
use App\Models\CurrencyConversionRate;
use function Symfony\Component\Translation\t;

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

        return Inertia::render('TradingAccount/Account', [
            'terms' => $structuredTerms
        ]);
    }

    public function getOptions()
    {
        $locale = app()->getLocale();

        $accountOptions = AccountType::whereNot('account_group', 'Demo Account')
            ->where('status', 'active')
            ->get()
            ->map(function ($accountType) use ($locale) {
                // $translations = json_decode($accountType->descriptions, true);
                return [
                    'id' => $accountType->id,
                    'name' => $accountType->name,
                    'slug' => $accountType->slug,
                    'account_group' => $accountType->account_group,
                    'leverage' => $accountType->leverage,
                    // 'descriptions' => $translations[$locale],
                ];
            });

        $conversionRate = CurrencyConversionRate::firstWhere('base_currency', 'VND')->deposit_rate;

        return response()->json([
            'leverages' => (new DropdownOptionService())->getLeveragesOptions(),
            'transferOptions' => (new DropdownOptionService())->getInternalTransferOptions(),
            'walletOptions' => (new DropdownOptionService())->getWalletOptions(),
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
        // Validate the request data
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric',
            'leverage' => 'required|integer|min:1',
        ]);


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
        } catch (\Throwable $e) {
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
                    'account_type_leverage' => $account->account_type->leverage,
                    'account_type_color' => $account->account_type->color,
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
        $validator = Validator::make($request->all(), [
            'account_id' => ['required', 'exists:trading_accounts,id'],
            'amount' => ['required', 'numeric', 'gte:30'],
            'payment_account_id' => ['required']
        ])->setAttributeNames([
            'account_id' => trans('public.account'),
            'amount' => trans('public.amount'),
            'payment_account_id' => trans('public.receiving_wallet'),
        ]);
        $validator->validate();

        $amount = $request->amount;

         $tradingAccount = TradingAccount::find($request->account_id);
         (new MetaFourService)->getUserInfo($tradingAccount->meta_login);

         if ($tradingAccount->balance < $amount) {
             throw ValidationException::withMessages(['amount' => trans('public.insufficient_balance')]);
         }

         try {
             $trade = (new MetaFourService)->createTrade($tradingAccount->meta_login, -$amount,"Withdraw From Account", 'balance', '');
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

         $amount = $request->input('amount');
         $paymentWallet = PaymentAccount::find($request->payment_account_id);

         $transaction = Transaction::create([
             'user_id' => Auth::id(),
             'category' => 'trading_account',
             'transaction_type' => 'withdrawal',
             'from_meta_login' => $tradingAccount->meta_login,
             'transaction_number' => RunningNumberService::getID('transaction'),
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
             'amount' => $amount,
             'transaction_charges' => 0,
             'transaction_amount' => $amount,
             'status' => 'processing',
         ]);

         $user = Auth::user();

         Mail::to($user->email)->send(new WithdrawalRequestMail($user, $tradingAccount->meta_login, $amount, $transaction->created_at, $paymentWallet->account_no));
        // disable trade

        // Set notification data in the session
        return redirect()->back()->with('notification', [
            'details' => $transaction,
            'type' => 'withdrawal',
            // 'withdrawal_type' => 'rebate' this not put show meta_login put rebate show Rebate put bonus show Bonus
        ]);
    }

    public function internal_transfer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_id' => ['required', 'exists:trading_accounts,id'],
            'to_meta_login' => ['required']
        ])->setAttributeNames([
            'account_id' => trans('public.account'),
            'to_meta_login' => trans('public.transfer_to'),
        ]);
        $validator->validate();

         $tradingAccount = TradingAccount::find($request->account_id);
         (new MetaFourService)->getUserInfo($tradingAccount->meta_login);

         $tradingAccount = TradingAccount::find($request->account_id);
         $amount = $request->input('amount');
         $to_meta_login = $request->input('to_meta_login');

         if ($tradingAccount->balance < $amount) {
             throw ValidationException::withMessages(['wallet' => trans('public.insufficient_balance')]);
         }

         try {
             $tradeFrom = (new MetaFourService)->createTrade($tradingAccount->meta_login, -$amount, "Withdraw From Account", 'balance', '');
             $tradeTo = (new MetaFourService)->createTrade($to_meta_login, $amount, "Deposit To Account", 'balance', '');
         } catch (\Throwable $e) {
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
            'amount' => $amount,
            'transaction_charges' => 0,
            'transaction_amount' => $amount,
            'status' => 'successful',
            'comment' => 'to ' . $to_meta_login
         ]);

         $user = Auth::user();

         Mail::to($user->email)->send(new TransferMoneySuccessMail($user, $tradingAccount->meta_login, $to_meta_login, $amount));

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
        } catch (\Throwable $e) {
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
        } catch (\Exception $e) {
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
        } catch (\Throwable $e) {
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
        //change cryptoType validation as bank won't work
        Validator::make($request->all(), [
            'meta_login' => ['required', 'exists:trading_accounts,meta_login'],
            'payment_platform' => ['required'],
            'cryptoType' => ['required_if:payment_platform,crypto'],
            'amount' => ['required', 'numeric', 'gte:50'],
        ])->setAttributeNames([
            'meta_login' => trans('public.account'),
            'payment_platform' => trans('public.platform'),
            'cryptoType' => trans('public.method'),
            'amount' => trans('public.amount'),
        ])->validate();

        $user = Auth::user();

        $environment = 'local';

        if (App::environment('production')) {
            $environment = 'production';
        }

        $payment_gateway = PaymentGateway::where('platform', $request->payment_platform)
                ->where('environment', $environment)
                ->first();

        $latest_transaction = Transaction::where('user_id', $user->id)
            ->where('category', 'trading_account')
            ->where('transaction_type', 'deposit')
            ->where('status', 'processing')
            ->latest()
            ->first();

        $amount = number_format(floatval($request->amount), 2, '.', '');

        // Check if the latest transaction exists and its created_at time is within the last 30 seconds
        if ($latest_transaction && Carbon::parse($latest_transaction->created_at)->diffInSeconds(Carbon::now()) < 30) {

            $remainingSeconds = 30 - Carbon::parse($latest_transaction->created_at)->diffInSeconds(Carbon::now());

            return redirect()->back()
                ->with('title', trans('public.invalid_action'))
                ->with('warning', trans('public.please_wait_for_seconds', ['seconds' => $remainingSeconds]));
        }

        $conversion_rate = null;
        $conversion_amount = null;

        if ($request->payment_platform == 'bank'){
            $conversion_rate = CurrencyConversionRate::firstWhere('base_currency', 'VND')->deposit_rate;
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
            'conversion_rate' => $conversion_rate ?? null,
            'conversion_amount' => $conversion_amount ?? null,
            'transaction_charges' => 0,
            'status' => 'processing',
        ]);

        if ($payment_gateway) {
            $transaction->update([
                'payment_gateway_id' => $payment_gateway->id,
                'comment' => $payment_gateway->platform == 'crypto' ? $request->cryptoType : null,
            ]);

            // $domain = $_SERVER['HTTP_HOST'];
            // $notifyUrl = "https://$domain/deposit_callback";
            // $returnUrl = "https://$domain/deposit_return";

            $baseUrl = '';
            $params = [];
            switch ($payment_gateway->platform) {
                case 'bank':
                    $params = [
                        'partner_id' => $payment_gateway->payment_app_number,
                        'timestamp' => Carbon::now()->timestamp,
                        'random' => Str::random(14),
                        'partner_order_code' => $transaction->transaction_number,
                        'amount' => $conversion_amount,
                        'notify_url' => route('depositCallback'),
                        'return_url' => route('depositReturn'),
                    ];

                    $data = [
                        $params['partner_id'],
                        $params['timestamp'],
                        $params['random'],
                        $params['partner_order_code'],
                        $params['amount'],
                        '', //bank
                        '', //bank
                        $params['notify_url'],
                        $params['return_url'],
                        '',
                        $payment_gateway->payment_app_key
                    ];

                    $hashedCode = md5(implode(':', $data));
                    $params['sign'] = $hashedCode;

                    $baseUrl = $environment == 'production' ? $payment_gateway->payment_url . '/gateway/bnb/createVA.do' : $payment_gateway->payment_url . '/gateway/bnb/createVA.do';
                    break;

                case 'crypto':
                    $params = [
                        'partner_id' => $payment_gateway->payment_app_number,
                        'timestamp' => Carbon::now()->timestamp,
                        'random' => Str::random(14),
                        'partner_order_code' => $transaction->transaction_number,
                        'order_currency' => 0,
                        'order_language' => 'en_ww',
                        'guest_id' => md5('SuperFX' . $user->id),
                        'amount' => $amount,
                        'notify_url' => route('depositCallback'),
                        'return_url' => route('depositReturn'),
                    ];

                    $data = [
                        $params['partner_id'],
                        $params['timestamp'],
                        $params['random'],
                        $params['partner_order_code'],
                        $params['order_currency'], //crypto
                        $params['order_language'], //crypto
                        $params['guest_id'], //crypto
                        $params['amount'],
                        $params['notify_url'],
                        $params['return_url'],
                        '',
                        $payment_gateway->payment_app_key
                    ];

                    $hashedCode = md5(implode(':', $data));
                    $params['sign'] = $hashedCode;

                    $baseUrl = $request->cryptoType == 'ERC20' ? $payment_gateway->payment_url . '/gateway/usdt/createERC20.do' : $payment_gateway->payment_url . '/gateway/usdt/createTRC20.do';
                    break;
            }

            // Send response
            $redirectUrl = $baseUrl . "?" . http_build_query($params);
            Log::debug("POST URL : " . $redirectUrl);

            $response = Http::get($redirectUrl);

            $responseData = $response->json();

            if (isset($responseData['data']['payment_url'])) {
                $paymentUrl = $responseData['data']['payment_url'];
                Log::debug("Payment URL: " . $paymentUrl);

                return response()->json([
                    'success' => true,
                    'payment_url' => $paymentUrl,
                    'toast_title' => trans('public.successful'),
                    'toast_message' => trans('public.toast_deposit_request_success_message'),
                    'toast_type' => 'success'
                ]);
            } else {
                Log::error("Payment URL not found in response.", $responseData);

                return response()->json([
                    'success' => false,
                    'toast_title' => trans('public.gateway_error'),
                    'toast_message' => $responseData['msg'],
                    'toast_type' => 'error'
                ]);
            }
        }

        return redirect()->back()
            ->with('title', trans('public.successful'))
            ->with('success', trans('public.toast_deposit_request_success_message'));
    }

    public function depositCallback(Request $request)
    {
        $rawBody = $request->getContent();

        $response = json_decode($rawBody, true);

        Log::debug("Callback Response: " , $response);

        $transaction = Transaction::with('payment_gateway')
            ->where('transaction_number', $response['partner_order_code'])->first();
            // ->whereHas('payment_gateway', function ($query) use ($result) {
            //     $query->where('payment_app_number', $result['partner_id']);
            // })
            // ->first();

        $result = [];
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
            $fees = $result['amount'] - $result['fees'];
            $to_wallet_address = $result['erc20address'] ?? $result['trc20address'];

            $transaction->update([
                'to_wallet_address' => $to_wallet_address ?? null,
                'txn_hash' => $result['txid'],
                'transaction_charges' => $fees,
                'transaction_amount' => $transaction->amount,
                'from_currency' => 'USDT',
                'to_currency' => 'USD',
                'status' => $status,
                'approved_at' => now()
            ]);
        }
        else{
            $to_wallet_address = $result['bank_account_no'];
            $fees = round($result['fees'] / $transaction->conversion_rate, 2);

            $payment_platform_name = Bank::where('bank_code', $result['bank_code'])->first();;

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
                'approved_at' => now()
            ]);
        }
        // $final_amount = $transaction->amount - $fees;

        if ($transaction->status == 'successful') {
            if ($transaction->transaction_type == 'deposit') {
                $trade = null;
                try {
                    $trade = (new MetaFourService)->createTrade($transaction->to_meta_login, $transaction->transaction_amount,"Deposit", 'balance', '');
                } catch (\Throwable $e) {
                    if ($e->getMessage() == "Not found") {
                        TradingUser::firstWhere('meta_login', $transaction->to_meta_login)->update(['acc_status' => 'Inactive']);
                    } else {
                        Log::error($e->getMessage());
                    }
                }
                $ticket = $trade['ticket'] ?? null;
                $transaction->ticket = $ticket;
                $transaction->save();

                $user = User::where('id', $transaction->user_id)->first();

                Mail::to($user->email)->send(new DepositSuccessMail($user, $transaction->to_meta_login, $transaction->amount, $transaction->created_at));

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
}
