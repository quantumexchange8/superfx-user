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
        return Inertia::render('RebateAllocate/RebateAllocate');
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

        //level 1 children
        $lv1_agents = User::find(Auth::id())->directChildren()->where('role', 'ib')
            ->get()->map(function($agent) {
                return [
                    'id' => $agent->id,
                    'profile_photo' => $agent->getFirstMediaUrl('profile_photo'),
                    'name' => $agent->name,
                    'hierarchy_list' => $agent->hierarchyList,
                    'upline_id' => $agent->upline_id,
                    'level' => 1,
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

        $downline = $user->directChildren()->where('role', 'ib')->first();

        if ($downline) {
            $downline_rebate = User::find($downline->id)->rebateAllocations()->where('account_type_id', $type_id)->get();

            if (!$downline_rebate->isEmpty()) {
                $rebates += [
                    'downline_forex' => floatval($downline_rebate[0]->amount),
                    'downline_indexes' => floatval($downline_rebate[1]->amount),
                    'downline_commodities' => floatval($downline_rebate[2]->amount),
                    'downline_metals' => floatval($downline_rebate[3]->amount),
                    'downline_cryptocurrency' => floatval($downline_rebate[4]->amount),
                    'downline_shares' => floatval($downline_rebate[5]->amount),
                ];
            }
        }

        return $rebates;
    }

    public function changeAgents(Request $request)
    {
        // dd($request->all());
        $selected_agent_id = $request->id;
        $type_id = $request->type_id;
        $agents_array = [];

        // $selected_ib = User::where('id', $selected_ib_id)->first();

        // // determine is the selected ib other than level 1
        // if ($selected_ib->upline_id !== 2) {
        //     $split_hierarchy = explode('-2-', $selected_ib->hierarchyList);
        //     $upline_ids = explode('-', $split_hierarchy[1]);

        //     array_pop($upline_ids);

        //     $uplines = User::whereIn('id', $upline_ids)->get()
        //         ->map(function($upline) use ($type_id) {
        //             $rebate = $this->getRebateAllocate($upline->id, $type_id);

        //             $same_level_ibs = User::where(['hierarchyList' => $upline->hierarchyList, 'role' => 'ib'])->get()
        //                 ->map(function($same_level_ib) {
        //                     return [
        //                         'id' => $same_level_ib->id,
        //                         'profile_photo' => $same_level_ib->getFirstMediaUrl('profile_photo'),
        //                         'name' => $same_level_ib->name,
        //                         'email' => $same_level_ib->email,
        //                         'hierarchy_list' => $same_level_ib->hierarchyList,
        //                         'upline_id' => $same_level_ib->upline_id,
        //                         'level' => $this->calculateLevel($same_level_ib->hierarchyList),
        //                     ];
        //                 })
        //                 ->sortBy(fn($ib) => $ib['id'] != $upline->id)
        //                 ->toArray();

        //             // reindex
        //             $same_level_ibs = array_values($same_level_ibs);

        //             $data = [];
        //             array_push($data, $same_level_ibs, $rebate);
        //             return $data;
        //         })->toArray();

        //     $ibs_array = $uplines;
        // }

        // // selected ib & same level ibs
        // $ibs = User::where(['hierarchyList' => $selected_ib->hierarchyList, 'role' => 'ib'])->get()
        //     ->map(function($ib) {
        //         return [
        //             'id' => $ib->id,
        //             'profile_photo' => $ib->getFirstMediaUrl('profile_photo'),
        //             'name' => $ib->name,
        //             'email' => $ib->email,
        //             'hierarchy_list' => $ib->hierarchyList,
        //             'upline_id' => $ib->upline_id,
        //             'level' => $this->calculateLevel($ib->hierarchyList),
        //         ];
        //     })
        //     ->sortBy(fn($ib) => $ib['id'] != $selected_ib->id)
        //     ->toArray();

        // // reindex
        // $ibs = array_values($ibs);

        // // selected ib rebate
        // $rebate = $this->getRebateAllocate($selected_ib_id, $type_id);

        // //push selected ib level into array
        // $temp = [];
        // array_push($temp, $ibs, $rebate);
        // $ibs_array[] = $temp;

        // //pass to getDirectibs
        // $loop_flag = true;
        // $current_ib_id = $selected_ib_id;
        // while ($loop_flag) {
        //     $next_level = $this->getDirectIBs($current_ib_id, $type_id);
        //     if ( !empty($next_level) ) {
        //         $current_ib_id = $next_level[0][0]['id'];
        //         $ibs_array[] = $next_level;
        //     } else {
        //         $loop_flag = false;
        //     }
        // }


        if($request->level == 1) {
            $lv1_data = [];

            //level 1 children
            $lv1_agents = User::find(Auth::id())->directChildren()->where('role', 'ib')->get()
                ->map(function($agent) {
                    return [
                        'id' => $agent->id,
                        'profile_photo' => $agent->getFirstMediaUrl('profile_photo'),
                        'name' => $agent->name,
                        'hierarchy_list' => $agent->hierarchyList,
                        'upline_id' => $agent->upline_id,
                        'level' => 1,
                    ];
                })
                ->sortBy(fn($agent) => $agent['id'] != $selected_agent_id)
                ->toArray();

            // reindex
            $lv1_agents = array_values($lv1_agents);

            //level 1 children rebate
            $lv1_rebate = $this->getRebateAllocate($lv1_agents[0]['id'], $type_id);

            array_push($lv1_data, $lv1_agents, $lv1_rebate);
            array_push($agents_array, $lv1_data);

            // children of first level 1 agent
            $children_ids = User::find($lv1_agents[0]['id'])->getChildrenIds();
        // dd($children_ids);
            $agents = User::whereIn('id', $children_ids)->where('role', 'ib')
                ->get()->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'profile_photo' => $user->getFirstMediaUrl('profile_photo'),
                        'name' => $user->name,
                        'hierarchy_list' => $user->hierarchyList,
                        'upline_id' => $user->upline_id,
                        'level' => $this->calculateLevel($user->hierarchyList),
                    ];
                })
                ->groupBy('level')->toArray();
        // dd($agents);
            // push lvl 2 and above agent & rebate into array 
            for ($i = 2; $i <= sizeof($agents) + 1; $i++) {
                $temp = [];
                $rebate = $this->getRebateAllocate($agents[$i][0]['id'], $type_id);

                array_push($temp, $agents[$i], $rebate);
                array_push($agents_array, $temp);
            }
        }
        else {
            // selected agent details
            $agent = User::where('id', $selected_agent_id)->first();

            // find the upper hierarchy of selected agent
            $split_hierarchy = explode('-'.Auth::id().'-', $agent->hierarchyList);
            $upline_ids = explode('-', $split_hierarchy[1]);

            array_push($upline_ids, $selected_agent_id);
            // dd($upline_ids);

            $uplines = User::whereIn('id', $upline_ids)->get()
                ->map(function($upline) use ($type_id) {
                    $rebate = $this->getRebateAllocate($upline->id, $type_id);

                    $same_level_agents = User::where(['hierarchyList' => $upline->hierarchyList, 'role' => 'ib'])->get()
                        ->map(function($same_level_agent) {
                            return [
                                'id' => $same_level_agent->id,
                                'profile_photo' => $same_level_agent->getFirstMediaUrl('profile_photo'),
                                'name' => $same_level_agent->name,
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

            // children of selected agent
            $children_ids = User::where('id', $selected_agent_id)->first()->getChildrenIds();
            if ($children_ids) {
                $agents = User::whereIn('id', $children_ids)->where('role', 'ib')
                ->get()->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'profile_photo' => $user->getFirstMediaUrl('profile_photo'),
                        'name' => $user->name,
                        'hierarchy_list' => $user->hierarchyList,
                        'upline_id' => $user->upline_id,
                        'level' => $this->calculateLevel($user->hierarchyList),
                    ];
                })
                ->groupBy('level')->toArray();

                // reindex
                $agents = array_values($agents);

                // push donward hierarchy agent & rebate into array 
                for ($i = 0; $i < sizeof($agents); $i++) {
                    $temp = [];
                    $rebate = $this->getRebateAllocate($agents[$i][0]['id'], $type_id);

                    array_push($temp, $agents[$i], $rebate);
                    array_push($uplines, $temp);
                }
            }

            $agents_array = $uplines;
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
