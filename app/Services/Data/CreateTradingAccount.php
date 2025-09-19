<?php

namespace App\Services\Data;

use App\Models\TradingAccount;
use App\Models\TradingPlatform;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class CreateTradingAccount
{
    /**
     * @throws Throwable
     */
    public function execute(User $user, $data, $group): TradingAccount
    {
        return $this->storeNewAccount($user, $data, $group);
    }

    /**
     * @throws Throwable
     */
    public function storeNewAccount(User $user, $data, $group): TradingAccount
    {
        $trading_platform = TradingPlatform::find($group->trading_platform_id);

        if ($trading_platform->slug == 'mt4') {
            $login = $data['meta_login'];
        } else {
            $login = $data['login'];
        }

        $tradingAccount = new TradingAccount();
        $tradingAccount->user_id = $user->id;
        $tradingAccount->meta_login = $login;
        $tradingAccount->account_type_id = $group->id;
        $tradingAccount->margin_leverage = $data['leverage'];
        DB::transaction(function () use ($tradingAccount) {
            $tradingAccount->save();
        });

        return $tradingAccount;
    }
}
