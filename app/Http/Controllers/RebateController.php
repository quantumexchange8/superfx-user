<?php

namespace App\Http\Controllers;

use App\Models\RebateAllocation;
use App\Models\User;
use Auth;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;

class RebateController extends Controller
{
    public function index()
    {
        if (Auth::user()->role != 'ib') {
            return redirect()->route('dashboard')->with('toast', [
                'title' => trans('public.access_denied'),
                'type' => 'warning'
            ]);
        }

        return Inertia::render('RebateAllocate/RebateAllocate', [
            'accountTypes' => (new GeneralController())->getAccountTypes(true),
        ]);
    }

    public function getRebateAllocateData(Request $request)
    {
        $rebate_data = RebateAllocation::with('symbol_group:id,display')
            ->where('user_id', Auth::id())
            ->where('account_type_id', $request->account_type_id)
            ->get();

        return response()->json([
            'rebate_data' => $rebate_data
        ]);
    }

    public function getAgents(Request $request)
    {
        $type_id = $request->type_id;
        $search = $request->search;

        $query = User::where('role', 'ib')->where('hierarchyList', 'like', '%-' . Auth::id() . '-%');

        // If there is no search term, filter by upline_id
        if (empty($search)) {
            $query->where('upline_id', Auth::id());  // Only apply upline_id filter if no search term
        } else {
            // If there is a search term, search by name or email
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('id_number', 'like', "%$search%");
            });
        }

        //level 1 children
        $lv1_agents = $query->get()->map(function($agent) use ($search) {
            $level = $search ? $this->calculateLevel($agent->hierarchyList) : 1;

                return [
                    'id' => $agent->id,
                    'profile_photo' => $agent->getFirstMediaUrl('profile_photo'),
                    'name' => $agent->name,
                    'hierarchy_list' => $agent->hierarchyList,
                    'upline_id' => $agent->upline_id,
                    'level' => $level,
                ];
        })->toArray();

        $agents_array = [];
        $lv1_data = [];

        // Check if there are any ibs found
        if (!empty($lv1_agents)) {
            // Get level 1 children rebate only if ibs are found
            $lv1_rebate = $this->getRebateAllocate($lv1_agents[0]['id'], $type_id);

            // Push level 1 ib & rebate data
            array_push($lv1_data, $lv1_agents, $lv1_rebate);
            $agents_array[] = $lv1_data;

            // Get direct ibs of the first upline
            $loop_flag = true;
            $current_agent_id = $lv1_agents[0]['id'];
            while ($loop_flag) {
                $next_level = $this->getDirectAgents($current_agent_id, $type_id);

                // If next level ibs are found, continue the loop
                if (!empty($next_level) && isset($next_level[0][0]['id'])) {
                    $current_agent_id = $next_level[0][0]['id'];
                    $agents_array[] = $next_level;
                } else {
                    $loop_flag = false; // Stop looping if no more ibs
                }
            }
        }

        return response()->json($agents_array);
    }

    private function getDirectAgents($agent_id, $type_id)
    {
        // children of id passed in
        $children = User::find($agent_id)->directChildren()->where('role', 'ib')->select('id', 'hierarchyList')->get();

        // find children same level
        if ( $children->isNotEmpty() ) {
            $agents = User::where(['hierarchyList' => $children[0]->hierarchyList, 'role' => 'ib'])->get()
                ->map(function ($agent) {
                    return [
                        'id' => $agent->id,
                        'profile_photo' => $agent->getFirstMediaUrl('profile_photo'),
                        'name' => $agent->name,
                        'email' => $agent->email,
                        'hierarchy_list' => $agent->hierarchyList,
                        'upline_id' => $agent->upline_id,
                        'level' => $this->calculateLevel($agent->hierarchyList),
                    ];
                })
                ->sortBy(fn($agent) => $agent['id'] != $children[0]->id)
                ->toArray();

            // reindex
            $agents = array_values($agents);

            // push current level hierarchy ib & rebate into array
            $temp = [];
            $rebate = $this->getRebateAllocate($agents[0]['id'], $type_id);

            array_push($temp, $agents, $rebate);

            return $temp;
        }

        return '';
    }

    private function calculateLevel($hierarchyList)
    {
        if (is_null($hierarchyList) || $hierarchyList === '') {
            return 0;
        }

        $split = explode('-'.Auth::id().'-', $hierarchyList);
        return substr_count($split[1], '-') + 1;
    }

    private function getRebateAllocate($user_id, $type_id)
    {
        $user = User::find($user_id);
        $rebate = $user->rebateAllocations()->where('account_type_id', $type_id)->get();
        $upline_rebate = User::find($user->upline_id)->rebateAllocations()->where('account_type_id', $type_id)->get();

        $rebates = [
            'user_id' => $rebate[0]->user_id,
            'account_type_id' => $type_id,
            $rebate[0]->symbol_group_id => floatval($rebate[0]->amount),
            $rebate[1]->symbol_group_id => floatval($rebate[1]->amount),
            $rebate[2]->symbol_group_id => floatval($rebate[2]->amount),
            $rebate[3]->symbol_group_id => floatval($rebate[3]->amount),
            $rebate[4]->symbol_group_id => floatval($rebate[4]->amount),
            $rebate[5]->symbol_group_id => floatval($rebate[5]->amount),
            'upline_forex' => floatval($upline_rebate[0]->amount),
            'upline_indexes' => floatval($upline_rebate[1]->amount),
            'upline_commodities' => floatval($upline_rebate[2]->amount),
            'upline_metals' => floatval($upline_rebate[3]->amount),
            'upline_cryptocurrency' => floatval($upline_rebate[4]->amount),
            'upline_shares' => floatval($upline_rebate[5]->amount),
        ];

        $downlines = $user->directChildren()->where('role', 'ib')->get();

        if ($downlines->isNotEmpty()) {
            $rebateCategories = [
                'downline_forex' => null,
                'downline_indexes' => null,
                'downline_commodities' => null,
                'downline_metals' => null,
                'downline_cryptocurrency' => null,
                'downline_shares' => null,
            ];

            foreach ($downlines as $downline) {
                $downline_rebate = $downline->rebateAllocations()->where('account_type_id', $type_id)->get();

                if (!$downline_rebate->isEmpty()) {
                    $rebateCategories['downline_forex'] = is_null($rebateCategories['downline_forex'])
                        ? floatval($downline_rebate[0]->amount)
                        : max($rebateCategories['downline_forex'], floatval($downline_rebate[0]->amount));

                    $rebateCategories['downline_indexes'] = is_null($rebateCategories['downline_indexes'])
                        ? floatval($downline_rebate[1]->amount)
                        : max($rebateCategories['downline_indexes'], floatval($downline_rebate[1]->amount));

                    $rebateCategories['downline_commodities'] = is_null($rebateCategories['downline_commodities'])
                        ? floatval($downline_rebate[2]->amount)
                        : max($rebateCategories['downline_commodities'], floatval($downline_rebate[2]->amount));

                    $rebateCategories['downline_metals'] = is_null($rebateCategories['downline_metals'])
                        ? floatval($downline_rebate[3]->amount)
                        : max($rebateCategories['downline_metals'], floatval($downline_rebate[3]->amount));

                    $rebateCategories['downline_cryptocurrency'] = is_null($rebateCategories['downline_cryptocurrency'])
                        ? floatval($downline_rebate[4]->amount)
                        : max($rebateCategories['downline_cryptocurrency'], floatval($downline_rebate[4]->amount));

                    $rebateCategories['downline_shares'] = is_null($rebateCategories['downline_shares'])
                        ? floatval($downline_rebate[5]->amount)
                        : max($rebateCategories['downline_shares'], floatval($downline_rebate[5]->amount));
                }
            }

            $rebates += $rebateCategories;
        }

        return $rebates;
    }

    public function changeAgents(Request $request)
    {
        // dd($request->all());
        $selected_agent_id = $request->id;
        $type_id = $request->type_id;
        $agents_array = [];

        $selected_agent = User::where('id', $selected_agent_id)->first();

        // determine is the selected ib other than level 1
        if ($selected_agent->upline_id !== Auth::id()) {
            $split_hierarchy = explode('-'.Auth::id().'-', $selected_agent->hierarchyList);
            $upline_ids = explode('-', $split_hierarchy[1]);

            array_pop($upline_ids);

            $uplines = User::whereIn('id', $upline_ids)->get()
                ->map(function($upline) use ($type_id) {
                    $rebate = $this->getRebateAllocate($upline->id, $type_id);

                    $same_level_agents = User::where(['hierarchyList' => $upline->hierarchyList, 'role' => 'ib'])->get()
                        ->map(function($same_level_agent) {
                            return [
                                'id' => $same_level_agent->id,
                                'profile_photo' => $same_level_agent->getFirstMediaUrl('profile_photo'),
                                'name' => $same_level_agent->name,
                                'email' => $same_level_agent->email,
                                'hierarchy_list' => $same_level_agent->hierarchyList,
                                'upline_id' => $same_level_agent->upline_id,
                                'level' => $this->calculateLevel($same_level_agent->hierarchyList),
                            ];
                        })
                        ->sortBy(fn($agent) => $agent['id'] != $upline->id)
                        ->toArray();

                    // reindex
                    $same_level_agents = array_values($same_level_agents);

                    $data = [];
                    array_push($data, $same_level_agents, $rebate);
                    return $data;
                })->toArray();

            $agents_array = $uplines;
        }

        // selected ib & same level ibs
        $agents = User::where(['hierarchyList' => $selected_agent->hierarchyList, 'role' => 'ib'])->get()
            ->map(function($agent) {
                return [
                    'id' => $agent->id,
                    'profile_photo' => $agent->getFirstMediaUrl('profile_photo'),
                    'name' => $agent->name,
                    'email' => $agent->agent,
                    'hierarchy_list' => $agent->hierarchyList,
                    'upline_id' => $agent->upline_id,
                    'level' => $this->calculateLevel($agent->hierarchyList),
                ];
            })
            ->sortBy(fn($agent) => $agent['id'] != $selected_agent->id)
            ->toArray();

        // reindex
        $agents = array_values($agents);

        // selected ib rebate
        $rebate = $this->getRebateAllocate($selected_agent_id, $type_id);

        //push selected ib level into array
        $temp = [];
        array_push($temp, $agents, $rebate);
        $agents_array[] = $temp;

        //pass to getDirectibs
        $loop_flag = true;
        $current_agent_id = $selected_agent_id;
        while ($loop_flag) {
            $next_level = $this->getDirectAgents($selected_agent_id, $type_id);
            if ( !empty($next_level) ) {
                $selected_agent_id = $next_level[0][0]['id'];
                $agents_array[] = $next_level;
            } else {
                $loop_flag = false;
            }
        }

        return response()->json($agents_array);

    }

    public function updateRebateAmount(Request $request)
    {
        $data = $request->rebates;
        \Log::info('Request Data:', $request->all());
        $rules = [];
        $attributeNames = [];

        $uplineFields = [
            '1' => 'upline_forex',
            '2' => 'upline_indexes',
            '3' => 'upline_commodities',
            '4' => 'upline_metals',
            '5' => 'upline_cryptocurrency',
            '6' => 'upline_shares',
        ];

        foreach ($uplineFields as $rebateKey => $uplineKey) {
            if (isset($data[$rebateKey]) && isset($data[$uplineKey])) {
                $rules["rebates.$rebateKey"] = [
                    'required',
                    'numeric',
                    function ($attribute, $value, $fail) use ($data, $uplineKey) {
                        \Log::info("Validating $attribute with value $value against {$data[$uplineKey]}");
                        if ($value > $data[$uplineKey]) {
                            $fail('Exceeds upline’s rebate.');
                        }
                    },
                ];
            }
        }

        $validator = Validator::make($request->all(), $rules);
        \Log::info('Validation Rules:', $rules);
        $validator->validate();

        $rebates = RebateAllocation::where('user_id', $data['user_id'])
            ->where('account_type_id', $data['account_type_id'])
            ->get();

        foreach ($rebates as $rebate) {
            if (isset($data[$rebate->symbol_group_id])) {
                $rebate->amount = $data[$rebate->symbol_group_id];
                $rebate->save();
            }
        }

        return back()->with('toast', [
            'title' => trans('public.toast_update_rebate_success_message'),
            'type' => 'success',
        ]);
    }
}
