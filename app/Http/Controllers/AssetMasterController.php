<?php

namespace App\Http\Controllers;

use App\Models\AssetMaster;
use App\Models\AssetMasterProfitDistribution;
use App\Models\AssetMasterUserFavourite;
use App\Models\AssetSubscription;
use App\Models\Group;
use App\Models\Term;
use App\Models\TradingAccount;
use App\Services\MetaFourService;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AssetMasterController extends Controller
{
    public function index()
    {
        $terms = Term::where('slug', 'limited-power-of-attorney-lpoa')->get();

        $structuredTerms = [];

        foreach ($terms as $term) {
            $locale = $term->locale;
            $structuredTerms[$locale] = [
                'title' => $term->title,
                'contents' => $term->contents,
            ];
        }

        return Inertia::render('AssetMaster/AssetMaster', [
            'terms' => $structuredTerms
        ]);
    }

    public function getMasters(Request $request)
    {
        // Fetch limit with default
        $limit = $request->input('limit', 12);

        // Fetch parameter from request
        $search = $request->input('search', '');
        $sortType = $request->input('sorting');
        $tag = $request->input('tag', '');
        $minInvestmentAmountRange = $request->input('minInvestmentAmountRange', '');

        // Initialize the query for total count
        $countQuery = AssetMaster::query();

        // Apply search parameter to multiple fields
        if (!empty($search)) {
            $countQuery->where(function($query) use ($search) {
                $query->where('trader_name', 'LIKE', "%$search%")
                    ->orWhere('asset_name', 'LIKE', "%$search%");
            });
        }

        // Apply tag filter
        if (!empty($tag)) {
            $tags = explode(',', $tag);
            foreach ($tags as $singleTag) {
                switch (trim($singleTag)) {
                    case 'no_min':
                        $countQuery->where('minimum_investment', 0);
                        break;
                    case 'lock_free':
                        $countQuery->where('minimum_investment_period', 0);
                        break;
                    case 'zero_fee':
                        $countQuery->where('performance_fee', 0);
                        break;
                    default:
                        return response()->json(['error' => 'Invalid filter'], 400);
                }
            }
        }

        // Apply min amount range filter
        if (!empty($minInvestmentAmountRange)) {
            $range = explode(',', $minInvestmentAmountRange);
            $min = $range[0] ?? null;
            $max = $range[1] ?? null;
            if (!is_null($min) && !is_null($max)) {
                $countQuery->whereBetween('minimum_investment', [$min, $max]);
            } elseif (!is_null($min)) {
                $countQuery->where('minimum_investment', '>=', $min);
            } elseif (!is_null($max)) {
                $countQuery->where('minimum_investment', '<=', $max);
            }
        }

        // Apply group filtering based on user's group membership if the master type is 'private'
        $userGroups = Auth::user()->groupHasUser()->pluck('group_id')->toArray();

        $countQuery->where(function ($query) use ($userGroups) {
            $query->where('type', 'public')
                ->orWhere(function ($q) use ($userGroups) {
                    $q->where('type', 'private')
                        ->whereHas('visible_to_groups', function ($q) use ($userGroups) {
                            $q->whereIn('group_id', $userGroups);
                        });
                });
        });

        // Get total count of masters
        $totalRecords = $countQuery->count();

        // Initialize the query for fetching results
        $mastersQuery = AssetMaster::query();

        // Apply search parameter to multiple fields
        if (!empty($search)) {
            $mastersQuery->where(function($query) use ($search) {
                $query->where('trader_name', 'LIKE', "%$search%")
                    ->orWhere('asset_name', 'LIKE', "%$search%");
            });
        }

        // Apply sorting dynamically
        switch ($sortType) {
            case 'latest':
                $mastersQuery
                    ->where('status', 'active')
                    ->orderBy('created_at', 'desc');
                break;

            case 'popular':
                $mastersQuery->leftJoin('asset_master_user_favourites', function ($join) {
                    $join->on('asset_masters.id', '=', 'asset_master_user_favourites.asset_master_id')
                        ->where('asset_master_user_favourites.user_id', Auth::id());
                })
                    ->where('asset_masters.status', 'active')
                    ->select('asset_masters.*', DB::raw('COALESCE(total_likes_count, 0) + COALESCE(COUNT(asset_master_user_favourites.id), 0) AS popularity'))
                    ->groupBy('asset_masters.id')
                    ->orderBy(DB::raw('COALESCE(total_likes_count, 0) + COALESCE(COUNT(asset_master_user_favourites.id), 0)'), 'desc');
                break;

            case 'largest_fund':
                $mastersQuery->leftJoin('asset_subscriptions', function ($join) {
                    $join->on('asset_masters.id', '=', 'asset_subscriptions.asset_master_id')
                        ->where('asset_subscriptions.status', 'ongoing');
                })
                    ->where('asset_masters.status', 'active')
                    ->select('asset_masters.*', DB::raw('total_fund + COALESCE(SUM(asset_subscriptions.investment_amount), 0) AS total_fund_combined'))
                    ->groupBy('asset_masters.id', 'total_fund')
                    ->orderBy(DB::raw('total_fund + COALESCE(SUM(asset_subscriptions.investment_amount), 0)'), 'desc');
                break;

            case 'most_investors':
                $mastersQuery->leftJoin('asset_subscriptions', function ($join) {
                    $join->on('asset_masters.id', '=', 'asset_subscriptions.asset_master_id')
                        ->where('asset_subscriptions.status', 'ongoing');
                })
                    ->where('asset_masters.status', 'active')
                    ->select('asset_masters.*', DB::raw('total_investors + COALESCE(COUNT(asset_subscriptions.id), 0) AS total_investors_combined'))
                    ->groupBy('asset_masters.id', 'total_investors')
                    ->orderBy(DB::raw('total_investors + COALESCE(COUNT(asset_subscriptions.id), 0)'), 'desc');
                break;

            case 'favourites':
                $mastersQuery->where('status', 'active')
                    ->whereHas('asset_user_favourites', function ($query) {
                        $query->where('user_id', Auth::id());
                    });
                break;

            case 'joining':
                $mastersQuery->where('status', 'active')
                    ->whereHas('asset_subscriptions', function ($query) {
                        $query->where('user_id', Auth::id());
                    });
                break;

            default:
                return response()->json(['error' => 'Invalid filter'], 400);
        }

        // Apply tag filter
        if (!empty($tag)) {
            $tags = explode(',', $tag);
            foreach ($tags as $singleTag) {
                switch (trim($singleTag)) {
                    case 'no_min':
                        $mastersQuery->where('minimum_investment', 0);
                        break;
                    case 'lock_free':
                        $mastersQuery->where('minimum_investment_period', 0);
                        break;
                    case 'zero_fee':
                        $mastersQuery->where('performance_fee', 0);
                        break;
                    default:
                        return response()->json(['error' => 'Invalid filter'], 400);
                }
            }
        }

        // Apply min amount range filter
        if (!empty($minInvestmentAmountRange)) {
            $range = explode(',', $minInvestmentAmountRange);
            $min = $range[0] ?? null;
            $max = $range[1] ?? null;
            if (!is_null($min) && !is_null($max)) {
                $mastersQuery->whereBetween('minimum_investment', [$min, $max]);
            } elseif (!is_null($min)) {
                $mastersQuery->where('minimum_investment', '>=', $min);
            } elseif (!is_null($max)) {
                $mastersQuery->where('minimum_investment', '<=', $max);
            }
        }

        // Apply group filtering based on user's group membership if the master type is 'private'
        $mastersQuery->where(function ($query) use ($userGroups) {
            $query->where('type', 'public')
                ->orWhere(function ($q) use ($userGroups) {
                    $q->where('type', 'private')
                        ->whereHas('visible_to_groups', function ($q) use ($userGroups) {
                            $q->whereIn('group_id', $userGroups);
                        });
                });
        });

        // Fetch paginated results
        $masters = $mastersQuery->paginate($limit);

        // Format masters
        $formattedMasters = $masters->map(function($master) {

            $asset_subscription = AssetSubscription::where('asset_master_id', $master->id)
                ->where('status', 'ongoing');

            // Get the last profit distribution before today
            $last_profit_distribution = AssetMasterProfitDistribution::where('asset_master_id', $master->id)
                ->whereDate('profit_distribution_date', '<', today())
                ->orderByDesc('profit_distribution_date')
                ->first();

            // Initialize the profit with the master's latest profit as a fallback
            $profit = $master->latest_profit;

            // If there's a last profit distribution, update the profit to that value
            if ($last_profit_distribution) {
                $profit = $last_profit_distribution->profit_distribution_percent;
            }

            // Calculate the monthly gain for the current month
            $monthly_gain = AssetMasterProfitDistribution::where('asset_master_id', $master->id)
                ->whereMonth('profit_distribution_date', Carbon::now()->month)
                ->whereDate('profit_distribution_date', '<', Carbon::today())
                ->sum('profit_distribution_percent');

            // Calculate the cumulative gain until yesterday, excluding the current month
            $cumulative_gain_until_yesterday = AssetMasterProfitDistribution::where('asset_master_id', $master->id)
                ->whereMonth('profit_distribution_date', '<', Carbon::now()->month)
                ->whereDate('profit_distribution_date', '<', Carbon::today())
                ->sum('profit_distribution_percent');

            if ($master->created_at->isCurrentMonth()) {
                $monthly_gain += $master->monthly_gain;
                $total_gain = $master->total_gain + $monthly_gain;
            } else {
                $total_gain = $master->total_gain + $cumulative_gain_until_yesterday;
            }

            $userFavourites = $master->asset_user_favourites->count();

            $isFavourite = AssetMasterUserFavourite::where('user_id', Auth::id())
                ->where('asset_master_id', $master->id)
                ->first();

            return [
                'id' => $master->id,
                'asset_name' => $master->asset_name,
                'trader_name' => $master->trader_name,
                'total_investors' => $master->total_investors + $asset_subscription->count(),
                'total_fund' => $master->total_fund + $asset_subscription->sum('investment_amount'),
                'minimum_investment' => $master->minimum_investment,
                'minimum_investment_period' => $master->minimum_investment_period,
                'performance_fee' => $master->performance_fee,
                'penalty_fee' => $master->penalty_fee,
                'total_gain' => $total_gain,
                'monthly_gain' => $monthly_gain,
                'latest_profit' => $profit,
                'master_profile_photo' => $master->getFirstMediaUrl('master_profile_photo'),
                'total_likes_count' => $master->total_likes_count + $userFavourites,
                'isFavourite' => $isFavourite ? 1 : 0,
            ];
        });

        return response()->json([
            'masters' => $formattedMasters,
            'totalRecords' => $totalRecords,
            'currentPage' => $masters->currentPage(),
        ]);
    }

    public function getAvailableAccounts(Request $request)
    {
        $user = Auth::user();

        $manage_accounts = $user->tradingAccounts()
            ->whereHas('account_type', function($q) {
                $q->where('category', 'manage');
            })
            ->get();

        try {
            foreach ($manage_accounts as $trading_account) {
                (new MetaFourService)->getUserInfo($trading_account->meta_login);
            }
        } catch (\Throwable $e) {
            Log::error($e->getMessage());
        }

        $accounts = TradingAccount::where('user_id', $user->id)
            ->whereHas('account_type', function ($query) {
                $query->where('category', 'manage');
            })
            ->whereDoesntHave('asset_subscriptions', function ($query) {
                $query->whereIn('status', ['ongoing', 'pending']);
            })
            ->get()
            ->map(function($account) {
                return [
                    'id' => $account->id,
                    'meta_login' => $account->meta_login,
                    'balance' => $account->balance,
                ];
            });

        return response()->json([
            'accounts' => $accounts
        ]);
    }

    public function showPammInfo($id)
    {
        $master = AssetMaster::where('id', $id)->select('id', 'asset_name')->first();

        return Inertia::render('AssetMaster/PammInfo', ['master' => $master]);
    }

    public function getMasterDetail(Request $request)
    {
        $id = $request->id;
        $master = AssetMaster::find($id);

        $date = new DateTime($master->started_at);
        $duration = $date->diff(now())->format('%d');

        // Get the last profit distribution before today
        $last_profit_distribution = AssetMasterProfitDistribution::where('asset_master_id', $master->id)
            ->whereDate('profit_distribution_date', '<', today())
            ->orderByDesc('profit_distribution_date')
            ->first();

        // Initialize the profit with the master's latest profit as a fallback
        $profit = $master->latest_profit;

        // If there's a last profit distribution, update the profit to that value
        if ($last_profit_distribution) {
            $profit = $last_profit_distribution->profit_distribution_percent;
        }

        // Calculate the monthly gain for the current month
        $monthly_gain = AssetMasterProfitDistribution::where('asset_master_id', $master->id)
            ->whereMonth('profit_distribution_date', Carbon::now()->month)
            ->whereDate('profit_distribution_date', '<', Carbon::today())
            ->sum('profit_distribution_percent');

        // Calculate the cumulative gain until yesterday, excluding the current month
        $cumulative_gain_until_yesterday = AssetMasterProfitDistribution::where('asset_master_id', $master->id)
            ->whereMonth('profit_distribution_date', '<', Carbon::now()->month)
            ->whereDate('profit_distribution_date', '<', Carbon::today())
            ->sum('profit_distribution_percent');

        if ($master->created_at->isCurrentMonth()) {
            $monthly_gain += $master->monthly_gain;
            $total_gain = $master->total_gain + $monthly_gain;
        } else {
            $total_gain = $master->total_gain + $cumulative_gain_until_yesterday;
        }

        $userFavourites = $master->asset_user_favourites->count();

        $isFavourite = AssetMasterUserFavourite::where('user_id', Auth::id())
            ->where('asset_master_id', $master->id)
            ->first();

        $master_detail = [
            'id' => $master->id,
            'asset_name' => $master->asset_name,
            'trader_name' => $master->trader_name,
            'minimum_investment' => $master->minimum_investment,
            'minimum_investment_period' => $master->minimum_investment_period,
            'performance_fee' => $master->performance_fee,
            'penalty_fee' => $master->penalty_fee,
            'total_gain' => $total_gain,
            'monthly_gain' => $monthly_gain,
            'latest_profit' => $profit,
            'master_profile_photo' => $master->getFirstMediaUrl('master_profile_photo'),
            'total_likes_count' => $master->total_likes_count + $userFavourites,
            'isFavourite' => $isFavourite ? 1 : 0,
            'with_us' => $duration,
            'profile_photo' => $master->getFirstMediaUrl('master_profile_photo'),
        ];

        return response()->json([
            'masterDetail' => $master_detail,
        ]);
    }

    public function joinPamm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'meta_login' => ['required'],
            'investment_amount' => ['required'],
        ])->setAttributeNames([
            'meta_login' => trans('public.managed_account'),
            'investment_amount' => trans('public.investment_amount'),
        ]);
        $validator->validate();

        try {
            (new MetaFourService)->getUserInfo($request->meta_login);
        } catch (\Throwable $e) {
            Log::error($e->getMessage());
        }

        $trading_account = TradingAccount::where('meta_login', $request->meta_login)->first();
        $asset_master = AssetMaster::find($request->asset_master_id);

        if ($trading_account->balance < $asset_master->minimum_investment) {
            throw ValidationException::withMessages(['investment_amount' => trans('public.insufficient_balance')]);
        }

        $investment_periods = $asset_master->minimum_investment_period;

        AssetSubscription::create([
            'user_id' => Auth::id(),
            'meta_login' => $trading_account->meta_login,
            'asset_master_id' => $asset_master->id,
            'investment_amount' => $trading_account->balance,
            'investment_periods' => $investment_periods,
            'matured_at' => $investment_periods > 0 ? now()->addMonths($investment_periods)->endOfDay() : null,
        ]);

        return back()->with('toast', [
            'title' => trans("public.toast_join_pamm_successful"),
            'message' => trans("public.toast_join_pamm_successful_message"),
            'type' => 'success',
        ]);
    }

    public function getMasterMonthlyProfit(Request $request)
    {
        $dateParts = explode('/', $request->input('month'));
        $month = $dateParts[0];
        $year = $dateParts[1];

        // Apply month and year filtering if provided
        $filteredQuery = AssetMasterProfitDistribution::where('asset_master_id', $request->master_id)
            ->when($request->filled('month'), function ($query) use ($month, $year) {
                $query->whereYear('profit_distribution_date', $year)
                    ->whereMonth('profit_distribution_date', $month)
                    ->whereDate('profit_distribution_date', '<', today());
            });

        // Generate chart results using the filtered query
        $chartResults = $filteredQuery->select(
            DB::raw('DAY(profit_distribution_date) as day'),
            DB::raw('SUM(profit_distribution_percent) as pamm_return')
        )
            ->groupBy('day')
            ->get();

        // Initialize the chart data structure
        $chartData = [
            'labels' => array_map(function($day) use ($month, $year) {
                return sprintf('%02d/%02d', $day, $month);
            }, range(1, cal_days_in_month(CAL_GREGORIAN, $month, $year))), // Generate an array of labels in 'day/month' format
            'datasets' => [],
        ];

        $dataset = [
            'label' => trans('public.profit'),
            'data' => array_map(function ($label) use ($chartResults) {
                // Extract the day part from the label (formatted as 'day/month')
                $day = (int) explode('/', $label)[0];
                return $chartResults->firstWhere('day', $day)->pamm_return ?? 0;
            }, $chartData['labels']),
            'backgroundColor' => array_map(function ($label) use ($chartResults) {
                // Extract the day part from the label (formatted as 'day/month')
                $day = (int) explode('/', $label)[0];
                $pammReturn = $chartResults->firstWhere('day', $day)->pamm_return ?? 0;
                return $pammReturn > 0 ? '#06D001' : '#FF2D58';
            }, $chartData['labels']),
            'pointStyle' => false,
            'fill' => true,
        ];

        $chartData['datasets'][] = $dataset;

        return response()->json([
            'chartData' => $chartData,
            'selectedMonthProfit' => array_sum($dataset['data']),
        ]);
    }

    public function addToFavourites(Request $request)
    {
        $user = Auth::user();

        $userFavouriteMaster = AssetMasterUserFavourite::withTrashed()
            ->where('user_id', $user->id)
            ->where('asset_master_id', $request->master_id)
            ->first();

        if (empty($userFavouriteMaster)) {
            AssetMasterUserFavourite::create([
                'user_id' => $user->id,
                'asset_master_id' => $request->master_id,
            ]);

            $isUserFavourite = true;
        } else {
            if ($userFavouriteMaster->trashed()) {
                $userFavouriteMaster->restore();

                $isUserFavourite = true;
            } else {
                $userFavouriteMaster->delete();

                $isUserFavourite = false;
            }
        }

        return response()->json([
            'status' => 'success',
            'isLike' => $isUserFavourite,
        ]);
    }
}
