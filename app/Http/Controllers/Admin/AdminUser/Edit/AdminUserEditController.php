<?php

namespace App\Http\Controllers\Admin\AdminUser\Edit;

use App\Enums\User\UserPermissionEnum;
use App\Enums\User\UserRoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminUserEditController extends Controller
{
    public function __invoke(User $adminUser): Response
    {
        if ($adminUser->hasRole(UserRoleEnum::SUPER_ADMIN)) {
            throw new NotFoundHttpException('Super admin users cannot be edited from this UI.');
        }

        $assignableRoles = Role::query()
            ->where('guard_name', 'web')
            ->whereNotIn('name', [UserRoleEnum::SUPER_ADMIN, UserRoleEnum::MEMBER])
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $r) => [
                'name' => $r->name,
                'permissions' => $r->permissions->pluck('name')->values()->all(),
                'description' => UserRoleEnum::descriptionFor($r->name),
            ])
            ->values();

        $partners = Partner::orderBy('title')->get(['id', 'title'])->map(fn (Partner $p) => [
            'value' => $p->id,
            'label' => $p->title,
        ])->values();

        return Inertia::render('Admin/AdminUser/Edit', [
            'admin' => [
                'id' => $adminUser->id,
                'name' => $adminUser->name,
                'email' => $adminUser->email,
                'partner_id' => $adminUser->partner_id,
                'email_verified' => $adminUser->email_verified_at !== null,
                'roles' => $adminUser->getRoleNames()->values(),
                'direct_permissions' => $adminUser->permissions->pluck('name')->values(),
            ],
            'assignable_roles' => $assignableRoles,
            'all_permissions' => UserPermissionEnum::all(),
            'partner_options' => $partners,
        ]);
    }
}
