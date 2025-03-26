<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\UserToMarkupProfile;
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

}
