<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserPermissionEnum;
use App\Enums\User\UserRoleEnum;
use App\Models\AdminLedgerLimit;
use App\Models\LoyaltyTransaction;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Adding / taking points and wallet money by hand: four permissions, an optional monthly cap each, super admin unlimited. */
class LedgerPermissionsLimitsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $permissions = []): User
    {
        foreach (array_merge(['manage memberships'], UserPermissionEnum::all()) as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $u = User::factory()->create();
        $u->givePermissionTo(array_merge(['manage memberships'], $permissions));

        return $u;
    }

    private function grant(User $as, int $memberId, int $points)
    {
        return $this->actingAs($as)->post(route('admin.loyalty.grants.store'), ['user_id' => $memberId, 'points' => $points, 'note' => 'because']);
    }

    private function wallet(User $as, int $memberId, float $amount)
    {
        return $this->actingAs($as)->post(route('admin.loyalty.wallet.adjust'), ['user_id' => $memberId, 'amount' => $amount, 'note' => 'because']);
    }

    public function test_without_the_permission_it_is_refused_and_nothing_is_written(): void
    {
        $member = User::factory()->create();
        $admin = $this->admin(); // manage memberships only

        $this->grant($admin, $member->id, 50)->assertForbidden();
        $this->wallet($admin, $member->id, 5)->assertForbidden();
        $this->assertSame(0, LoyaltyTransaction::balanceOf($member->id));
        $this->assertEquals(0, WalletTransaction::balanceOf($member->id));
    }

    public function test_add_and_take_are_separate_permissions(): void
    {
        $member = User::factory()->create();
        $admin = $this->admin([UserPermissionEnum::ADD_LOYALTY_POINTS]);

        $this->grant($admin, $member->id, 50)->assertRedirect();
        $this->assertSame(50, LoyaltyTransaction::balanceOf($member->id));
        $this->grant($admin, $member->id, -10)->assertForbidden();
        $this->assertSame(50, LoyaltyTransaction::balanceOf($member->id));
    }

    public function test_a_monthly_cap_stops_the_change_that_would_pass_it_and_unlimited_never_does(): void
    {
        $member = User::factory()->create();
        $admin = $this->admin([UserPermissionEnum::ADD_LOYALTY_POINTS, UserPermissionEnum::ADD_WALLET_MONEY]);
        AdminLedgerLimit::create(['user_id' => $admin->id, 'permission' => UserPermissionEnum::ADD_LOYALTY_POINTS, 'monthly_limit' => 100]);

        $this->grant($admin, $member->id, 60)->assertSessionHas('success');
        $this->grant($admin, $member->id, 41)->assertSessionHas('error'); // 60 + 41 > 100
        $this->assertSame(60, LoyaltyTransaction::balanceOf($member->id));
        $this->grant($admin, $member->id, 40)->assertSessionHas('success'); // exactly the cap
        $this->grant($admin, $member->id, 1)->assertSessionHas('error');
        $this->assertSame(100, LoyaltyTransaction::balanceOf($member->id));

        // No row for the wallet = unlimited.
        $this->wallet($admin, $member->id, 99999)->assertSessionHas('success');
        $this->assertEquals(99999, WalletTransaction::balanceOf($member->id));
    }

    public function test_last_months_lines_do_not_count_and_the_super_admin_is_never_capped(): void
    {
        $member = User::factory()->create();
        $admin = $this->admin([UserPermissionEnum::ADD_LOYALTY_POINTS]);
        AdminLedgerLimit::create(['user_id' => $admin->id, 'permission' => UserPermissionEnum::ADD_LOYALTY_POINTS, 'monthly_limit' => 100]);
        $old = LoyaltyTransaction::create(['user_id' => $member->id, 'source' => 'admin', 'client_id' => 'old', 'points' => 90, 'created_by' => $admin->id, 'answered_at' => now()]);
        $old->forceFill(['created_at' => now()->subMonths(2)])->save();

        $this->grant($admin, $member->id, 100)->assertSessionHas('success');

        $super = User::factory()->create();
        $super->assignRole(Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web'));
        AdminLedgerLimit::create(['user_id' => $super->id, 'permission' => UserPermissionEnum::ADD_LOYALTY_POINTS, 'monthly_limit' => 1]);
        $this->grant($super, $member->id, 5000)->assertSessionHas('success');
    }

    public function test_a_transfer_needs_both_take_and_add_and_counts_on_the_caps(): void
    {
        $from = User::factory()->create(['phone' => '01000000001']);
        $to = User::factory()->create(['phone' => '01000000002']);
        LoyaltyTransaction::create(['user_id' => $from->id, 'source' => 'admin', 'client_id' => 's', 'points' => 500, 'answered_at' => now()]);
        $admin = $this->admin([UserPermissionEnum::TAKE_LOYALTY_POINTS]); // can take, cannot add
        $body = ['user_id' => $from->id, 'to_phone' => '01000000002', 'kind' => 'points', 'amount' => 100, 'note' => 'x'];

        $this->actingAs($admin)->post(route('admin.loyalty.transfer'), $body)->assertForbidden();
        $this->assertSame(500, LoyaltyTransaction::balanceOf($from->id));

        $admin->givePermissionTo(UserPermissionEnum::ADD_LOYALTY_POINTS);
        AdminLedgerLimit::create(['user_id' => $admin->id, 'permission' => UserPermissionEnum::TAKE_LOYALTY_POINTS, 'monthly_limit' => 150]);
        $this->actingAs($admin)->post(route('admin.loyalty.transfer'), $body)->assertSessionHas('success');
        $this->actingAs($admin)->post(route('admin.loyalty.transfer'), $body)->assertSessionHas('error'); // 100 + 100 > 150
        $this->assertSame(400, LoyaltyTransaction::balanceOf($from->id));
        $this->assertSame(100, LoyaltyTransaction::balanceOf($to->id));
    }

    public function test_the_ledger_tells_the_screen_what_this_admin_may_still_do(): void
    {
        $member = User::factory()->create();
        $admin = $this->admin([UserPermissionEnum::ADD_LOYALTY_POINTS]);
        AdminLedgerLimit::create(['user_id' => $admin->id, 'permission' => UserPermissionEnum::ADD_LOYALTY_POINTS, 'monthly_limit' => 100]);
        $this->grant($admin, $member->id, 30);

        $this->actingAs($admin)->getJson(route('admin.loyalty.members.ledger', $member->id))
            ->assertOk()
            ->assertJsonPath('allowance.points.add.allowed', true)->assertJsonPath('allowance.points.add.remaining', 70)
            ->assertJsonPath('allowance.points.take.allowed', false)
            ->assertJsonPath('allowance.wallet.add.allowed', false);
    }

    public function test_only_the_super_admin_sets_an_admins_limits_with_inherit_unlimited_or_a_number(): void
    {
        $target = $this->admin([UserPermissionEnum::ADD_LOYALTY_POINTS]);
        $plain = $this->admin();
        $super = User::factory()->create();
        $super->assignRole(Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web'));
        $url = route('admin.admin-users.ledger-limits', $target->id);
        $add = UserPermissionEnum::ADD_LOYALTY_POINTS;
        $take = UserPermissionEnum::TAKE_LOYALTY_POINTS;

        $this->actingAs($plain)->put($url, ['limits' => [$add => ['mode' => 'limit', 'value' => 5]]])->assertForbidden();
        $this->assertDatabaseCount('admin_ledger_limits', 0);

        $this->actingAs($super)->put($url, ['limits' => [$add => ['mode' => 'limit', 'value' => 250.5], $take => ['mode' => 'unlimited']]])->assertRedirect();
        $this->assertEquals(250.5, AdminLedgerLimit::where(['user_id' => $target->id, 'permission' => $add])->value('monthly_limit'));
        $this->assertNull(AdminLedgerLimit::where(['user_id' => $target->id, 'permission' => $take])->value('monthly_limit'));
        $this->assertDatabaseHas('admin_ledger_limits', ['user_id' => $target->id, 'permission' => $take]);

        // "Inherit" removes the admin's own row again.
        $this->actingAs($super)->put($url, ['limits' => [$add => ['mode' => 'inherit']]])->assertRedirect();
        $this->assertDatabaseMissing('admin_ledger_limits', ['user_id' => $target->id, 'permission' => $add]);
    }

    public function test_a_role_limit_is_the_default_for_its_holders_and_the_admins_own_row_wins(): void
    {
        $role = Role::findOrCreate('cashier', 'web');
        $this->admin(); // creates every permission row
        $role->givePermissionTo(['manage memberships', UserPermissionEnum::ADD_WALLET_MONEY]);
        $member = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole($role);
        $url = route('admin.roles.ledger-limits', $role->id);
        $super = User::factory()->create();
        $super->assignRole(Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web'));

        // Nothing set anywhere: unlimited (and the screen says so).
        $this->wallet($admin, $member->id, 1000)->assertSessionHas('success');

        $this->actingAs($admin)->put($url, ['limits' => [UserPermissionEnum::ADD_WALLET_MONEY => 50]])->assertForbidden();
        $this->actingAs($super)->put($url, ['limits' => [UserPermissionEnum::ADD_WALLET_MONEY => 50]])->assertRedirect();

        // The role's 50 a month applies to every holder: 1000 was already used above, so even 1 more is over it.
        $this->wallet($admin, $member->id, 1)->assertSessionHas('error');

        // This admin's own limit replaces the role's.
        AdminLedgerLimit::create(['user_id' => $admin->id, 'permission' => UserPermissionEnum::ADD_WALLET_MONEY, 'monthly_limit' => 5000]);
        $this->wallet($admin, $member->id, 100)->assertSessionHas('success');
        AdminLedgerLimit::where('user_id', $admin->id)->update(['monthly_limit' => null]); // explicit unlimited beats the role too
        $this->wallet($admin, $member->id, 100000)->assertSessionHas('success');
    }

    public function test_two_roles_apply_the_strictest_cap_and_the_edit_page_shows_the_limits(): void
    {
        $a = Role::findOrCreate('role-a', 'web');
        $b = Role::findOrCreate('role-b', 'web');
        $this->admin();
        $a->givePermissionTo(UserPermissionEnum::TAKE_WALLET_MONEY);
        $b->givePermissionTo(UserPermissionEnum::TAKE_WALLET_MONEY);
        \App\Models\RoleLedgerLimit::create(['role_id' => $a->id, 'permission' => UserPermissionEnum::TAKE_WALLET_MONEY, 'monthly_limit' => 300]);
        \App\Models\RoleLedgerLimit::create(['role_id' => $b->id, 'permission' => UserPermissionEnum::TAKE_WALLET_MONEY, 'monthly_limit' => 120]);
        $admin = User::factory()->create();
        $admin->assignRole([$a, $b]);

        $this->assertEquals(120.0, \App\Support\AdminLedgerLimits::limitOf($admin, UserPermissionEnum::TAKE_WALLET_MONEY));
        $this->assertSame('role', \App\Support\AdminLedgerLimits::sourceOf($admin, UserPermissionEnum::TAKE_WALLET_MONEY)['source']);

        $super = User::factory()->create();
        $super->assignRole(Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web'));
        $this->actingAs($super)->get(route('admin.roles.edit', $a->id))->assertOk()
            ->assertInertia(fn ($p) => $p->where('can_edit_limits', true)->has('ledger_limits', 4)
                ->where('ledger_limits.3.permission', UserPermissionEnum::TAKE_WALLET_MONEY)->where('ledger_limits.3.granted', true)->where('ledger_limits.3.monthly_limit', '300.00'));
    }
}
