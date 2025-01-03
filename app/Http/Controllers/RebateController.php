<?php

namespace App\Http\Controllers;

use App\Models\RebateAllocation;
use App\Models\User;
use Auth;
use Illuminate\Http\Request;
use Inertia\Inertia;

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

        //level 1 children rebate
        $lv1_rebate = $this->getRebateAllocate($lv1_agents[0]['id'], $type_id);

        $agents_array = [];
        $lv1_data = [];

        // push lvl 1 agent & rebate
        array_push($lv1_data, $lv1_agents, $lv1_rebate);
        array_push($agents_array, $lv1_data);

        // children of first level 1 agent
        $children_ids = User::find($lv1_agents[0]['id'])->getChildrenIds();
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

        // push lvl 2 and above agent & rebate into array 
        for ($i = 2; $i <= sizeof($agents) + 1; $i++) {
            $temp = [];
            $rebate = $this->getRebateAllocate($agents[$i][0]['id'], $type_id);

            array_push($temp, $agents[$i], $rebate);
            array_push($agents_array, $temp);
        }

        return response()->json($agents_array);
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
        $rebate = User::find($user_id)->rebateAllocations()->where('account_type_id', $type_id)->get();

        $data = [
            'user_id' => $rebate[0]->user_id,
            'forex' => $rebate[0]->amount,
            'stocks' => $rebate[1]->amount,
            'indices' => $rebate[2]->amount,
            'commodities' => $rebate[3]->amount,
            'cryptocurrency' => $rebate[4]->amount,
        ];

        return $data;
    }

    public function changeAgents(Request $request)
    {
        // dd($request->all());
        $selected_agent_id = $request->id;
        $type_id = $request->type_id;
        $agents_array = [];

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
}
