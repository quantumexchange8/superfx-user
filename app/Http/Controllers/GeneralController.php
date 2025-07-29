<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\AccountType;
use App\Models\UserToMarkupProfile;
use App\Models\User;
use App\Models\Symbol;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class GeneralController extends Controller
{    
    public function getUserMarkupProfiles($returnAsArray = false)
    {
        $user_markup_profiles = UserToMarkupProfile::where('user_id', Auth::id())
            ->get()
            ->map(function ($user_markup_profile) {
                return [
                    'referral_code' => $user_markup_profile->referral_code,
                    'markup_profile_id' => $user_markup_profile->markupProfile->id,
                    'name' => $user_markup_profile->markupProfile->name,
                    'account_types' => $user_markup_profile->markupProfile->markupProfileToAccountTypes
                                        ->map(fn($accountType) => $accountType->accountType)
                ];
            });

        if ($returnAsArray) {
            return $user_markup_profiles;
        }

        return response()->json([
            'user_markup_profiles' => $user_markup_profiles,
        ]);
    }

    public function getRebateUplines($returnAsArray = false)
    {
        $uplines = User::whereIn('role', ['ib'])
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

        if ($returnAsArray) {
            return $uplines;
        }

        return response()->json([
            'uplines' => $uplines,
        ]);
    }

    public function getSymbols($returnAsArray = false)
    {
        $symbols = Symbol::distinct()->pluck('meta_symbol_name');
    
        if ($returnAsArray) {
            return $symbols;
        }
    
        return response()->json([
            'symbols' => $symbols,
        ]);
    }

    public function getAccountTypes($returnAsArray = false)
    {
        $accountTypes = AccountType::where('status', 'active')
            ->get()
            ->map(function ($accountType) {
                return [
                    'value' => $accountType->id,
                    'name' => trans('public.' . $accountType->slug),
                ];
            });
    
        if ($returnAsArray) {
            return $accountTypes;
        }
    
        return response()->json([
            'accountTypes' => $accountTypes,
        ]);
    }
}
