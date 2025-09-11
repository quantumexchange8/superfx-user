<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\SiteSetting;

class ModuleAccess
{
    public function handle(Request $request, Closure $next, string $moduleName): Response
    {
        // Check site settings table for module status
        $setting = SiteSetting::where('name', $moduleName)->first();

        if (!$setting || $setting->status !== 'active') {
            abort(403, 'This module is disabled.');
        }

        return $next($request);
    }
}
