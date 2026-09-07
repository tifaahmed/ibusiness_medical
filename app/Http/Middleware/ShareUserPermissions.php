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
            /* Effective, not merely granted: a super admin passes every check
               through the Gate regardless of `role_has_permissions`, and this
               list is what the front end hides its buttons on. */
            $permissions = $user->effectivePermissionNames();

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
