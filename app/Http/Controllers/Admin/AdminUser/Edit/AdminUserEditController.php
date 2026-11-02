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
    public function __invoke(\Illuminate\Http\Request $request, User $adminUser): Response
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
                'permissions' => $r->permissions->pluck('name')->intersect(UserPermissionEnum::all())->values()->all(),
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
                'dashboard_blocked' => $adminUser->dashboard_blocked_at !== null,
                'email_verified' => $adminUser->email_verified_at !== null,
                'roles' => $adminUser->getRoleNames()->values(),
                // Stale rows (e.g. the retired "manage own contact messages") are not in the enum;
                // offering them back would fail the `in` rule on save.
                'direct_permissions' => $adminUser->permissions->pluck('name')->intersect(UserPermissionEnum::all())->values(),
            ],
            'assignable_roles' => $assignableRoles,
            'all_permissions' => UserPermissionEnum::all(),
            'partner_options' => $partners,
            // Monthly caps on hand-written balance changes: what is set, what this admin used this month. Only the super admin edits them.
            'ledger_limits' => collect(\App\Support\AdminLedgerLimits::permissions())->map(function (string $permission) use ($adminUser) {
                $own = \App\Models\AdminLedgerLimit::query()->where('user_id', $adminUser->id)->where('permission', $permission)->first();
                $effective = \App\Support\AdminLedgerLimits::sourceOf($adminUser, $permission);

                return [
                    'permission' => $permission,
                    'mode' => $own === null ? 'inherit' : ($own->monthly_limit === null ? 'unlimited' : 'limit'),
                    'monthly_limit' => $own?->monthly_limit,
                    // What applies right now and where it comes from: own | role | none (= unlimited).
                    'effective_limit' => $effective['limit'],
                    'source' => $effective['source'],
                    'granted' => $adminUser->can($permission),
                    'used' => \App\Support\AdminLedgerLimits::usedThisMonth($adminUser, str_contains($permission, 'wallet') ? 'wallet' : 'points', str_starts_with($permission, 'add') ? 'add' : 'take'),
                ];
            })->values()->all(),
            'can_edit_limits' => \App\Support\AdminLedgerLimits::isSuper($request->user()),
        ]);
    }
}
