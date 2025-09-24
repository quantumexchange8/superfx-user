<?php

namespace App\Http\Controllers;

use App\Exports\StructureListingExport;
use App\Models\TradingAccount;
use App\Models\TradingPlatform;
use App\Models\User;
use App\Services\DropdownOptionService;
use App\Services\TradingPlatform\TradingPlatformFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Exception;
use Throwable;

class StructureController extends Controller
{
    public function show(Request $request)
    {
        $tab_index = 0;
        if ($request->tab == 'listing') {
            $tab_index = 1;
        }

        return Inertia::render('Structure/Structure', [
            'tab' => $tab_index,
            'maxLevels' => $this->calculateLevel($this->getMaxHierarchy()),
        ]);
    }

    public function getDownlineData(Request $request)
    {
        $upline_id = $request->upline_id;
        $parent_id = $request->parent_id ?: Auth::id();

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';

            $authId = Auth::id();
            $needle = "%-$authId-%";

            $parent = User::whereIn('role', ['ib', 'member'])
                ->where(function ($query) use ($search) {
                    $query->where('id_number', 'LIKE', $search)
                        ->orWhere('email', 'LIKE', $search);
                })
                ->where('hierarchyList', 'LIKE', $needle)
                ->first();

            if ($parent) {
                $parent_id = $parent->id;
                $upline_id = $parent->upline_id;
            }
        }

        $parent = User::with(['directChildren:id,name,id_number,upline_id,role,hierarchyList'])
            ->whereIn('role', ['ib', 'member'])
            ->select('id', 'name', 'id_number', 'upline_id', 'role', 'hierarchyList')
            ->find($parent_id);

        $upline = $upline_id && $upline_id != Auth::user()->upline_id ? User::select('id', 'name', 'id_number', 'upline_id', 'role', 'hierarchyList')->find($upline_id) : null;

        $parent_data = $this->formatUserData($parent);
        $upline_data = $upline ? $this->formatUserData($upline) : null;

        $direct_children = $parent->directChildren->map(function ($child) {
            return $this->formatUserData($child);
        });

