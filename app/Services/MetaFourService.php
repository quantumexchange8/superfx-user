<?php

namespace App\Services;

use App\Services\Data\CreateTradingAccount;
use App\Services\Data\CreateTradingUser;
use App\Services\Data\UpdateTradingAccount;
use App\Services\Data\UpdateTradingUser;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\User as UserModel;

class MetaFourService {
    private string $port = "8443";
    private string $login = "10012";
    private string $password = "Test1234.";
    private string $baseURL = "https://superfin-live.currenttech.pro/api";
    // private string $demoURL = "https://superfin-demo.currenttech.pro/api";

    private string $environmentName = "live";

    private string $token;

    public function __construct()
    {
        $token2 = "SuperFin-Live^" . Carbon::now('Asia/Riyadh')->toDateString() . "&SuperGlobal";

        // $this->baseURL = app()->environment('production')
        //     ? 'https://superfin-live.currenttech.pro/api'
        //     : 'https://superfin-demo.currenttech.pro/api';

        $this->token = hash('sha256', $token2);
    }

    public function getUser($meta_login)
    {
        $payload = [
            'meta_login' => $meta_login,
        ];

        $jsonPayload = json_encode($payload);

        $accountResponse = Http::acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])
            ->withBody($jsonPayload, 'application/json')
            ->get($this->baseURL . "/getuser");

        return $accountResponse->json();
    }

    public function getUserInfo($meta_login): void
    {
        $data = $this->getUser($meta_login);

        if ($data && $data['status'] == 'success') {
            (new UpdateTradingUser)->execute($meta_login, $data);
            (new UpdateTradingAccount)->execute($meta_login, $data);
        }
    }

    public function createUser(UserModel $user, $group, $leverage, $mainPassword, $investorPassword)
    {
        $accountResponse = Http::acceptJson()
        ->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])
        ->post($this->baseURL . "/createuser", [
            'master_password' => $mainPassword,
            'investor_password' => $investorPassword,
            'name' => $user->name,
            'group' => $group,
            'leverage' => $leverage,
            'email' => $user->email,
        ]);
        $accountResponse = $accountResponse->json();

        (new CreateTradingAccount)->execute($user, $accountResponse, $group);
        (new CreateTradingUser)->execute($user, $accountResponse, $group);
        return $accountResponse;
    }

    public function createTrade($meta_login, $amount, $comment, $type, $expire_date)
    {
        $dealResponse = Http::acceptJson()
        ->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])
        ->post($this->baseURL . "/transaction", [
            'meta_login' => $meta_login,
            'type' => $type,
            'amount' => $amount,
            'expiration_date' => $expire_date,
            'comment' => $comment,
        ]);
        $dealResponse = $dealResponse->json();
        Log::debug($dealResponse);

        $this->getUserInfo($meta_login);

        return $dealResponse;
    }

    public function deleteTrader($meta_login): void
    {
        Http::acceptJson()
        ->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])
        ->patch($this->baseURL . "/disableaccount", [
            'meta_login' => $meta_login,
        ]);
    }
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
