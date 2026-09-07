<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserPermissionEnum;
use App\Enums\User\UserRoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The super admin reaches everything, always.
 *
 * "Always" is the part worth testing. Before the `Gate::before` in
 * AppServiceProvider, the role only held whatever `PermissionSeeder` last
 * synced onto it — so shipping a feature with a new permission locked the super
 * admin out of it until somebody remembered to re-seed. That is how
 * `manage settings` came to 403 the one account meant to be able to do
 * anything, and these tests exist so it cannot happen again silently.
 */
class SuperAdminPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web'));

        return $user;
    }

    public function test_super_admin_passes_a_permission_it_was_never_granted(): void
    {
        $user = $this->superAdmin();

        /* The role holds nothing at all — the point being that the grant comes
           from being a super admin, not from `role_has_permissions`. */
        $this->assertCount(0, $user->getAllPermissions());

        $this->assertTrue($user->can(UserPermissionEnum::MANAGE_SETTINGS));
        $this->assertTrue($user->can(UserPermissionEnum::MANAGE_ORDERS));
        $this->assertTrue($user->canAny(['manage settings', 'view settings']));
    }

    public function test_super_admin_passes_a_permission_that_is_not_even_seeded_yet(): void
    {
        $user = $this->superAdmin();

        /* The exact shape of the original bug: a permission that exists in code
           and is used by a route, but has no row in `permissions` because the
           seeder has not been run since the feature shipped. */
        $this->assertDatabaseMissing('permissions', ['name' => 'manage some future thing']);

        $this->assertTrue($user->can('manage some future thing'));
    }

    public function test_super_admin_reaches_the_settings_page_that_was_returning_403(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user)->get('/admin/setting')->assertOk();
        $this->actingAs($user)->get(route('admin.setting.create'))->assertOk();
    }

    public function test_the_shared_permission_list_matches_what_the_super_admin_can_do(): void
    {
        $user = $this->superAdmin();

        /* The admin UI hides its buttons on this list. If it reported only the
           granted rows, a super admin would get a page whose links were all
           missing even though every route behind them would have let them in. */
        $this->assertSame(UserPermissionEnum::all(), $user->effectivePermissionNames());

        $this->actingAs($user)
            ->get('/admin/setting')
            ->assertInertia(fn ($page) => $page
                ->where('authUserPermissions', UserPermissionEnum::all()));
    }

    public function test_an_ordinary_admin_is_still_refused(): void
    {
        /* The other half of `Gate::before`: it returns null, not false, for
           everyone else — so normal permission checks still decide, and a
           blanket "true" has not leaked past the super admin. */
        $user = User::factory()->create();
        $role = Role::findOrCreate(UserRoleEnum::ADMIN.'-settings-none', 'web');
        $role->givePermissionTo(Permission::findOrCreate(UserPermissionEnum::VIEW_ORDERS, 'web'));
        $user->assignRole($role);

        $this->assertFalse($user->can(UserPermissionEnum::MANAGE_SETTINGS));
        $this->assertTrue($user->can(UserPermissionEnum::VIEW_ORDERS));

        $this->actingAs($user)->get('/admin/setting')->assertForbidden();
    }

    public function test_every_permission_the_enum_defines_is_one_the_super_admin_holds(): void
    {
        $user = $this->superAdmin();

        foreach (UserPermissionEnum::all() as $permission) {
            $this->assertTrue(
                $user->can($permission),
                "The super admin could not {$permission}.",
            );
        }
    }
}
