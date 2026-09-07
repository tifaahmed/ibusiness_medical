<?php

namespace App\Http\Middleware;

use App\Enums\User\UserPermissionEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Gate for the admin area, based on permissions — never on role names.
 *
 * A user gets in when they hold at least one admin permission; `manage profile`
 * does not count because every account carries it for the Jetstream profile
 * page. Each route inside the group still declares the specific permission it
 * needs, so this only keeps out accounts with no admin abilities at all.
 *
 * The question is asked through the Gate (`canAny`) rather than through
 * Spatie's `hasAnyPermission`, which reads `role_has_permissions` directly and
 * so cannot see the super-admin grant in AppServiceProvider. That difference is
 * not academic: it let a super admin pass every route's own `permission:` check
 * and still be turned away at the door of the admin area.
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

        /* `canAny`, not `hasAnyPermission`: the former runs the Gate, so a
           super admin is admitted on the strength of the role, and a permission
           that exists in the enum but has never been seeded does not throw. */
        if (! $user->canAny($adminPermissions)) {
            throw new AccessDeniedHttpException('This action is unauthorized.');
        }

        return $next($request);
    }
}
