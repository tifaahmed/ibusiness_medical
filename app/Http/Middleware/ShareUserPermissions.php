<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Features;

class ShareUserPermissions
{
    public function handle(Request $request, Closure $next)
    {
        if ($user = $request->user()) {
            $roles = $user->getRoleNames()->values()->all();
            $permissions = $user->getAllPermissions()->pluck('name')->values()->all();

            Inertia::share([
                'auth' => [
                    'user' => array_merge($user->toArray(), [
                        'two_factor_enabled' => Features::enabled(Features::twoFactorAuthentication())
                            && ! is_null($user->two_factor_secret),
                        'roles' => $roles,
                        'permissions' => $permissions,
                    ]),
                ],
            ]);
        }

        return $next($request);
    }
}
