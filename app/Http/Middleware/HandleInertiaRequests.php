<?php

namespace App\Http\Middleware;

use Inertia\Middleware;
use App\Models\SiteSetting;
use Illuminate\Http\Request;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): string|null
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'profile_photo' => $request->user() ? $request->user()->getFirstMediaUrl('profile_photo') : null,
                'payment_account' => $request->user() ? $request->user()->paymentAccounts : null,
            ],
            'toast' => session('toast'),
            'notification' => session('notification'),
            'locale' => session('locale') ? session('locale') : app()->getLocale(),
            'user_status' => session('user_status'),
            'permissions' => $request->user() ? $request->user()->getAllPermissions()->pluck('name')->toArray() : 'no permission',
            'site_settings' => SiteSetting::pluck('status', 'name'),
        ];
    }
}
