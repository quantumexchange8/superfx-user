<?php

namespace App\Http\Controllers;

use App\Models\BillboardBonus;
use App\Models\BillboardProfile;
use App\Models\Term;
use App\Models\TradeBrokerHistory;
use App\Models\TradingAccount;
use App\Models\Transaction;
use Auth;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BillboardController extends Controller
{
    public function index()
    {
        $terms = Term::where('slug', 'terms-and-conditions')->get();

        $structuredTerms = [];

        foreach ($terms as $term) {
            $locale = $term->locale;
            $structuredTerms[$locale] = [
                'title' => $term->title,
                'contents' => $term->contents,
            ];
        }

        $achievementsCount = BillboardProfile::where('user_id', Auth::id())
            ->count();

        return Inertia::render('Billboard/Billboard', [
            'achievementsCount' => $achievementsCount,
            'terms' => $structuredTerms,
        ]);
    }

    public function getBonusWallet()
    {
        $user = Auth::user();

        return response()->json([
            'bonusWallet' => $user->bonus_wallet
        ]);
    }

    public function getTargetAchievements()
    {
        $targetAchievements = BillboardProfile::where('user_id', Auth::id())
            ->get()
            ->map(function($achievement) {
                $user = Auth::user();
                $bonus_amount = 0;
                $achieved_percentage = 0;
                $achieved_amount = 0;

                $today = Carbon::today();

                // Set start and end dates based on calculation period
                if ($achievement->calculation_period == 'every_sunday') {
                    // Start of the current week (Monday) and end of the current week (Sunday)
                    $startDate = $today->copy()->startOfWeek();
                    $endDate = $today->copy()->endOfWeek();
                } elseif ($achievement->calculation_period == 'every_second_sunday') {
                    // Start of the month
                    $startDate = $today->copy()->startOfMonth();

                    // Find the first Sunday of the month
                    $firstSunday = $startDate->copy()->next('Sunday');

                    // Find the second Sunday of the month
                    $secondSunday = $firstSunday->copy()->addWeek();

                    // If today is before or on the second Sunday, calculate until the day before the second Sunday
                    if ($today->lessThan($secondSunday)) {
                        $endDate = $secondSunday->copy()->subDay()->endOfDay();
                    } else {
                        // If today is after the second Sunday, set startDate to the second Sunday
                        $startDate = $secondSunday->copy();
                        $endDate = $today->copy()->endOfWeek(); // Or end of current week if needed
                    }

                } elseif ($achievement->calculation_period == 'first_sunday_of_every_month') {
                    $startDate = $today->startOfMonth();
                    $endDate = $startDate->copy()->endOfMonth();
                } else {
                    // Default to the entire current month if no calculation period is specified
                    $startDate = $today->copy()->startOfMonth();
                    $endDate = $today->copy()->endOfMonth();
                }

                if ($achievement->sales_calculation_mode == 'personal_sales') {
                    if ($achievement->sales_category == 'gross_deposit') {
                        $gross_deposit = Transaction::where('user_id', $user->id)
                            ->whereBetween('approved_at', [$startDate, $endDate])
                            ->where(function ($query) {
                                $query->where('transaction_type', 'deposit')
                                    ->orWhere('transaction_type', 'balance_in');
                            })
                            ->where('status', 'successful')
                            ->sum('transaction_amount');

                        $achieved_percentage = ($gross_deposit / $achievement->target_amount) * 100;
                        $bonus_amount = ($gross_deposit * $achievement->bonus_rate) / 100;
                        $achieved_amount = $gross_deposit;
                    } elseif ($achievement->sales_category == 'net_deposit') {
                        $total_deposit = Transaction::where('user_id', $user->id)
                            ->whereBetween('approved_at', [$startDate, $endDate])
                            ->where(function ($query) {
                                $query->where('transaction_type', 'deposit')
                                    ->orWhere('transaction_type', 'balance_in');
                            })
                            ->where('status', 'successful')
                            ->sum('transaction_amount');

                        $total_withdrawal = Transaction::where('user_id', $user->id)
                            ->whereBetween('approved_at', [$startDate, $endDate])
                            ->where(function ($query) {
                                $query->where('transaction_type', 'withdrawal')
                                    ->orWhere('transaction_type', 'balance_out')
                                    ->orWhere('transaction_type', 'rebate_out');
                            })
                            ->where('status', 'successful')
                            ->sum('transaction_amount');

                        $net_deposit = abs($total_deposit - $total_withdrawal);

                        $achieved_percentage = ($net_deposit / $achievement->target_amount) * 100;
                        $bonus_amount = ($net_deposit * $achievement->bonus_rate) / 100;
                        $achieved_amount = $net_deposit;
                    } elseif ($achievement->sales_category == 'trade_volume') {
                        $meta_logins = $user->tradingAccounts->pluck('meta_login');

                        $trade_volume = TradeBrokerHistory::whereIn('meta_login', $meta_logins)
                            ->whereBetween('created_at', [$startDate, $endDate])
                            ->sum('trade_lots');

                        $achieved_percentage = ($trade_volume / $achievement->target_amount) * 100;
                        $bonus_amount = $achieved_percentage >= $achievement->bonus_calculation_threshold ? $achievement->bonus_rate : 0;
                        $achieved_amount = $trade_volume;
                    }
                } elseif ($achievement->sales_calculation_mode == 'group_sales') {
                    $child_ids = $user->getChildrenIds();
                    $child_ids[] = $user->id;

                    if ($achievement->sales_category == 'gross_deposit') {
                        $gross_deposit = Transaction::whereIn('user_id', $child_ids)
                            ->whereBetween('approved_at', [$startDate, $endDate])
                            ->where(function ($query) {
                                $query->where('transaction_type', 'deposit')
                                    ->orWhere('transaction_type', 'balance_in');
                            })
                            ->where('status', 'successful')
                            ->sum('transaction_amount');

                        $achieved_percentage = ($gross_deposit / $achievement->target_amount) * 100;
                        $bonus_amount = ($gross_deposit * $achievement->bonus_rate) / 100;
                        $achieved_amount = $gross_deposit;
                    } elseif ($achievement->sales_category == 'net_deposit') {
                        $total_deposit = Transaction::whereIn('user_id', $child_ids)
                            ->whereBetween('approved_at', [$startDate, $endDate])
                            ->where(function ($query) {
                                $query->where('transaction_type', 'deposit')
                                    ->orWhere('transaction_type', 'balance_in');
                            })
                            ->where('status', 'successful')
                            ->sum('transaction_amount');

                        $total_withdrawal = Transaction::whereIn('user_id', $child_ids)
                            ->whereBetween('approved_at', [$startDate, $endDate])
                            ->where(function ($query) {
                                $query->where('transaction_type', 'withdrawal')
                                    ->orWhere('transaction_type', 'balance_out')
                                    ->orWhere('transaction_type', 'rebate_out');
                            })
                            ->where('status', 'successful')
                            ->sum('transaction_amount');

                        $net_deposit = $total_deposit - $total_withdrawal;

                        $achieved_percentage = ($net_deposit / $achievement->target_amount) * 100;
                        $bonus_amount = ($net_deposit * $achievement->bonus_rate) / 100;
                        $achieved_amount = $net_deposit;
                    } elseif ($achievement->sales_category == 'trade_volume') {
                        $meta_logins = TradingAccount::whereIn('user_id', $child_ids)
                            ->get()
                            ->pluck('meta_login')
                            ->toArray();

                        $trade_volume = TradeBrokerHistory::whereIn('meta_login', $meta_logins)
                            ->whereBetween('created_at', [$startDate, $endDate])
                            ->sum('trade_lots');

                        $achieved_percentage = ($trade_volume / $achievement->target_amount) * 100;
                        $bonus_amount = $achieved_percentage >= $achievement->bonus_calculation_threshold ? $achievement->bonus_rate : 0;
                        $achieved_amount = $trade_volume;
                    }
                }

                return [
                    'id' => $achievement->id,
                    'sales_calculation_mode' => $achievement->sales_calculation_mode,
                    'bonus_badge' => $achievement->sales_calculation_mode == 'personal_sales' ? 'gray' : 'info',
                    'sales_category' => $achievement->sales_category,
                    'target_amount' => $achievement->target_amount,
                    'bonus_rate' => $achievement->bonus_rate,
                    'bonus_amount' => $bonus_amount,
                    'achieved_percentage' => $achieved_percentage,
                    'achieved_amount' => $achieved_amount,
                    'calculation_period' => $achievement->calculation_period,
                    'bonus_calculation_threshold' => $achievement->bonus_calculation_threshold,
                    'next_calculation_date' => $achievement->next_payout_at,
                ];
            });

        return response()->json([
            'targetAchievements' => $targetAchievements
        ]);
    }

    public function getTotalEarnedBonusData(Request $request)
    {
        $year = $request->year;

        $currentMonth = Carbon::now()->month;
        $previousMonth = Carbon::now()->subMonth()->month;

        $currentMonthBonus = BillboardBonus::whereYear('created_at', $year)
            ->where('bonus_month', $currentMonth)
            ->where('user_id', Auth::id())
            ->sum('bonus_amount');

        $previousMonthBonus = BillboardBonus::whereYear('created_at', $year)
            ->where('bonus_month', $previousMonth)
            ->where('user_id', Auth::id())
            ->sum('bonus_amount');

        if ($previousMonthBonus > 0) {
            $percentageChange = (($currentMonthBonus - $previousMonthBonus) / $previousMonthBonus) * 100;
        } else {
            $percentageChange = $currentMonthBonus > 0 ? 100 : 0;
        }

        // Your existing query to fetch chart data
        $bonusQuery = BillboardBonus::whereYear('created_at', $year)
            ->where('user_id', Auth::id());

        $chartResults = $bonusQuery->select(
            DB::raw('bonus_month as month'),
            DB::raw('SUM(bonus_amount) as bonus_amount')
        )
            ->groupBy('bonus_month')
            ->get();

        $shortMonthNames = [];
        for ($month = 1; $month <= 12; $month++) {
            $shortMonthNames[] = date('M', mktime(0, 0, 0, $month, 1));
        }

        $chartData = [
            'labels' => $shortMonthNames,
            'datasets' => [],
        ];

        $dataset = [
            'label' => trans('public.bonus_earned'),
            'data' => array_map(function ($month) use ($chartResults) {
                return $chartResults->firstWhere('month', $month)->bonus_amount ?? 0;
            }, range(1, 12)), // Use month numbers 1-12
            'backgroundColor' => '#36BFFA',
            'borderRadius' => 12,
            'pointStyle' => false,
            'fill' => true,
        ];

        $chartData['datasets'][] = $dataset;

        $totalEarnedBonus = $bonusQuery->sum('bonus_amount');

        return response()->json([
            'chartData' => $chartData,
            'totalEarnedBonus' => $totalEarnedBonus,
            'percentageChange' => $percentageChange,
        ]);
    }

    public function getStatementData(Request $request)
    {
        $bonusQuery = BillboardBonus::where('billboard_profile_id', $request->profile_id)
            ->where('user_id', Auth::id());

        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');
        if ($startDate && $endDate) {
            $start_date = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
            $end_date = Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay();

            $bonusQuery->whereBetween('created_at', [$start_date, $end_date]);
        }

        $bonuses = $bonusQuery
            ->get()
            ->map(function ($bonus) {
                return [
                    'id' => $bonus->id,
                    'target_amount' => $bonus->target_amount,
                    'achieved_amount' => $bonus->achieved_amount,
                    'bonus_rate' => $bonus->bonus_rate,
                    'bonus_amount' => $bonus->bonus_amount,
                    'created_at' => $bonus->created_at,
                ];
            });

        return response()->json([
           'bonuses' => $bonuses,
           'totalBonusAmount' => $bonusQuery->sum('bonus_amount'),
        ]);
    }

    public function getBonusWithdrawalHistories(Request $request)
    {
        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');

        $query = Transaction::where('user_id', Auth::id())
            ->where('category', 'bonus_wallet')
            ->where('transaction_type', 'withdrawal');

        if ($startDate && $endDate) {
            $start_date = Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
            $end_date = Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay();

            $query->whereBetween('created_at', [$start_date, $end_date]);
        }

        $bonusWithdrawalHistories = $query
            ->latest()
            ->get()
            ->map(function ($transaction) {
                return [
                    'category' => $transaction->category,
                    'transaction_type' => $transaction->transaction_type,
                    'user_name' => $transaction->user->name,
                    'user_email' => $transaction->user->email,
                    'user_profile_photo' => $transaction->user->getFirstMediaUrl('profile_photo'),
                    'from_wallet_name' => $transaction->from_wallet->type,
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
                    'approved_at' => $transaction->approved_at,
                    'to_wallet_name' => $transaction->payment_account->payment_account_name ?? '-'
                ];
            });

        return response()->json([
            'bonusWithdrawalHistories' => $bonusWithdrawalHistories,
            'totalApprovedAmount' => $query->where('status', 'successful')->sum('amount'),
        ]);
    }
}
