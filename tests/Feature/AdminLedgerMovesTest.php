<?php

namespace Tests\Feature;

use App\Models\LoyaltyTransaction;
use App\Models\Membership;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\LoyaltyPoints;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** An admin moves a balance between members, or turns a member's points into wallet money, in one logged step. */
class AdminLedgerMovesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $name = 'Admin One'): User
    {
        $admin = User::factory()->create(['name' => $name]);
        $admin->givePermissionTo(Permission::findOrCreate('manage memberships', 'web'));

        return $admin;
    }

    private function member(string $phone, float $wallet = 0, int $points = 0): User
    {
        $user = User::factory()->create(['phone' => $phone]);
        if ($wallet) {
            WalletTransaction::query()->create(['user_id' => $user->id, 'source' => 'topup', 'client_id' => 'seed:'.uniqid(), 'amount' => $wallet]);
        }
        if ($points) {
            LoyaltyTransaction::query()->create(['user_id' => $user->id, 'source' => 'admin', 'client_id' => 'seed:'.uniqid(), 'points' => $points, 'answered_at' => now()]);
        }

        return $user;
    }

    private function rate(int $perEgp = 100): void
    {
        SiteSettings::put(LoyaltyPoints::POINTS_PER_EGP, (string) $perEgp, Setting::TYPE_NUMBER);
        SiteSettings::forget();
    }

    /** @test */
    public function wallet_money_moves_between_members_in_one_step_with_the_admin_and_reason_on_both_lines(): void
    {
        $admin = $this->admin('Sara Admin');
        $from = $this->member('01011111111', wallet: 100);
        $to = $this->member('01022222222');

        $this->actingAs($admin)->post(route('admin.loyalty.transfer'), ['user_id' => $from->id, 'to_phone' => '01022222222', 'kind' => 'wallet', 'amount' => 40.5, 'note' => 'Mistaken top-up'])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(59.5, WalletTransaction::balanceOf($from->id));
        $this->assertSame(40.5, WalletTransaction::balanceOf($to->id));
        $this->assertDatabaseHas('wallet_transactions', ['user_id' => $from->id, 'source' => 'transfer', 'amount' => -40.5, 'created_by' => $admin->id]);
        $this->assertDatabaseHas('wallet_transactions', ['user_id' => $to->id, 'source' => 'transfer', 'amount' => 40.5, 'created_by' => $admin->id]);
        $this->assertStringContainsString('Mistaken top-up', WalletTransaction::query()->where('user_id', $to->id)->value('note'));
    }

    /** @test */
    public function points_move_by_card_number_and_nothing_moves_when_the_sender_is_short(): void
    {
        $admin = $this->admin();
        $from = $this->member('01011111111', points: 500);
        $receiver = $this->member('01033333333');
        $membership = Membership::factory()->create(['user_id' => $receiver->id, 'membership_number' => '654321']);

        $this->actingAs($admin)->post(route('admin.loyalty.transfer'), ['user_id' => $from->id, 'to_card' => '654321', 'kind' => 'points', 'amount' => 200, 'note' => 'Event prize'])->assertSessionHas('success');
        $this->assertSame(300, LoyaltyTransaction::balanceOf($from->id));
        $this->assertSame(200, LoyaltyTransaction::balanceOf($receiver->id));

        $this->actingAs($admin)->post(route('admin.loyalty.transfer'), ['user_id' => $from->id, 'to_card' => '654321', 'kind' => 'points', 'amount' => 301, 'note' => 'Too much'])->assertSessionHas('error');
        $this->assertSame(300, LoyaltyTransaction::balanceOf($from->id), 'a refused move writes nothing');
        $this->assertSame(200, LoyaltyTransaction::balanceOf($receiver->id));
    }

    /** @test */
    public function a_move_needs_a_reason_another_member_and_sensible_amounts(): void
    {
        $admin = $this->admin();
        $from = $this->member('01011111111', wallet: 50);
        $this->member('01022222222');

        $base = ['user_id' => $from->id, 'to_phone' => '01022222222', 'kind' => 'wallet', 'amount' => 10, 'note' => 'ok'];

        $this->actingAs($admin)->post(route('admin.loyalty.transfer'), array_merge($base, ['note' => '']))->assertSessionHasErrors('note');
        $this->actingAs($admin)->post(route('admin.loyalty.transfer'), array_merge($base, ['amount' => 0]))->assertSessionHasErrors('amount');
        $this->actingAs($admin)->post(route('admin.loyalty.transfer'), array_merge($base, ['amount' => -5]))->assertSessionHasErrors('amount');
        $this->actingAs($admin)->post(route('admin.loyalty.transfer'), array_merge($base, ['kind' => 'gold']))->assertSessionHasErrors('kind');
        $this->actingAs($admin)->post(route('admin.loyalty.transfer'), array_merge($base, ['to_phone' => '01011111111']))->assertSessionHas('error'); // itself
        $this->actingAs($admin)->post(route('admin.loyalty.transfer'), array_merge($base, ['to_phone' => '01099999999']))->assertSessionHas('error'); // nobody
        $this->actingAs($admin)->post(route('admin.loyalty.transfer'), array_merge($base, ['kind' => 'points', 'amount' => 2.5]))->assertSessionHas('error'); // half a point
        $this->actingAs($admin)->post(route('admin.loyalty.transfer'), array_merge($base, ['amount' => 51]))->assertSessionHas('error'); // more than the wallet holds

        $this->assertSame(50.0, WalletTransaction::balanceOf($from->id));
        $this->assertSame(1, WalletTransaction::query()->count());
    }

    /** @test */
    public function an_admin_turns_points_into_wallet_money_at_the_rate_even_with_the_member_switch_off(): void
    {
        $this->rate(100);
        $admin = $this->admin('Mona Admin');
        $member = $this->member('01011111111', points: 3000);

        $this->actingAs($admin)->post(route('admin.loyalty.convert'), ['user_id' => $member->id, 'egp' => 5, 'note' => 'Customer asked by phone'])->assertSessionHas('success');

        $this->assertSame(2500, LoyaltyTransaction::balanceOf($member->id));
        $this->assertEquals(5, WalletTransaction::balanceOf($member->id));
        $this->assertDatabaseHas('loyalty_transactions', ['user_id' => $member->id, 'source' => 'wallet', 'points' => -500, 'created_by' => $admin->id]);
        $this->assertDatabaseHas('wallet_transactions', ['user_id' => $member->id, 'source' => 'points', 'amount' => 5, 'points' => 500, 'created_by' => $admin->id]);
    }

    /** @test */
    public function a_conversion_is_refused_when_points_are_short_or_no_rate_is_set_and_writes_nothing(): void
    {
        $admin = $this->admin();
        $member = $this->member('01011111111', points: 400);

        $this->rate(100);
        $this->actingAs($admin)->post(route('admin.loyalty.convert'), ['user_id' => $member->id, 'egp' => 5, 'note' => 'x'])->assertSessionHas('error');
        $this->actingAs($admin)->post(route('admin.loyalty.convert'), ['user_id' => $member->id, 'egp' => 0, 'note' => 'x'])->assertSessionHasErrors('egp');
        $this->actingAs($admin)->post(route('admin.loyalty.convert'), ['user_id' => $member->id, 'egp' => 1])->assertSessionHasErrors('note');

        $this->rate(0);
        $this->actingAs($admin)->post(route('admin.loyalty.convert'), ['user_id' => $member->id, 'egp' => 1, 'note' => 'x'])->assertSessionHas('error');

        $this->assertSame(400, LoyaltyTransaction::balanceOf($member->id));
        $this->assertSame(0.0, WalletTransaction::balanceOf($member->id));
    }

    /** @test */
    public function only_staff_who_manage_memberships_can_do_either(): void
    {
        $outsider = User::factory()->create();
        $from = $this->member('01011111111', wallet: 10, points: 1000);
        $this->member('01022222222');

        $this->actingAs($outsider)->post(route('admin.loyalty.transfer'), ['user_id' => $from->id, 'to_phone' => '01022222222', 'kind' => 'wallet', 'amount' => 5, 'note' => 'x'])->assertForbidden();
        $this->actingAs($outsider)->post(route('admin.loyalty.convert'), ['user_id' => $from->id, 'egp' => 1, 'note' => 'x'])->assertForbidden();
        $this->assertSame(1000, LoyaltyTransaction::balanceOf($from->id));
        $this->assertSame(10.0, WalletTransaction::balanceOf($from->id));
    }

    /** @test */
    public function the_member_ledger_shows_both_sides_of_a_move_with_the_admin_name(): void
    {
        $admin = $this->admin('Hana Admin');
        $from = $this->member('01011111111', wallet: 30);
        $to = $this->member('01022222222');
        $this->actingAs($admin)->post(route('admin.loyalty.transfer'), ['user_id' => $from->id, 'to_phone' => '01022222222', 'kind' => 'wallet', 'amount' => 10, 'note' => 'Correction']);

        $rows = collect($this->actingAs($admin)->getJson(route('admin.loyalty.members.ledger', $to))->assertOk()->json('data'));

        $this->assertTrue($rows->contains(fn ($r) => $r['ledger'] === 'wallet' && $r['source'] === 'transfer' && $r['value'] == 10 && $r['by'] === 'Hana Admin'));
    }
}
