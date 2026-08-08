<?php

namespace App\Http\Controllers\Admin\AdminUser\Show;

use App\Enums\User\UserRoleEnum;
use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminUserShowController extends Controller
{
    public function __invoke(User $adminUser): Response
    {
        if ($adminUser->hasRole(UserRoleEnum::SUPER_ADMIN)) {
            throw new NotFoundHttpException('Super admin users are not viewable from this UI.');
        }

        $adminUser->load(['partner:id,title', 'roles', 'permissions']);

        // Permissions granted via roles (separately from any direct grants),
        // so the show page can label each row with where the permission comes
        // from. Mirrors the locked-by-role hint on the edit form.
        $rolePermissions = [];
        foreach ($adminUser->roles as $role) {
            $perms = Role::query()
                ->where('id', $role->id)
                ->with('permissions:id,name')
                ->first()
                ?->permissions
                ->pluck('name')
                ->all() ?? [];
            foreach ($perms as $perm) {
                $rolePermissions[$perm][] = $role->name;
            }
        }

        $directPermissions = $adminUser->permissions->pluck('name')->values()->all();

        // Effective set = direct ∪ via roles. Used for the "all effective
        // permissions" table on the show page and the PDF export.
        $effectivePermissions = collect(array_keys($rolePermissions))
            ->merge($directPermissions)
            ->unique()
            ->sort()
            ->values()
            ->map(fn (string $perm) => [
                'name' => $perm,
                'via_roles' => $rolePermissions[$perm] ?? [],
                'direct' => in_array($perm, $directPermissions, true),
            ])
            ->all();

        return Inertia::render('Admin/AdminUser/Show', [
            'admin' => [
                'id' => $adminUser->id,
                'name' => $adminUser->name,
                'email' => $adminUser->email,
                'phone' => $adminUser->phone,
                'partner' => $adminUser->partner ? [
                    'id' => $adminUser->partner->id,
                    'title' => $adminUser->partner->title,
                ] : null,
                'email_verified_at' => $adminUser->email_verified_at?->toDateTimeString(),
                'roles' => $adminUser->getRoleNames()->values(),
                'role_descriptions' => $adminUser->getRoleNames()
                    ->mapWithKeys(fn (string $name) => [$name => UserRoleEnum::descriptionFor($name)])
                    ->all(),
                'direct_permissions' => $directPermissions,
                'effective_permissions' => $effectivePermissions,
                'created_at' => $adminUser->created_at?->toDateTimeString(),
                'updated_at' => $adminUser->updated_at?->toDateTimeString(),
            ],
        ]);
    }
}
