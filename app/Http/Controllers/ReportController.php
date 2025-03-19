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
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\RebateHistoryExport;
use App\Exports\RebateListingExport;
use App\Exports\GroupTransactionExport;
use App\Services\DropdownOptionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ReportController extends Controller
{
    public function index()
    {
        return Inertia::render('Report/Report', [
            'uplines' => (new DropdownOptionService())->getRebateUplines(),
            'downlines' => (new DropdownOptionService())->getRebateDownlines(),
        ]);
    }

    public function getRebateSummary(Request $request)
    {
        $userId = Auth::id();

        // Retrieve date parameters from request
        $search = $request->query('search');
        $downline_ids = array_filter(explode(',', $request->input('user_id', '')));
        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');
        $group = $request->query('group');

        // Initialize query for rebate summary with date filtering
        $query = TradeRebateSummary::with('user', 'symbolGroup', 'accountType')
            ->where('upline_user_id', $userId);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('id_number', 'like', '%' . $search . '%');
                    });
                })->orWhere('meta_login', 'like', '%' . $search . '%');
            });
        }

        if (!empty($downline_ids)) {
            $users = User::whereIn('id', $downline_ids)->get();

            $allDownlineIds = $users->flatMap(function ($user) {
                return array_merge([$user->id], $user->getChildrenIds());
            })->unique()->toArray();

            $query->whereIn('user_id', $allDownlineIds);
        }

        // Apply date filter based on availability of startDate and/or endDate
        if ($startDate && $endDate) {
            // Both startDate and endDate are provided
            $query->whereDate('execute_at', '>=', $startDate)
                ->whereDate('execute_at', '<=', $endDate);
        } else {
            // Both startDate and endDate are null, apply default start date
            $query->whereDate('execute_at', '>=', '2024-01-01');
        }

        if ($group) {
            $query->whereHas('accountType', function ($q) use ($group) {
                $q->where('category', $group);
            });
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
        $allSymbolGroups = SymbolGroup::pluck('display', 'id')->toArray();

        if ($request->has('lazyEvent')) {
            $data = json_decode($request->only(['lazyEvent'])['lazyEvent'], true);

            $query = TradeRebateSummary::with('user', 'accountType')
            ->where('upline_user_id', Auth::id());

            if ($data['filters']['global']['value']) {
                $keyword = $data['filters']['global']['value'];

                $query->where(function ($q) use ($keyword) {
                    $q->whereHas('user', function ($query) use ($keyword) {
                        $query->where(function ($q) use ($keyword) {
                            $q->where('name', 'like', '%' . $keyword . '%')
                            ->orWhere('email', 'like', '%' . $keyword . '%')
                            ->orWhere('id_number', 'like', '%' . $keyword . '%');
                        });
                    })->orWhere('meta_login', 'like', '%' . $keyword . '%');
                });
            }

            if (!empty($data['filters']['start_date']['value']) && !empty($data['filters']['end_date']['value'])) {
                $start_date = Carbon::parse($data['filters']['start_date']['value'])->addDay()->startOfDay();
                $end_date = Carbon::parse($data['filters']['end_date']['value'])->addDay()->endOfDay();

                $query->whereBetween('execute_at', [$start_date, $end_date]);
            }

            if (!empty($data['filters']['group']['value'])) {
                $group = $data['filters']['group']['value'];

                $query->whereHas('accountType', function ($q) use ($group) {
                    $q->where('category', $group);
                });
            }

            if (!empty($data['filters']['downline_id']['value'])) {
                $downlineIds = $data['filters']['downline_id']['value'];

                $users = User::whereIn('id', $downlineIds)->get();

                $allDownlineIds = $users->flatMap(function ($user) {
                    return array_merge([$user->id], $user->getChildrenIds());
                })->unique()->toArray();

                $query->whereIn('user_id', $allDownlineIds);
                // $query->whereIn('user_id', $downlineIds);
            }

            if ($data['sortField'] && $data['sortOrder']) {
                $order = $data['sortOrder'] == 1 ? 'asc' : 'desc';

                // Check if sorting by a related field (like "name")
                if (in_array($data['sortField'], ['name', 'email', 'id_number'])) {
                    $query->join('users', 'trade_rebate_summaries.user_id', '=', 'users.id')
                          ->orderBy('users.' . $data['sortField'], $order);
                } else {
                    $query->orderBy($data['sortField'], $order);
                }
            } else {
                $query->orderByDesc('id');
            }

                // Map the listings to include the summary and grouping logic
            $allRecords = $query->get()->map(function ($item) {
                return [
                    'user_id' => $item->user_id,
                    'name' => $item->user->name,
                    'email' => $item->user->email,
                    'id_number' => $item->user->id_number,
                    'meta_login' => $item->meta_login,
                    'execute_at' => Carbon::parse($item->execute_at)->format('Y/m/d'),
                    'symbol_group' => $item->symbol_group,
                    'volume' => $item->volume,
                    'net_rebate' => $item->net_rebate,
                    'rebate' => $item->rebate,
                    'slug' => $item->accountType->slug,
                    'color' => $item->accountType->color,
                ];
            });

            // Group and structure the response data
            $listing = $allRecords->groupBy(function ($item) {
                return $item['user_id'] . '-' . $item['meta_login'];
            })->map(function ($group) use ($allSymbolGroups) {
                $group = collect($group);

                // Calculate overall volume and rebate
                $volume = $group->sum('volume');
                $rebate = $group->sum('rebate');

                // Group data by execution date and summarize
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

                    // Add missing symbol groups as zeroed entries
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

                    // Sort details by symbol group ID
                    $details = $details->sortBy('id')->values();

                    return [
                        'execute_at' => $executeGroup->first()['execute_at'],
                        'volume' => $executeGroup->sum('volume'),
                        'rebate' => $executeGroup->sum('rebate'),
                        'details' => $details,
                    ];
                })->values();

                // Return grouped rebate listing item
                return [
                    'user_id' => $group->first()['user_id'],
                    'name' => $group->first()['name'],
                    'email' => $group->first()['email'],
                    'id_number' => $group->first()['id_number'],
                    'meta_login' => $group->first()['meta_login'],
                    'volume' => $volume,
                    'rebate' => $rebate,
                    'summary' => $summary,
                    'slug' => $group->first()['slug'],
                    'color' => $group->first()['color'],
                ];
            })->values();

            if ($request->has(key: 'exportStatus') && $request->exportStatus == true) {
                return Excel::download(new RebateListingExport($listing), now() . '-rebate-summary.xlsx');
            };

            $page = LengthAwarePaginator::resolveCurrentPage();
            $perPage = $data['rows'] ?? 10;  // Default to 10 rows if not specified
            $paginatedListing = new LengthAwarePaginator(
                $listing->slice(($page - 1) * $perPage, $perPage)->values(),
                $listing->count(),
                $perPage,
                $page,
                ['path' => LengthAwarePaginator::resolveCurrentPath()]
            );

            // Return the final response with lazy loading and pagination
            return response()->json([
                'success' => true,
                'data' => $paginatedListing,
            ]);
        }

        return response()->json(['success' => false, 'data' => []]);
    }

    public function getGroupTransaction(Request $request)
    {
        if ($request->has('lazyEvent')) {
            $user = Auth::user();
            $groupIds = $user->getChildrenIds();
            $groupIds[] = $user->id;

            $data = json_decode($request->only(['lazyEvent'])['lazyEvent'], true);

            $transactionType = $data['filters']['type']['value'];

            $transactionTypes = match($transactionType) {
                'deposit' => ['deposit', 'balance_in'],
                'withdrawal' => ['withdrawal', 'balance_out'],
                default => []
            };

            $query = Transaction::with([
                'to_account',
                'from_account',
                'to_account.account_type',
                'from_account.account_type',
            ])
                ->where('status', 'successful')
                ->whereIn('user_id', $groupIds);

            if ($data['filters']['global']['value']) {
                $keyword = $data['filters']['global']['value'];

                $query->where(function ($q) use ($keyword) {
                    $q->whereHas('user', function ($query) use ($keyword) {
                        $query->where(function ($q) use ($keyword) {
                            $q->where('name', 'like', '%' . $keyword . '%')
                            ->orWhere('email', 'like', '%' . $keyword . '%')
                            ->orWhere('id_number', 'like', '%' . $keyword . '%');
                        });
                    })->orWhere('to_meta_login', 'like', '%' . $keyword . '%');
                });
            }

            if (!empty($data['filters']['start_date']['value']) && !empty($data['filters']['end_date']['value'])) {
                $start_date = Carbon::parse($data['filters']['start_date']['value'])->addDay()->startOfDay();
                $end_date = Carbon::parse($data['filters']['end_date']['value'])->addDay()->endOfDay();

                $query->whereBetween('created_at', [$start_date, $end_date]);
            }

            if (!empty($data['filters']['downline_id']['value'])) {
                $downlineIds = $data['filters']['downline_id']['value'];

                $users = User::whereIn('id', $downlineIds)->get();

                $allDownlineIds = $users->flatMap(function ($user) {
                    return array_merge([$user->id], $user->getChildrenIds());
                })->unique()->toArray();

                $query->whereIn('user_id', $allDownlineIds);
            }

            if (!empty($data['filters']['role']['value'])) {
                $query->whereHas('user', function ($query) use ($data) {
                    $query->where('role', $data['filters']['role']['value']);
                });
            }

            if (isset($data['filters']['amount']['value'][0], $data['filters']['amount']['value'][1])) {
                $minAmount = $data['filters']['amount']['value'][0];
                $maxAmount = $data['filters']['amount']['value'][1];

                $query->whereBetween('amount', [$minAmount, $maxAmount]);
            }

            $group_total_deposit = (clone $query)->whereIn('transaction_type', ['deposit', 'balance_in'])->sum('transaction_amount');
            $group_total_withdrawal = (clone $query)->whereIn('transaction_type', ['withdrawal', 'balance_out'])->sum('transaction_amount');

            $query->whereIn('transaction_type', $transactionTypes);

            if ($data['sortField'] && $data['sortOrder']) {
                $order = $data['sortOrder'] == 1 ? 'asc' : 'desc';
                $query->orderBy($data['sortField'], $order);
            } else {
                $query->orderByDesc('id');
            }

            $exportQuery = clone $query;

            if ($request->has('exportStatus') && $request->exportStatus == true) {
                $transactions = $exportQuery->latest()->get()->map(function ($transaction) {
                    $metaLogin = $transaction->to_meta_login ?: $transaction->from_meta_login;
                    $account_type = $transaction->to_account->account_type->name;

                    if ($transaction->transaction_type === 'withdrawal') {
                        switch ($transaction->category) {
                            case 'trading_account':
                                $metaLogin = $transaction->from_meta_login;
                                $account_type = $transaction->from_account->account_type->name;
                                break;
                            case 'rebate_wallet':
                                $metaLogin = 'rebate';
                                break;
                            case 'bonus_wallet':
                                $metaLogin = 'bonus';
                                break;
                        }
                    }

                    return [
                        'created_at' => $transaction->created_at->format('Y-m-d H:i:s'),  // Format the date
                        'name' => $transaction->user->name,
                        'email' => $transaction->user->email,
                        'id_number' => $transaction->user->id_number,
                        'role' => $transaction->user->role,
                        'meta_login' => $metaLogin,
                        'account_type' => $account_type,
                        'amount' => (float) $transaction->transaction_amount,
                    ];
                });

                return Excel::download(new GroupTransactionExport($transactions), now() . '-group-transaction-report.xlsx');
            }

            $transactions = $query->paginate($data['rows'])->through(function ($transaction) {
                $metaLogin = $transaction->to_meta_login ?: $transaction->from_meta_login;

                if ($transaction->transaction_type === 'withdrawal') {
                    $account_type = '';
                    switch ($transaction->category) {
                        case 'trading_account':
                            $metaLogin = $transaction->from_meta_login;
                            $account_type = $transaction->from_account->account_type;
                            break;
                        case 'rebate_wallet':
                            $metaLogin = 'rebate';
                            $account_type = '';
                            break;
                        case 'bonus_wallet':
                            $metaLogin = 'bonus';
                            $account_type = '';
                            break;
                    }
                } else {
                    $account_type = $transaction->to_account->account_type;
                }

                return [
                    'created_at' => $transaction->created_at,
                    'user_id' => $transaction->user_id,
                    'name' => $transaction->user->name,
                    'email' => $transaction->user->email,
                    'id_number' => $transaction->user->id_number,
                    'role' => $transaction->user->role,
                    'meta_login' => $metaLogin,
                    'account_type' => $account_type,
                    'transaction_amount' => $transaction->transaction_amount,
                    'status' => $transaction->status,
                ];
            });

            return response()->json([
                'success' => true,
                'transactions' => $transactions,
                'groupTotalDeposit' => $group_total_deposit,
                'groupTotalWithdrawal' => $group_total_withdrawal,
                'groupTotalNetBalance' => $group_total_deposit - $group_total_withdrawal,
            ]);
        }

        return response()->json(['success' => false, 'data' => []]);
    }

    public function getRebateHistory(Request $request)
    {
        if ($request->has('lazyEvent')) {
            $data = json_decode($request->only(['lazyEvent'])['lazyEvent'], true);

            $query = TradeRebateHistory::with([
                'upline:id,name,email,id_number',
                'downline:id,name,email,id_number',
                'of_account_type'
            ])
                ->whereIn('upline_user_id', array_merge([Auth::id()], Auth::user()->getChildrenIds()))
                ->where('t_status', 'approved');

            if ($data['filters']['global']['value']) {
                $keyword = $data['filters']['global']['value'];

                $query->where(function ($q) use ($keyword) {
                    $q->whereHas('downline', function ($query) use ($keyword) {
                        $query->where(function ($q) use ($keyword) {
                            $q->where('name', 'like', '%' . $keyword . '%')
                            ->orWhere('email', 'like', '%' . $keyword . '%')
                            ->orWhere('id_number', 'like', '%' . $keyword . '%');
                        });
                    })->orWhere('meta_login', 'like', '%' . $keyword . '%')
                    ->orWhere('deal_id', 'like', '%' . $keyword . '%');
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

            if (!empty($data['filters']['upline_id']['value'])) {
                $uplineIds = $data['filters']['upline_id']['value'];
                Log::debug('Upline IDs:', $uplineIds); // Log the extracted IDs

                $query->whereIn('upline_user_id', $uplineIds);
            }

            if (!empty($data['filters']['t_type']['value'])) {
                $query->where('t_type', $data['filters']['t_type']['value']);
            }

            if ($data['sortField'] && $data['sortOrder']) {
                $order = $data['sortOrder'] == 1 ? 'asc' : 'desc';
                $query->orderBy($data['sortField'], $order);
            } else {
                $query->orderByDesc('id');
            }

            if ($request->has(key: 'exportStatus') && $request->exportStatus == true) {
                $histories = $query->clone();
                return Excel::download(new RebateHistoryExport($histories), now() . '-rebate-report.xlsx');
            }

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
