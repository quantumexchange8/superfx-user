<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use App\Models\OpenTrade;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Exports\OpenTradesExport;
use App\Exports\CloseTradesExport;
use App\Models\TradeBrokerHistory;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Auth;
use App\Services\DropdownOptionService;

class TradePositionController extends Controller
{
    public function open_positions()
    {
        return Inertia::render('TradePosition/OpenPositions', [
            'uplines' => (new DropdownOptionService())->getRebateUplines(),
            'symbols' => (new GeneralController())->getSymbols(true),
        ]);
    }

    public function open_trade(Request $request)
    {
        if ($request->has('lazyEvent')) {
            $user = Auth::user();

            if ($user->role == 'ib') {
                $child_ids = $user->getChildrenIds();
                $child_ids[] = $user->id;
            } else {
                $child_ids = [$user->id];
            }

            $data = json_decode($request->only(['lazyEvent'])['lazyEvent'], true); //only() extract parameters in lazyEvent

            $query = OpenTrade::with([
                    'user:id,name,email,id_number,upline_id,hierarchyList',
                    'trading_account:id,meta_login,account_type_id',
                    'trading_account.account_type:id,name,trading_platform_id,slug,account_group,currency,color',
                    'trading_account.account_type.trading_platform:id,slug'
                ])
                ->whereIn('trade_type', ['buy', 'sell'])
                ->whereIn('user_id', $child_ids)
                ->where('status', 'open');

            // Handle search functionality
            $search = $data['filters']['global'];
            if ($search) {
                $query->where(function ($query) use ($search) {
                    $query->whereHas('user', function ($query) use ($search) {
                        $query->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%')
                            ->orWhere('id_number', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('trading_account.account_type', function ($query) use ($search) {
                        $query->where('name', 'like', '%' . $search . '%')
                            ->orWhere('slug', 'like', '%' . $search . '%')
                            ->orWhere('account_group', 'like', '%' . $search . '%');
                    })
                    ->orWhere('meta_login', 'like', '%' . $search . '%')
                    ->orWhere('trade_deal_id', 'like', '%' . $search . '%');
                });
            }

            $startDate = $data['filters']['start_date'];
            $endDate = $data['filters']['end_date'];

            if ($startDate && $endDate) {
                $start_date = Carbon::parse($startDate)->addDay()->startOfDay();
                $end_date = Carbon::parse($endDate)->addDay()->endOfDay();

                $query->whereBetween('trade_open_time', [$start_date, $end_date]);
            }

            if ($data['filters']['symbol']) {
                $query->where('trade_symbol', $data['filters']['symbol']);
            }

            if ($data['filters']['trade_type']) {
                $query->where('trade_type', $data['filters']['trade_type']);
            }

            if ($data['filters']['account_currency']) {
                $query->whereHas('trading_account.account_type', function ($q) use ($data) {
                    $q->where('currency', $data['filters']['account_currency']);
                });
            }

            // Handle sorting
            if ($data['sortField'] && $data['sortOrder']) {
                $order = $data['sortOrder'] == 1 ? 'asc' : 'desc';
                $query->orderBy($data['sortField'], $order);
            } else {
                $query->orderByDesc('trade_open_time')->orderByDesc('id');
            }

            // Handle pagination
            $rowsPerPage = $data['rows'] ?? 15; // Default to 15 if 'rows' not provided

            // Export logic
            if ($request->has('exportStatus') && $request->exportStatus) {
                $records = $query->get();

                return Excel::download(new OpenTradesExport($records), now() . '-open-trade-report.xlsx');
            }

            $totalLots = (clone $query)->sum('trade_lots');
            $totalCommission = (clone $query)->sum('trade_commission');
            $totalSwap = (clone $query)->sum('trade_swap_usd');
            $totalProfit = (clone $query)->sum('trade_profit_usd');

            $openTrades = $query->paginate($rowsPerPage);

            foreach ($openTrades as $openTrade) {
                // Flatten user-related fields if user exists
                $maskEmail = fn($email) => substr($email, 0, 2) . '*******' . strstr($email, '@');

                if ($openTrade->user) {
                    $openTrade->name = $openTrade->user->name ?? null;
                    $openTrade->email = $openTrade->user->email ?? null;
                    if ($this->calculateLevel($openTrade->user->hierarchyList) > 1 && $openTrade->email) {
                        $openTrade->email = $maskEmail($openTrade->email);
                    }

                    $openTrade->id_number = $openTrade->user->id_number ?? null;
                }

                // Flatten trading_account-related fields if trading_account exists
                if ($openTrade->trading_account) {
                    $accountType = $openTrade->trading_account->account_type;
                    if ($accountType) {
                        $openTrade->account_type_name = $accountType->name ?? null;
                        $openTrade->account_type_slug = $accountType->slug ?? null;
                        $openTrade->account_type_currency = $accountType->currency ?? null;
                        $openTrade->account_type_color = $accountType->color ?? null;
                        $openTrade->trading_platform_name = $openTrade->trading_account->account_type->trading_platform->slug ?? null;
                    }
                }

                // Remove unnecessary nested relationships to keep data clean
                unset($openTrade->user);
                unset($openTrade->trading_account);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $openTrades,
            'totalLots' => $totalLots,
            'totalCommission' => $totalCommission,
            'totalSwap' => $totalSwap,
            'totalProfit' => $totalProfit,
        ]);

    }

    public function closed_positions()
    {
        return Inertia::render('TradePosition/ClosedPositions', [
            'uplines' => (new DropdownOptionService())->getRebateUplines(),
            'symbols' => (new GeneralController())->getSymbols(true),
        ]);
    }

    public function closed_trade(Request $request)
    {

        if ($request->has('lazyEvent')) {
            $user = Auth::user();

            if ($user->role == 'ib') {
                $child_ids = $user->getChildrenIds();
                $child_ids[] = $user->id;
            } else {
                $child_ids = [$user->id];
            }

            $data = json_decode($request->only(['lazyEvent'])['lazyEvent'], true); //only() extract parameters in lazyEvent

            $query = TradeBrokerHistory::with([
                    'user:id,name,email,id_number,upline_id,hierarchyList',
                    'trading_account:id,meta_login,account_type_id',
                    'trading_account.account_type:id,name,trading_platform_id,slug,account_group,currency,color',
                    'trading_account.account_type.trading_platform:id,slug'
                ])
                ->whereIn('user_id', $child_ids);

            // Handle search functionality
            $search = $data['filters']['global'];
            if ($search) {
                $query->where(function ($query) use ($search) {
                    $query->whereHas('user', function ($query) use ($search) {
                        $query->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%')
                            ->orWhere('id_number', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('trading_account.account_type', function ($query) use ($search) {
                        $query->where('name', 'like', '%' . $search . '%')
                            ->orWhere('slug', 'like', '%' . $search . '%')
                            ->orWhere('account_group', 'like', '%' . $search . '%');
                    })
                    ->orWhere('meta_login', 'like', '%' . $search . '%')
                    ->orWhere('trade_deal_id', 'like', '%' . $search . '%');
                });
            }

            $startDate = $data['filters']['start_date'];
            $endDate = $data['filters']['end_date'];

            if ($startDate && $endDate) {
                $start_date = Carbon::parse($startDate)->addDay()->startOfDay();
                $end_date = Carbon::parse($endDate)->addDay()->endOfDay();

                $query->whereBetween('trade_open_time', [$start_date, $end_date]);
            }

            $startClosedDate = $data['filters']['start_close_date'];
            $endClosedDate = $data['filters']['end_close_date'];

            if ($startClosedDate && $endClosedDate) {
                $start_close_date = Carbon::parse($startClosedDate)->addDay()->startOfDay();
                $end_close_date = Carbon::parse($endClosedDate)->addDay()->endOfDay();

                $query->whereBetween('trade_close_time', [$start_close_date, $end_close_date]);
            }

            if ($data['filters']['symbol']) {
                $query->where('trade_symbol', $data['filters']['symbol']);
            }

            if ($data['filters']['trade_type']) {
                $query->where('trade_type', $data['filters']['trade_type']);
            }

            if ($data['filters']['account_currency']) {
                $query->whereHas('trading_account.account_type', function ($q) use ($data) {
                    $q->where('currency', $data['filters']['account_currency']);
                });
            }

            // Handle sorting
            if ($data['sortField'] && $data['sortOrder']) {
                $order = $data['sortOrder'] == 1 ? 'asc' : 'desc';
                $query->orderBy($data['sortField'], $order);
            } else {
                $query->orderByDesc('trade_open_time')->orderByDesc('id');
            }

            // Handle pagination
            $rowsPerPage = $data['rows'] ?? 15; // Default to 15 if 'rows' not provided

            // Export logic
            if ($request->has('exportStatus') && $request->exportStatus) {
                $records = $query->get();

                return Excel::download(new CloseTradesExport($records), now() . '-close-trade-report.xlsx');
            }

            $totalLots = (clone $query)->sum('trade_lots');
            $totalCommission = (clone $query)->sum('trade_commission');
            $totalSwap = (clone $query)->sum('trade_swap_usd');
            $totalProfit = (clone $query)->sum('trade_profit_usd');

            $closeTrades = $query->paginate($rowsPerPage);

            foreach ($closeTrades as $closeTrade) {
                // Flatten user-related fields if user exists
                if ($closeTrade->user) {
                    $maskEmail = fn($email) => substr($email, 0, 2) . '*******' . strstr($email, '@');

                    $closeTrade->name = $closeTrade->user->name ?? null;
                    $closeTrade->email = $closeTrade->user->email ?? null;

                    if ($this->calculateLevel($closeTrade->user->hierarchyList) > 1 && $closeTrade->email) {
                        $closeTrade->email = $maskEmail($closeTrade->email);
                    }

                    $closeTrade->id_number = $closeTrade->user->id_number ?? null;
                }

                // Flatten trading_account-related fields if trading_account exists
                if ($closeTrade->trading_account) {
                    $accountType = $closeTrade->trading_account->account_type;
                    if ($accountType) {
                        $closeTrade->account_type_name = $accountType->name ?? null;
                        $closeTrade->account_type_slug = $accountType->slug ?? null;
                        $closeTrade->account_type_currency = $accountType->currency ?? null;
                        $closeTrade->account_type_color = $accountType->color ?? null;
                        $closeTrade->trading_platform_name = $closeTrade->trading_account->account_type->trading_platform->slug ?? null;
                    }
                }

                // Remove unnecessary nested relationships to keep data clean
                unset($closeTrade->user);
                unset($closeTrade->trading_account);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $closeTrades,
            'totalLots' => $totalLots,
            'totalCommission' => $totalCommission,
            'totalSwap' => $totalSwap,
            'totalProfit' => $totalProfit,
        ]);

    }

    private function calculateLevel($hierarchyList)
    {
        if (is_null($hierarchyList) || $hierarchyList === '') {
            return 0;
        }

        $split = explode('-'.Auth::id().'-', $hierarchyList);

        if (!isset($split[1])) {
            return 1;
        }

        return substr_count($split[1], '-') + 1;
    }
}
