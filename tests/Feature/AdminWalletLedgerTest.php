<?php

namespace Tests\Feature;

use App\Models\LoyaltyTransaction;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** /admin/loyalty: every member's points and wallet money, and every by-hand change recorded with who made it and why. */
class AdminWalletLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $name = 'Admin One'): User
    {
        $admin = User::factory()->create(['name' => $name]);
        $admin->givePermissionTo(Permission::findOrCreate('manage memberships', 'web'));

        return $admin;
    }

    private function credit(User $member, float $amount): void
    {
        WalletTransaction::query()->create(['user_id' => $member->id, 'source' => 'topup', 'client_id' => 'seed:'.uniqid(), 'amount' => $amount]);
    }

    /** @test */
    public function the_page_lists_members_with_their_points_and_wallet_without_a_search(): void
    {
        $member = User::factory()->create(['name' => 'Holder']);
        User::factory()->create(['name' => 'Nobody']);
        $this->credit($member, 75.5);
        LoyaltyTransaction::query()->create(['user_id' => $member->id, 'source' => 'admin', 'client_id' => 'x1', 'points' => 120, 'answered_at' => now()]);

        $this->actingAs($this->admin())->get(route('admin.loyalty.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('members', fn ($rows) => collect($rows)->contains(fn ($r) => $r['name'] === 'Holder' && $r['balance'] == 120 && $r['wallet_balance'] == 75.5)
                && ! collect($rows)->contains(fn ($r) => $r['name'] === 'Nobody')));
    }

    /** @test */
    public function an_admin_wallet_change_is_a_new_line_with_the_admin_and_the_reason(): void
    {
        $admin = $this->admin('Sara Admin');
        $member = User::factory()->create();
        $this->credit($member, 100);

        $this->actingAs($admin)->post(route('admin.loyalty.wallet.adjust'), ['user_id' => $member->id, 'amount' => 25.5, 'note' => 'Goodwill after a late order'])->assertRedirect();
        $this->actingAs($admin)->post(route('admin.loyalty.wallet.adjust'), ['user_id' => $member->id, 'amount' => -10, 'note' => 'Correction'])->assertRedirect();

        $this->assertSame(115.5, WalletTransaction::balanceOf($member->id));
        $this->assertDatabaseHas('wallet_transactions', ['user_id' => $member->id, 'source' => 'admin', 'amount' => 25.5, 'note' => 'Goodwill after a late order', 'created_by' => $admin->id]);
        $this->assertSame(3, WalletTransaction::query()->where('user_id', $member->id)->count(), 'nothing is edited, every change is a line');

        $this->actingAs($admin)->get(route('admin.loyalty.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('wallet_adjustments', fn ($rows) => collect($rows)->contains(fn ($r) => $r['by'] === 'Sara Admin' && $r['amount'] == 25.5 && $r['note'] === 'Goodwill after a late order')));
    }

    /** @test */
    public function a_reason_is_required_and_the_wallet_never_goes_below_zero(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create();
        $this->credit($member, 20);

        $this->actingAs($admin)->post(route('admin.loyalty.wallet.adjust'), ['user_id' => $member->id, 'amount' => 5])->assertSessionHasErrors('note');
        $this->actingAs($admin)->post(route('admin.loyalty.wallet.adjust'), ['user_id' => $member->id, 'amount' => 0, 'note' => 'x'])->assertSessionHasErrors('amount');
        $this->actingAs($admin)->post(route('admin.loyalty.wallet.adjust'), ['user_id' => $member->id, 'amount' => -20.01, 'note' => 'too much'])->assertSessionHas('error');
        $this->actingAs($admin)->post(route('admin.loyalty.grants.store'), ['user_id' => $member->id, 'points' => 5])->assertSessionHasErrors('note');

        $this->assertSame(20.0, WalletTransaction::balanceOf($member->id));
        $this->assertSame(0, LoyaltyTransaction::query()->where('source', 'admin')->count());
    }

    /** @test */
    public function only_staff_who_manage_memberships_can_see_or_change_it(): void
    {
        $member = User::factory()->create();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->post(route('admin.loyalty.wallet.adjust'), ['user_id' => $member->id, 'amount' => 5, 'note' => 'x'])->assertForbidden();
        $this->actingAs($outsider)->getJson(route('admin.loyalty.members.ledger', $member))->assertForbidden();
        $this->assertSame(0, WalletTransaction::query()->count());
    }

    /** @test */
    public function the_ledger_shows_both_histories_with_who_made_each_hand_change(): void
    {
        $admin = $this->admin('Mona Admin');
        $member = User::factory()->create();
        $this->credit($member, 50);
        $this->actingAs($admin)->post(route('admin.loyalty.grants.store'), ['user_id' => $member->id, 'points' => 300, 'note' => 'Eid gift']);
        $this->actingAs($admin)->post(route('admin.loyalty.wallet.adjust'), ['user_id' => $member->id, 'amount' => 10, 'note' => 'Refund by hand']);

        $json = $this->actingAs($admin)->getJson(route('admin.loyalty.members.ledger', $member))->assertOk()->json();

        $this->assertSame(300, $json['balance']);
        $this->assertSame(60.0, (float) $json['wallet_balance']);
        $rows = collect($json['data']);
        $this->assertCount(3, $rows);
        $this->assertTrue($rows->contains(fn ($r) => $r['ledger'] === 'points' && $r['value'] === 300 && $r['by'] === 'Mona Admin' && $r['note'] === 'Eid gift'));
        $this->assertTrue($rows->contains(fn ($r) => $r['ledger'] === 'wallet' && $r['value'] == 10 && $r['by'] === 'Mona Admin' && $r['note'] === 'Refund by hand'));
        $this->assertTrue($rows->contains(fn ($r) => $r['ledger'] === 'wallet' && $r['source'] === 'topup' && $r['by'] === null));
    }
}
