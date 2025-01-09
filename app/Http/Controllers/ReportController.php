<?php

namespace App\Http\Controllers;

use App\Models\TradeRebateHistory;
use Inertia\Inertia;
use App\Models\SymbolGroup;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\RebateAllocation;
use App\Models\TradeRebateSummary;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function index()
    {
        return Inertia::render('Report/Report');
    }

    public function getRebateSummary(Request $request)
    {
        $userId = Auth::id();

        // Retrieve date parameters from request
        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');

        // Initialize query for rebate summary with date filtering
        $query = TradeRebateSummary::with('symbolGroup')
            ->where('upline_user_id', $userId);

        // Apply date filter based on availability of startDate and/or endDate
        if ($startDate && $endDate) {
            // Both startDate and endDate are provided
            $query->whereDate('execute_at', '>=', $startDate)
                ->whereDate('execute_at', '<=', $endDate);
        } else {
            // Both startDate and endDate are null, apply default start date
            $query->whereDate('execute_at', '>=', '2024-01-01');
        }

        // Fetch rebate summary data
        $rebateSummary = $query->get(['symbol_group', 'volume', 'rebate']);

        // Retrieve all symbol groups with non-null display values
        $symbolGroups = SymbolGroup::whereNotNull('display')->pluck('display', 'id');

        // Aggregate rebate data in PHP
        $rebateSummaryData = $rebateSummary->groupBy('symbol_group')->map(function ($items) {
            return [
                'volume' => $items->sum('volume'),
                'rebate' => $items->sum('rebate'),
            ];
        });

        // Initialize final summary and totals
        $finalSummary = [];
        $totalVolume = 0;
        $totalRebate = 0;

        // Iterate over all symbol groups
        foreach ($symbolGroups as $id => $display) {
            // Retrieve data or use default values
            $data = $rebateSummaryData->get($id, ['volume' => 0, 'rebate' => 0]);

            // Add to the final summary
            $finalSummary[] = [
                'symbol_group' => $display,
                'volume' => $data['volume'],
                'rebate' => $data['rebate'],
            ];

            // Accumulate totals
            $totalVolume += $data['volume'];
            $totalRebate += $data['rebate'];
        }

        // Return the response with rebate summary, total volume, and total rebate
        return response()->json([
            'rebateSummary' => $finalSummary,
            'totalVolume' => $totalVolume,
            'totalRebate' => $totalRebate,
        ]);
    }

    public function getRebateListing(Request $request)
    {
        // Retrieve query parameters
        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');

        // Fetch all symbol groups from the database, ordered by the primary key (id)
        $allSymbolGroups = SymbolGroup::pluck('display', 'id')->toArray();

        $query = TradeRebateSummary::with('user')
            ->where('upline_user_id', Auth::id());

        // Apply date filter based on availability of startDate and/or endDate
        if ($startDate && $endDate) {
            $query->whereDate('execute_at', '>=', $startDate)
                  ->whereDate('execute_at', '<=', $endDate);
        } else {
            $query->whereDate('execute_at', '>=', '2024-01-01');
        }

        // Fetch rebate listing data
        $data = $query->get()->map(function ($item) {
            return [
                'user_id' => $item->user_id,
                'name' => $item->user->name,
                'email' => $item->user->email,
                'meta_login' => $item->meta_login,
                'execute_at' => Carbon::parse($item->execute_at)->format('Y/m/d'),
                'symbol_group' => $item->symbol_group,
                'volume' => $item->volume,
                'net_rebate' => $item->net_rebate,
                'rebate' => $item->rebate,
            ];
        });

        // Group data by user_id and meta_login
        $rebateListing = $data->groupBy(function ($item) {
            return $item['user_id'] . '-' . $item['meta_login'];
        })->map(function ($group) use ($allSymbolGroups) {
            $group = collect($group);

            // Calculate overall volume and rebate for the user
            $volume = $group->sum('volume');
            $rebate = $group->sum('rebate');

            // Create summary by execute_at
            $summary = $group->groupBy('execute_at')->map(function ($executeGroup) use ($allSymbolGroups) {
                $executeGroup = collect($executeGroup);

                // Calculate details for each symbol group
                $details = $executeGroup->groupBy('symbol_group')->map(function ($symbolGroupItems) use ($allSymbolGroups) {
                    $symbolGroupId = $symbolGroupItems->first()['symbol_group'];

                    return [
                        'id' => $symbolGroupId,
                        'name' => $allSymbolGroups[$symbolGroupId] ?? 'Unknown',
                        'volume' => $symbolGroupItems->sum('volume'),
                        'net_rebate' => $symbolGroupItems->first()['net_rebate'] ?? 0,
                        'rebate' => $symbolGroupItems->sum('rebate'),
                    ];
                })->values();

                // Add missing symbol groups with volume, net_rebate, and rebate as 0
                foreach ($allSymbolGroups as $symbolGroupId => $symbolGroupName) {
                    if (!$details->pluck('id')->contains($symbolGroupId)) {
                        $details->push([
                            'id' => $symbolGroupId,
                            'name' => $symbolGroupName,
                            'volume' => 0,
                            'net_rebate' => 0,
                            'rebate' => 0,
                        ]);
                    }
                }

                // Sort the symbol group details array to match the order of symbol groups
                $details = $details->sortBy('id')->values();

                return [
                    'execute_at' => $executeGroup->first()['execute_at'],
                    'volume' => $executeGroup->sum('volume'),
                    'rebate' => $executeGroup->sum('rebate'),
                    'details' => $details,
                ];
            })->values();

            // Return rebateListing item with summaries included
            return [
                'user_id' => $group->first()['user_id'],
                'name' => $group->first()['name'],
                'email' => $group->first()['email'],
                'meta_login' => $group->first()['meta_login'],
                'volume' => $volume,
                'rebate' => $rebate,
                'summary' => $summary,
            ];
        })->values();

        // Return JSON response with combined rebateListing and details
        return response()->json([
            'rebateListing' => $rebateListing
        ]);
    }

    public function getGroupTransaction(Request $request)
    {
        $user = Auth::user();
        $groupIds = $user->getChildrenIds();
        $groupIds[] = $user->id;

        $transactionType = $request->query('type');
        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');

        $transactionTypes = match($transactionType) {
            'deposit' => ['deposit', 'balance_in'],
            'withdrawal' => ['withdrawal', 'balance_out'],
            default => []
        };

        // Initialize the query for transactions
        $query = Transaction::whereIn('transaction_type', $transactionTypes)
            ->where('status', 'successful')
            ->whereIn('user_id', $groupIds);

        // Apply date filter based on availability of startDate and/or endDate
        if ($startDate && $endDate) {
            $query->whereDate('created_at', '>=', $startDate)
                  ->whereDate('created_at', '<=', $endDate);
        } else {
            // Handle cases where startDate or endDate are not provided
            $query->whereDate('created_at', '>=', '2024-01-01'); // Default start date
        }

        $transactions = $query->get()
            ->map(function ($transaction) {
                $metaLogin = $transaction->to_meta_login ?: $transaction->from_meta_login;

                // Check for withdrawal type and modify meta_login based on category
                if ($transaction->transaction_type === 'withdrawal') {
                    switch ($transaction->category) {
                        case 'trading_account':
                            $metaLogin = $transaction->from_meta_login;
                            break;
                        case 'rebate_wallet':
                            $metaLogin = 'rebate';
                            break;
                        case 'bonus_wallet':
                            $metaLogin = 'bonus';
                            break;
                    }
                }

                // Return the formatted transaction data
                return [
                    'created_at' => $transaction->created_at,
                    'user_id' => $transaction->user_id,
                    'name' => $transaction->user->name,
                    'email' => $transaction->user->email,
                    'meta_login' => $metaLogin,
                    'transaction_amount' => $transaction->transaction_amount,
                ];
            });

        // Calculate total deposit and withdrawal amounts for the given date range
        $group_total_deposit = Transaction::whereIn('transaction_type', ['deposit', 'balance_in'])
            ->where('status', 'successful')
            ->whereIn('user_id', $groupIds)
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereDate('created_at', '>=', $startDate)
                      ->whereDate('created_at', '<=', $endDate);
            })
            ->sum('transaction_amount');

        $group_total_withdrawal = Transaction::whereIn('transaction_type', ['withdrawal', 'balance_out'])
            ->where('status', 'successful')
            ->whereIn('user_id', $groupIds)
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereDate('created_at', '>=', $startDate)
                      ->whereDate('created_at', '<=', $endDate);
            })
            ->sum('transaction_amount');

        return response()->json([
            'transactions' => $transactions,
            'groupTotalDeposit' => $group_total_deposit,
            'groupTotalWithdrawal' => $group_total_withdrawal,
            'groupTotalNetBalance' => $group_total_deposit - $group_total_withdrawal,
        ]);
    }

    public function getRebateHistory(Request $request)
    {
        if ($request->has('lazyEvent')) {
            $data = json_decode($request->only(['lazyEvent'])['lazyEvent'], true);

            $query = TradeRebateHistory::with([
                'downline',
                'of_account_type'
            ])
                ->where('upline_user_id', Auth::id())
                ->where('t_status', 'approved');

            if ($data['filters']['global']['value']) {
                $keyword = $data['filters']['global']['value'];

                $query->where(function ($q) use ($keyword) {
                    $q->whereHas('downline', function ($query) use ($keyword) {
                        $query->where(function ($q) use ($keyword) {
                            $q->where('name', 'like', '%' . $keyword . '%')
                            ->orWhere('email', 'like', '%' . $keyword . '%');
                        });
                    })->orWhere('meta_login', 'like', '%' . $keyword . '%');
                });
            }

            if (!empty($data['filters']['start_date']['value']) && !empty($data['filters']['end_date']['value'])) {
                $start_date = Carbon::parse($data['filters']['start_date']['value'])->addDay()->startOfDay();
                $end_date = Carbon::parse($data['filters']['end_date']['value'])->addDay()->endOfDay();

                $query->whereBetween('created_at', [$start_date, $end_date]);
            }

            if (!empty($data['filters']['start_close_date']['value']) && !empty($data['filters']['end_close_date']['value'])) {
                $start_close_date = Carbon::parse($data['filters']['start_close_date']['value'])->addDay()->startOfDay();
                $end_close_date = Carbon::parse($data['filters']['end_close_date']['value'])->addDay()->endOfDay();

                $query->whereBetween('closed_time', [$start_close_date, $end_close_date]);
            }

            if ($data['sortField'] && $data['sortOrder']) {
                $order = $data['sortOrder'] == 1 ? 'asc' : 'desc';
                $query->orderBy($data['sortField'], $order);
            } else {
                $query->orderByDesc('id');
            }

            // Export logic
//            if ($request->has('exportStatus') && $request->exportStatus) {
//                return Excel::download(new MemberListingExport($query), now() . '-member-report.xlsx');
//            }
            $totalRebateAmount = (clone $query)->sum('revenue');

            $histories = $query->paginate($data['rows']);

            return response()->json([
                'success' => true,
                'data' => $histories,
                'totalRebateAmount' => $totalRebateAmount,
            ]);
        }

        return response()->json(['success' => false, 'data' => []]);
    }
}
