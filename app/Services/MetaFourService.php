<?php

namespace App\Services;

use App\Services\Data\CreateTradingAccount;
use App\Services\Data\CreateTradingUser;
use App\Services\Data\UpdateAccountBalance;
use App\Services\Data\UpdateTradingAccount;
use App\Services\Data\UpdateTradingUser;
use App\Services\TradingPlatform\TradingPlatformInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\User as UserModel;
use Throwable;

class MetaFourService implements TradingPlatformInterface {
    private string $port = "8443";
    private string $login = "10012";
    private string $password = "Test1234.";
    private string $baseURL = "https://superfin-live.currenttech.pro/api";
     private string $demoURL = "https://superfin-demo.currenttech.pro/api";

    private string $environmentName = "live";

    private string $token;

    public function __construct()
    {
        $token2 = "SuperFin-Live^" . Carbon::now('Asia/Riyadh')->toDateString() . "&SuperGlobal";

        $this->token = hash('sha256', $token2);
    }

    /**
     * @throws ConnectionException
     */
    public function getUser($metaLogin): array
    {
        $payload = [
            'meta_login' => $metaLogin,
        ];

        $jsonPayload = json_encode($payload);

        $accountResponse = Http::acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])
            ->withBody($jsonPayload)
            ->post($this->baseURL . "/getuser");

        return $accountResponse->json();
    }

    public function getAccount($metaLogin): array
    {
        return [];
    }

    /**
     * @throws ConnectionException
     * @throws Throwable
     */
    public function getAccountEquity($meta_login)
    {
        $payload = [
            'meta_login' => $meta_login,
        ];

        $jsonPayload = json_encode($payload);

        $accountResponse = Http::acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])
            ->withBody($jsonPayload)
            ->post($this->baseURL . "/getequity");

        $data = $accountResponse->json();
        (new UpdateAccountBalance())->execute($meta_login, $data);

        return $data;
    }

    /**
     * @throws ConnectionException
     * @throws Throwable
     */
    public function getUserInfo($meta_login): void
    {
        $data = $this->getUser($meta_login);
        $this->getAccountEquity($meta_login);

        if ($data) {
            (new UpdateTradingUser)->execute($meta_login, $data);
            (new UpdateTradingAccount)->execute($meta_login, $data);
        }
    }

    /**
     * @throws ConnectionException|Throwable
     */
    public function createUser(UserModel $user, $group, $leverage, $mainPassword, $investorPassword)
    {
        $payload = [
            'master_password' => $mainPassword,
            'investor_password' => $investorPassword,
            'name' => $user->name,
            'group' => $group->account_group,
            'leverage' => $leverage,
            'email' => $user->email,
        ];

        $jsonPayload = json_encode($payload);

        $accountResponse = Http::acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])
            ->withBody($jsonPayload)
            ->post($this->baseURL . "/createuser");

        (new CreateTradingAccount)->execute($user, $accountResponse, $group);
        (new CreateTradingUser)->execute($user, $accountResponse, $group);
        return $accountResponse;
    }

    /**
     * @throws ConnectionException|Throwable
     */
    public function createDeal($meta_login, $amount, $comment, $type, $expire_date): array
    {
        $payload = [
            'meta_login' => $meta_login,
            'type' => $type,
            'amount' => (float) $amount,
            'expiration_date' => $expire_date,
            'comment' => $comment,
        ];

        $jsonPayload = json_encode($payload);

        $dealResponse = Http::acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])
            ->withBody($jsonPayload, 'application/json')
            ->post($this->baseURL . "/transaction");

        $dealResponse = $dealResponse->json();
        Log::debug($dealResponse);

        $this->getUserInfo($meta_login);

        return $dealResponse;
    }

    // public function deleteTrader($meta_login): void
    // {
    //     Http::acceptJson()
    //     ->withHeaders([
    //         'Authorization' => 'Bearer ' . $this->token,
    //     ])
    //     ->patch($this->baseURL . "/disableaccount", [
    //         'meta_login' => $meta_login,
    //     ]);
    // }

    public function updateLeverage($meta_login, $leverage): void
    {
        $payload = [
            'meta_login' => $meta_login,
            'leverage' => $leverage,
        ];

        $jsonPayload = json_encode($payload);

        Http::acceptJson()
        ->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])
        ->patch($this->baseURL . "/updateleverage", [
            'meta_login' => $meta_login,
            'leverage' => $leverage,
        ]);

        $this->getUserInfo($meta_login);
    }

    public function changeMasterPassword($meta_login, $password): void
    {
        $payload = [
            'meta_login' => $meta_login,
            'password' => $password,
        ];

        $jsonPayload = json_encode($payload);

        Http::acceptJson()
        ->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])
        ->patch($this->baseURL . "/changemasterpassword", [
            'meta_login' => $meta_login,
            'password' => $password,
        ]);

        $this->getUserInfo($meta_login);
    }

    public function changeInvestorPassword($meta_login, $password): void
    {
        $payload = [
            'meta_login' => $meta_login,
            'password' => $password,
        ];

        $jsonPayload = json_encode($payload);

        Http::acceptJson()
        ->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])
        ->patch($this->baseURL . "/changeinvestorpassword", [
            'meta_login' => $meta_login,
            'password' => $password,
        ]);

        $this->getUserInfo($meta_login);
    }

    public function createDemoUser(UserModel $user, $group, $leverage, $mainPassword, $investorPassword)
    {
        $payload = [
            'master_password' => $mainPassword,
            'investor_password' => $investorPassword,
            'name' => $user->name,
            'group' => $group,
            'leverage' => $leverage,
            'email' => $user->email,
        ];

        $jsonPayload = json_encode($payload);

        return Http::acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])
            ->withBody($jsonPayload, 'application/json')
            ->post($this->demoURL . "/createuser");
    }

    public function createDemoTrade($meta_login, $amount, $comment, $type, $expire_date)
    {
        $payload = [
            'meta_login' => $meta_login,
            'type' => $type,
            'amount' => (float) $amount,
            'expiration_date' => $expire_date,
            'comment' => $comment,
        ];

        $jsonPayload = json_encode($payload);

        $dealResponse = Http::acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])
            ->withBody($jsonPayload, 'application/json')
            ->post($this->demoURL . "/transaction");

        $dealResponse = $dealResponse->json();
        Log::debug($dealResponse);

        return $dealResponse;
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
