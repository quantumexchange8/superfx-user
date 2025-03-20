<?php

namespace App\Services;

use App\Models\PaymentAccount;
use App\Models\User;
use App\Models\Bank;
use App\Models\Group;
use App\Models\Country;
use App\Models\Transaction;
use App\Models\GroupHasUser;
use App\Models\SettingLeverage;
use App\Models\TradingAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DropdownOptionService
{
    public function getUplines(): Collection
    {
        return User::whereIn('id', User::find(Auth::id())->getChildrenIds())
            ->select('id', 'name')
            ->get()
            ->map(function ($user) {
                return [
                    'value' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    // 'profile_photo' => $user->getFirstMediaUrl('profile_photo')
                ];
            });
    }

    public function getCountries(): Collection
    {
        return Country::get()->map(function ($country) {
            return [
                'id' => $country->id,
                'name' => $country->name,
                'phone_code' => $country->phone_code,
                'nationality' => $country->nationality,
            ];
        });
    }

    public function getBanks(): Collection
    {
        return Bank::get()->map(function ($bank) {
            return [
                'id' => $bank->id,
                'bank_name' => $bank->bank_name,
                'bank_code' => $bank->bank_code,
            ];
        });
    }

    public function getGroups(): Collection
    {
        return Group::get()
            ->map(function ($group) {
                return [
                    'value' => $group->id,
                    'name' => $group->name,
                    'color' => $group->color,
                ];
            });
    }

    public function getLeverages(): Collection
    {
        $leverages = SettingLeverage::where('status', 'active')
                    ->get()
                    ->map(function ($leverage) {
                        return [
                            'name' => $leverage->display,
                            'value' => $leverage->value,
                        ];
                    });
        $leverages->prepend(['name' => 'Free', 'value' => 0]);
        return $leverages;
    }

    public function getLeveragesOptions(): Collection
    {
        $leverages = SettingLeverage::where('status', 'active')
                    ->get()
                    ->map(function ($leverage) {
                        return [
                            'name' => $leverage->display,
                            'value' => $leverage->value,
                        ];
                    });
        return $leverages;
    }

    public function getInternalTransferOptions(): Collection
    {
        $user = Auth::user();

        $trading_accounts = $user->tradingAccounts;
        try {
            foreach ($trading_accounts as $trading_account) {
                (new MetaFourService)->getUserInfo($trading_account->meta_login);
            }
        } catch (\Throwable $e) {
            Log::error($e->getMessage());
        }

        return $user->tradingAccounts->map(function($trading_account) {
            return [
                'name' => $trading_account->meta_login,
                'value' => $trading_account->balance,
                'group' => $trading_account->account_type->account_group,
                'category' => $trading_account->account_type->category,
                'minimum_deposit' => $trading_account->account_type->minimum_deposit,
                'balance_multiplier' => $trading_account->account_type->balance_multiplier,
            ];
        });
    }

    public function getWalletOptions(): Collection
    {
        return PaymentAccount::where('user_id', Auth::id())
                    ->get()
                    ->map(function ($walletOption) {
                        return [
                            'name' => $walletOption->payment_account_name,
                            'value' => $walletOption->account_no,
                        ];
                    });
    }

    public function getBankOptions(): Collection
    {
        return PaymentAccount::where('user_id', Auth::id())
                    ->where('payment_platform', 'bank')
                    ->get()
                    ->map(function ($bankOption) {
                        return [
                            'id' => $bankOption->id,
                            'account_name' => $bankOption->payment_account_name,
                            'bank_name' => $bankOption->payment_platform_name,
                            'account_number' => $bankOption->account_no,
                            'bank_code' => $bankOption->bank_code,
                            'payment_account_type' => $bankOption->payment_account_type,
                        ];
                    });
    }

    public function getAgents(): Collection
    {
        $has_group = GroupHasUser::pluck('user_id');

        $users = User::where('role', 'ib')
            ->whereNotIn('id', $has_group)
            ->select('id', 'name')
            ->get()
            ->map(function ($user) {
                return [
                    'value' => $user->id,
                    'name' => $user->name,
                    'profile_photo' => $user->getFirstMediaUrl('profile_photo')
                ];
            });

        return $users;
    }

    public function getTransactionMonths(): Collection
    {
        // Fetch the created_at dates of all transactions
        $transactionDates = Transaction::pluck('created_at');

        // Map the dates to the desired format and remove duplicates
        $months = $transactionDates
            ->map(function ($date) {
                return \Carbon\Carbon::parse($date)->format('m/Y');
            })
            ->unique()
            ->values();

        return $months;
    }

    public function getRebateUplines(): Collection
    {
        return User::whereIn('id', array_merge([Auth::id()],User::find(Auth::id())->getChildrenIds()))
            ->select('id', 'name', 'email', 'id_number')
            ->whereNot('role', 'member')
            ->get()
            ->map(function ($user) {
                return [
                    'value' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'id_number' => $user->id_number,
                    // 'profile_photo' => $user->getFirstMediaUrl('profile_photo')
                ];
            });
    }

    public function getRebateDownlines(): Collection
    {
        return User::whereIn('id', array_merge([Auth::id()],User::find(Auth::id())->getChildrenIds()))
            ->select('id', 'name', 'email', 'id_number')
            ->get()
            ->map(function ($user) {
                return [
                    'value' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'id_number' => $user->id_number,
                    // 'profile_photo' => $user->getFirstMediaUrl('profile_photo')
                ];
            });
    }

    public function getDownlines(): Collection
    {
        return User::where('upline_id', Auth::id())
            ->select('id', 'name', 'email', 'id_number')
            ->get()
            ->map(function ($user) {
                return [
                    'value' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'id_number' => $user->id_number,
                    // 'profile_photo' => $user->getFirstMediaUrl('profile_photo')
                ];
            });
    }
}
