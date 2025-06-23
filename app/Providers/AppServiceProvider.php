<?php

namespace App\Providers;

use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\Facades\LogViewer;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (App::environment('production') || App::environment('staging')) {
            resolve(\Illuminate\Routing\UrlGenerator::class)->forceScheme('https');
            $this->app['request']->server->set('HTTPS', true);
        }

        LogViewer::auth(function ($request) {
            return $request->user()
                && $request->user()->email == 'quantumexchange8@gmail.com';
        });
    }
}
