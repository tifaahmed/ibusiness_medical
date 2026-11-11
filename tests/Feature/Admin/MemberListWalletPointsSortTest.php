<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserPermissionEnum;
use App\Models\LoyaltyTransaction;
use App\Models\Membership;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** The members list shows each member's wallet money and loyalty points and sorts by them, highest or lowest first. */
class MemberListWalletPointsSortTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name, float $wallet, int $points): User
    {
        $u = User::factory()->create(['name' => $name]);
        Membership::factory()->create(['user_id' => $u->id, 'is_active' => true, 'is_visible' => true, 'completed_at' => now()]);
        if ($wallet != 0) {
            WalletTransaction::query()->create(['user_id' => $u->id, 'source' => 'admin', 'client_id' => 'w'.$u->id, 'amount' => $wallet]);
        }
        if ($points != 0) {
            LoyaltyTransaction::query()->create(['user_id' => $u->id, 'source' => 'admin', 'client_id' => 'p'.$u->id, 'points' => $points]);
        }

        return $u;
    }

    private function names(string $sort, string $direction): array
    {
        $admin = User::factory()->create();
        foreach (UserPermissionEnum::all() as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $admin->givePermissionTo(UserPermissionEnum::all());
        $admin->assignRole(\Spatie\Permission\Models\Role::findOrCreate('super_admin', 'web'));

        $response = $this->actingAs($admin)->get(route('admin.user.membership.list', ['sort' => $sort, 'direction' => $direction, 'per_page' => 50]));
        $response->assertOk();
        $rows = collect($response->viewData('page')['props']['users']['data'])->whereIn('name', ['Low', 'Mid', 'High']);

        return [$rows->pluck('name')->values()->all(), $rows->keyBy('name')];
    }

    public function test_balances_are_shown_and_the_list_sorts_both_ways(): void
    {
        $this->member('Low', 5.5, 10);
        $this->member('High', 900, 5000);
        $this->member('Mid', 100, 300);

        [$names, $rows] = $this->names('wallet', 'desc');
        $this->assertSame(['High', 'Mid', 'Low'], $names);
        $this->assertEquals(900, $rows['High']['wallet_balance']);
        $this->assertSame(5000, $rows['High']['loyalty_points']);

        [$names] = $this->names('wallet', 'asc');
        $this->assertSame(['Low', 'Mid', 'High'], $names);

        [$names] = $this->names('points', 'desc');
        $this->assertSame(['High', 'Mid', 'Low'], $names);
        [$names] = $this->names('points', 'asc');
        $this->assertSame(['Low', 'Mid', 'High'], $names);
    }
}