        return response()->json([
            'upline' => $upline_data,
            'parent' => $parent_data,
            'direct_children' => $direct_children,
        ]);
    }

    private function formatUserData($user)
    {
        if ($user->upline) {
            $upper_upline = $user->upline->upline;
        }

        return array_merge(
            $user->only(['id', 'name', 'id_number', 'upline_id', 'role']),
            [
                'profile_photo' => $user->getFirstMediaUrl('profile_photo'),
                'upper_upline_id' => $upper_upline->id ?? null,
                'level' => $user->id === Auth::id() ? 0 : $this->calculateLevel($user->hierarchyList),
                'total_agent_count' => $this->getChildrenCount('ib', $user->id),
                'total_member_count' => $this->getChildrenCount('member', $user->id),
            ]
        );
    }

    private function calculateLevel($hierarchyList)
    {
        if (is_null($hierarchyList) || $hierarchyList === '') {
            return 0;
        }

        $split = explode('-' . Auth::id() . '-', $hierarchyList);

        if (!isset($split[1])) {
            return 1;
        }

        return substr_count($split[1], '-') + 1;
    }

    private function getChildrenCount($role, $user_id): int
    {
        return User::where('role', $role)
            ->where('hierarchyList', 'like', '%-' . $user_id . '-%')
            ->count();
    }

    /**
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function getDownlineListingData(Request $request)
    {
        if ($request->has('lazyEvent')) {
            $data = json_decode($request->input('lazyEvent'), true);

            $user = Auth::user();
            $userIds = $user->getChildrenIds();
            $userIds[] = $user->id;

            $authId = Auth::id();

            $query = User::query()
                ->with([
                    'upline:id,name,email',
                    'upline.media' => function ($q) {
                        $q->where('collection_name', 'profile_photo');
                    },
                    'media' => function ($q) {
                        $q->where('collection_name', 'profile_photo');
                    },
                ])
                ->whereIn('id', $userIds);

            /** ────── Filters ────── */
            // Global search
            if (!empty($data['filters']['global']['value'])) {
                $keyword = $data['filters']['global']['value'];
                $query->where(function ($query) use ($keyword) {
                    $query->where('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%")
                        ->orWhere('id_number', 'like', "%{$keyword}%");
                });
            }

            // Role filter
            if (!empty($data['filters']['role']['value'])) {
                $query->where('role', $data['filters']['role']['value']);
            }

            // Level filter (calculate level in SQL)
            $selectColumns = [
                'users.id', 'users.name', 'users.email', 'users.hierarchyList',
                'users.role', 'users.upline_id', 'users.created_at', 'users.id_number', 'users.phone'
            ];

            $levelSelect = "CASE
        WHEN hierarchyList IS NULL OR hierarchyList = '' THEN 0
        WHEN LOCATE(CONCAT('-', {$authId} ,'-'), hierarchyList) = 0 THEN 1
        ELSE
            LENGTH(SUBSTRING_INDEX(hierarchyList, CONCAT('-', {$authId} ,'-'), -1))
            - LENGTH(REPLACE(SUBSTRING_INDEX(hierarchyList, CONCAT('-', {$authId} ,'-'), -1), '-', ''))
            + 1
    END as level";

            $query->selectRaw(implode(',', $selectColumns) . ', ' . $levelSelect);

            if (!empty($data['filters']['level']['value'])) {
                $query->having('level', $data['filters']['level']['value']);
            }

            // Upline filter
            if (!empty($data['filters']['upline']['value'])) {
                $query->where('upline_id', $data['filters']['upline']['value']['id']);
            }

            /** ────── Sort ────── */
            if (!empty($data['sortField']) && !empty($data['sortOrder'])) {
                $order = $data['sortOrder'] == 1 ? 'asc' : 'desc';
                $query->orderBy($data['sortField'], $order);
            } else {
                $query->orderByDesc('created_at');
            }

            /** ────── Export ────── */
            if ($request->has('exportStatus')) {
                $accounts = $query->get();

                // ensure level for Auth::id()
                $accounts->each(function ($user) {
                    if ($user->id == Auth::id()) {
                        $user->level = 1;
                    }
                });

                return Excel::download(
                    new StructureListingExport($accounts),
                    now()->format('Y-m-d') . '-structure-listing.xlsx'
                );
            }

            /** ────── Pagination ────── */
            $accounts = $query->paginate($data['rows']);

            $accounts->each(function ($user) {
                // Make sure level 1 for auth user
                if ($user->id == Auth::id()) {
                    $user->level = 1;
                }

                // Mask sensitive data for level >= 2
                if ($user->level >= 2) {
                    $user->email = substr($user->email, 0, 2) . '*******' . strstr($user->email, '@');

                    if (!empty($user->phone)) {
                        $last3 = substr($user->phone, -3);
                        $user->phone = '*******' . $last3;
                    }
                }
            });

            return response()->json([
                'success' => true,
                'data' => $accounts,
            ]);
        }

        return response()->json(['success' => false, 'data' => []]);
    }

    public function getFilterData()
    {
        return response()->json([
            'uplines' => (new DropdownOptionService())->getUplines(),
            'maxLevel' => $this->calculateLevel($this->getMaxHierarchy()),
        ]);
    }

    private function getMaxHierarchy()
    {
        $children_ids = User::find(Auth::id())->getChildrenIds();
        $hierarchies = User::whereIn('id', $children_ids)->get()->pluck('hierarchyList');
        $hierarchy_list = '';
        $max_length = 0;

        foreach ($hierarchies as $hierarchy) {
            $parts = explode('-', trim($hierarchy, '-'));
            $length = count($parts);

            if ($length > $max_length) {
                $hierarchy_list = $hierarchy;
                $max_length = count($parts);
            }
        }

        return $hierarchy_list;
    }

    public function viewDownline($id_number)
    {
        $user = User::where('id_number', $id_number)->select('id', 'name', 'role')->first();

        $tradingPlatforms = TradingPlatform::query()
            ->where('status', 'active')
            ->whereHas('account_types.markupProfileToAccountTypes.markupProfile.userToMarkupProfiles', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->select([
                'platform_name',
                'slug',
            ])
            ->get()
            ->toArray();

        return Inertia::render('Structure/ViewDownline', [
            'user' => $user,
            'tradingPlatforms' => $tradingPlatforms,
        ]);
    }

    public function getUserData(Request $request)
    {
        $user = User::where('id', $request->id)->where('hierarchyList', 'like', '%-' . Auth::id() . '-%')->first();

        $level = $this->calculateLevel($user->hierarchyList);

        $email = $user->email;
        $phone = $user->phone;

        if ($level > 1) {
            $email = substr($email, 0, 2) . '*******' . strstr($email, '@');
            $last3 = substr($phone, -3);
            $phone = '*******' . $last3;
        }

        $user_data = [
            'id' => $user->id,
            'profile_photo' => $user->getFirstMediaUrl('profile_photo'),
            'name' => $user->name,
            'id_number' => $user->id_number,
            'email' => $email,
            'kyc_status' => $user->kyc_status,
            'dial_code' => $user->dial_code,
            'phone' => $phone,
            'role' => $user->role,
            'upline_profile_photo' => $user->upline->getFirstMediaUrl('profile_photo'),
            'upline_name' => $user->upline->name,
        ];

        $trading_accounts = TradingAccount::with([
            'account_type:id,trading_platform_id,color,name',
            'account_type.trading_platform:id,slug',
        ])
            ->where('user_id', $user->id)
            ->get();

        try {
            foreach ($trading_accounts as $trading_account) {
                $service = TradingPlatformFactory::make($trading_account->account_type->trading_platform->slug);

                $service->getUserInfo($trading_account->meta_login);
            }
        } catch (Throwable $e) {
            Log::error($e->getMessage());
        }

        $trading_accounts = $user->tradingAccounts->map(function ($trading_account) {
            return [
                'id' => $trading_account->id,
                'meta_login' => $trading_account->meta_login,
                'account_type' => $trading_account->account_type->name,
                'balance' => $trading_account->balance,
                'credit' => $trading_account->credit,
                'equity' => $trading_account->equity,
                'account_type_color' => $trading_account->account_type->color,
                'trading_platform' => $trading_account->account_type->trading_platform->slug,
            ];
        });

        $deposit_amount = $user->transactions()
            ->where('category', 'trading_account')
            ->where('transaction_type', 'deposit')
            ->where('status', 'successful')
            ->sum('transaction_amount');

        $withdrawal_amount = $user->transactions()
            ->where('transaction_type', 'withdrawal')
            ->where('status', 'successful')
            ->sum('transaction_amount');

        return response()->json([
            'userDetail' => $user_data,
            'tradingAccounts' => $trading_accounts,
            'depositAmount' => floatval($deposit_amount),
            'withdrawalAmount' => floatval($withdrawal_amount),
            'memberAmount' => $user->directChildren()->where('role', 'member')->count(),
            'agentAmount' => $user->directChildren()->where('role', 'ib')->count(),
        ]);
    }
}
