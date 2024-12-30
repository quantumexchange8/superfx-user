<?php

namespace App\Services;

use App\Services\Data\CreateTradingAccount;
use App\Services\Data\CreateTradingUser;
use App\Services\Data\UpdateTradingAccount;
use App\Services\Data\UpdateTradingUser;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\User as UserModel;

class MetaFourService {
    private string $port = "8443";
    private string $login = "10012";
    private string $password = "Test1234.";
//    private string $baseURL = "http://192.168.0.224:5000/api";
    private string $baseURL = "https://superfin-live.currenttech.pro/api";
    private string $demoURL = "https://superfin-demo.currenttech.pro/api";

    private string $token = "6f0d6f97-3042-4389-9655-9bc321f3fc1e";
    private string $environmentName = "live";

    public function getConnectionStatus()
    {
        try {
            return Http::acceptJson()->timeout(10)->get($this->baseURL . "/connect_status")->json();
        } catch (ConnectionException $exception) {
            // Handle the connection timeout error
            // For example, returning an empty array as a default response
            return [];
        }
    }

    public function getMetaUser($meta_login)
    {
        return Http::acceptJson()->get($this->demoURL . "/getuser/$meta_login")->json();
    }

    // public function getMetaAccount($meta_login)
    // {
    //     return Http::acceptJson()->get($this->baseURL . "/trade_acc/$meta_login")->json();
    // }

    // public function getUserInfo($tradingAccounts): void
    // {
    //     foreach ($tradingAccounts as $row) {
    //         $userData = $this->getMetaUser($row->meta_login);
    //         $metaAccountData = $this->getMetaAccount($row->meta_login);
    //         Log::debug($userData, $metaAccountData);
    //         if($userData && $metaAccountData) {
    //             (new UpdateTradingAccount)->execute($row->meta_login, $metaAccountData);
    //             (new UpdateTradingUser)->execute($row->meta_login, $userData);
    //         }
    //     }
    // }

    public function createUser(UserModel $user, $group, $leverage, $mainPassword, $investorPassword)
    {
        $accountResponse = Http::acceptJson()->post($this->demoURL . "/createuser", [
            'master_password' => $mainPassword,
            'investor_password' => $investorPassword,
            'name' => $user->name,
            'group' => $group,
            'leverage' => $leverage,
            'email' => $user->email,
        ]);
        $accountResponse = $accountResponse->json();

        (new CreateTradingAccount)->execute($user, $group, $accountResponse);
        (new CreateTradingUser)->execute($user, $group, $accountResponse);
        return $accountResponse;
    }

    // public function createDeal($meta_login, $amount, $comment, $type)
    // {
    //     $dealResponse = Http::acceptJson()->post($this->baseURL . "/conduct_deal", [
    //         'login' => $meta_login,
    //         'amount' => $amount,
    //         'imtDeal_EnDealAction' => dealType::DEAL_BALANCE,
    //         'comment' => $comment,
    //         'deposit' => $type,
    //     ]);
    //     $dealResponse = $dealResponse->json();
    //     Log::debug($dealResponse);

    //     $userData = $this->getMetaUser($meta_login);
    //     $metaAccountData = $this->getMetaAccount($meta_login);
    //     (new UpdateTradingAccount)->execute($meta_login, $metaAccountData);
    //     (new UpdateTradingUser)->execute($meta_login, $userData);
    //     return $dealResponse;
    // }

    // public function disableTrade($meta_login)
    // {
    //     $disableTrade = Http::acceptJson()->patch($this->baseURL . "/disable_trade/{$meta_login}")->json();

    //     $userData = $this->getMetaUser($meta_login);
    //     $metaAccountData = $this->getMetaAccount($meta_login);
    //     (new UpdateTradingAccount)->execute($meta_login, $metaAccountData);
    //     (new UpdateTradingUser)->execute($meta_login, $userData);

    //     return $disableTrade;
    // }

    // public function dealHistory($meta_login, $start_date, $end_date)
    // {
    //     return Http::acceptJson()->get($this->baseURL . "/deal_history/{$meta_login}&{$start_date}&{$end_date}")->json();
    // }

    // public function updateLeverage($meta_login, $leverage)
    // {
    //     $upatedResponse = Http::acceptJson()->patch($this->baseURL . "/update_leverage", [
    //         'login' => $meta_login,
    //         'leverage' => $leverage,
    //     ]);
    //     $upatedResponse = $upatedResponse->json();
    //     $userData = $this->getMetaUser($meta_login);
    //     $metaAccountData = $this->getMetaAccount($meta_login);
    //     (new UpdateTradingAccount)->execute($meta_login, $metaAccountData);
    //     (new UpdateTradingUser)->execute($meta_login, $userData);

    //     return $upatedResponse;
    // }

    // public function changePassword($meta_login, $type, $password)
    // {
    //     $passwordResponse = Http::acceptJson()->patch($this->baseURL . "/change_password", [
    //         'login' => $meta_login,
    //         'type' => $type,
    //         'password' => $password,
    //     ]);
    //     return $passwordResponse->json();
    // }

    // public function userTrade($meta_login)
    // {
    //     return Http::acceptJson()->get($this->baseURL . "/check_position/{$meta_login}")->json();
    // }

    // public function deleteAccount($meta_login)
    // {
    //     $deleteAccount = Http::acceptJson()->delete($this->baseURL . "/delete_tradeacc/{$meta_login}")->json();

    //     return $deleteAccount;
    // }
}

class dealAction
{
    const DEPOSIT = true;
    const WITHDRAW = false;
}

class dealType
{
    const DEAL_BALANCE = 2;
    const DEAL_CREDIT = 3;
    const DEAL_BONUS = 6;
}

class passwordType
{
    const MAIN = false;
    const INVESTOR = true;
}
