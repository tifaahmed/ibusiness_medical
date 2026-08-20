<?php

namespace App\Http\Middleware;

use App\Enums\User\UserPermissionEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Gate for the admin area, based on permissions only — never on role names.
 *
 * A user gets in when they hold at least one admin permission; `manage profile`
 * does not count because every account carries it for the Jetstream profile
 * page. Each route inside the group still declares the specific permission it
 * needs, so this only keeps out accounts with no admin abilities at all.
 */
class EnsureAdminAreaAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            throw new AccessDeniedHttpException('This action is unauthorized.');
        }

        $adminPermissions = array_diff(UserPermissionEnum::all(), [UserPermissionEnum::MANAGE_PROFILE]);

        if (! $user->hasAnyPermission($adminPermissions)) {
            throw new AccessDeniedHttpException('This action is unauthorized.');
        }

        return $next($request);
    }
}
