<?php

namespace App\Services\Data;

use App\Models\TradingPlatform;
use App\Models\TradingUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class CreateTradingUser
{
    /**
     * @throws Throwable
     */
    public function execute(User $user, $data, $group): TradingUser
    {
        return $this->storeNewUser($user, $data, $group);
    }

    /**
     * @throws Throwable
     */
    public function storeNewUser(User $user, $data, $group): TradingUser
    {
        $trading_platform = TradingPlatform::find($group->trading_platform_id);

        if ($trading_platform->slug == 'mt4') {
            $login = $data['meta_login'];
        } else {
            $login = $data['login'];
        }

        $tradingUser = new TradingUser();
        $tradingUser->user_id = $user->id;
        $tradingUser->name = $data['name'];
        $tradingUser->meta_login = $login;
        $tradingUser->meta_group = $group->name;
        $tradingUser->account_type_id = $group->id;
        $tradingUser->leverage = $data['leverage'];
        $tradingUser->category = 'live';
        DB::transaction(function () use ($tradingUser) {
            $tradingUser->save();
        });

        return $tradingUser;
    }
}
