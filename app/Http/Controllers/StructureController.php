<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\MetaFourService;
use App\Services\DropdownOptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class StructureController extends Controller
{
    public function show(Request $request)
    {
        $tab_index = 0;
        if($request->tab == 'listing') {
            $tab_index = 1;
        }
        return Inertia::render('Structure/Structure', ['tab' => $tab_index]);
    }

    public function getDownlineData(Request $request)
    {
        $upline_id = $request->upline_id;
        $parent_id = $request->parent_id ?: Auth::id();

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $parent = User::whereIn('role', ['ib', 'member'])
                ->where('id_number', 'LIKE', $search)
                ->orWhere('email', 'LIKE', $search)
                ->first();

            $parent_id = $parent->id;
            $upline_id = $parent->upline_id;
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

        $split = explode('-'.Auth::id().'-', $hierarchyList);
        return substr_count($split[1], '-') + 1;
    }

    private function getChildrenCount($role, $user_id): int
    {
        return User::where('role', $role)
            ->where('hierarchyList', 'like', '%-' . $user_id . '-%')
            ->count();
    }

    public function getDownlineListingData()
    {
        $children_ids = User::find(Auth::id())->getChildrenIds();
        $query = User::whereIn('id', $children_ids)
            ->latest()
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'profile_photo' => $user->getFirstMediaUrl('profile_photo'),
                    'upline_id' => $user->upline_id,
                    'upline_name' => $user->upline->name,
                    'upline_profile_photo' => $user->upline->getFirstMediaUrl('profile_photo'),
                    'role' => $user->role,
                    'id_number' => $user->id_number,
                    'joined_date' => $user->created_at,
                    'level' => $this->calculateLevel($user->hierarchyList),
                ];
            });

        return response()->json([
            'users' => $query
        ]);
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

            if($length > $max_length) {
                $hierarchy_list = $hierarchy;
                $max_length = count($parts);
            }
        }

        return $hierarchy_list;
    }

    public function viewDownline($id_number)
    {
        $user = User::where('id_number', $id_number)->select('id', 'name', 'role')->first();

        return Inertia::render('Structure/ViewDownline', [
            'user' => $user,
        ]);
    }

    public function getUserData(Request $request)
    {
        $user = User::where('id', $request->id)->where('hierarchyList', 'like', '%-' . Auth::id() . '-%')->first();

        $user_data = [
            'id' => $user->id,
            'profile_photo' => $user->getFirstMediaUrl('profile_photo'),
            'name' => $user->name,
            'id_number' => $user->id_number,
            'email' => $user->email,
            'dial_code' => $user->dial_code,
            'phone' => $user->phone,
            'role' => $user->role,
            'upline_profile_photo' => $user->upline->getFirstMediaUrl('profile_photo'),
            'upline_name' => $user->upline->name,
        ];

        $trading_accounts = $user->tradingAccounts;
        try {
            foreach ($trading_accounts as $trading_account) {
                (new MetaFourService)->getUserInfo($trading_account->meta_login);
            }
        } catch (\Throwable $e) {
            Log::error($e->getMessage());
        }

        $trading_accounts = $user->tradingAccounts->map(function($trading_account) {
            return [
                'id' => $trading_account->id,
                'meta_login' => $trading_account->meta_login,
                'account_type' => $trading_account->account_type->name,
                'balance' => $trading_account->balance,
                'credit' => $trading_account->credit,
                'equity' => $trading_account->equity,
                'account_type_color' => $trading_account->account_type->color,
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
