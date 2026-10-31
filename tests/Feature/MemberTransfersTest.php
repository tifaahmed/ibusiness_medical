<?php

namespace Tests\Feature;

use App\Models\LoyaltyTransaction;
use App\Models\Membership;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\MemberTransfers;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberTransfersTest extends TestCase
{
    use RefreshDatabase;

    private function open(): void
    {
        SiteSettings::put(MemberTransfers::ENABLED, '1', Setting::TYPE_BOOLEAN);
        SiteSettings::forget();
    }

    private function member(string $phone, int $points = 0, float $wallet = 0): array
    {
        $user = User::factory()->create(['phone' => $phone]);
        $card = Membership::factory()->create(['user_id' => $user->id]);
        if ($points) {
            LoyaltyTransaction::query()->create(['user_id' => $user->id, 'source' => 'admin', 'client_id' => 'seed', 'points' => $points]);
        }
        if ($wallet) {
            WalletTransaction::query()->create(['user_id' => $user->id, 'source' => 'admin', 'client_id' => 'seed', 'amount' => $wallet]);
        }

        return [$user, $card];
    }

    /** @test */
    public function it_is_closed_until_the_admin_opens_it_and_needs_a_token(): void
    {
        [$a] = $this->member('01011111111', 500);
        [$b] = $this->member('01022222222');
        $this->postJson('/api/v1/transfers', ['phone' => '01022222222', 'kind' => 'points', 'amount' => 100, 'id' => 'x'])->assertUnauthorized();

        Sanctum::actingAs($a);
        $this->postJson('/api/v1/transfers', ['phone' => '01022222222', 'kind' => 'points', 'amount' => 100, 'id' => 'x'])->assertStatus(422)->assertJsonPath('reason', 'disabled');
        $this->assertSame(0, LoyaltyTransaction::balanceOf($b->id));
    }

    /** @test */
    public function nothing_moves_until_the_recipient_accepts_then_both_balances_change_once(): void
    {
        $this->open();
        [$a] = $this->member('01011111111', 500);
        [$b] = $this->member('01022222222');

        Sanctum::actingAs($a);
        $id = $this->postJson('/api/v1/transfers', ['phone' => '01022222222', 'kind' => 'points', 'amount' => 200, 'id' => 't1'])->assertOk()->json('data.transfer.id');
        $this->assertSame(500, LoyaltyTransaction::balanceOf($a->id));
        $this->assertSame(0, LoyaltyTransaction::balanceOf($b->id));

        // The sender cannot accept their own request.
        $this->postJson("/api/v1/transfers/$id/accept")->assertStatus(404);

        Sanctum::actingAs($b);
        $this->getJson('/api/v1/transfers')->assertOk()->assertJsonPath('data.transfers.0.direction', 'in')->assertJsonPath('data.transfers.0.amount', 200);
        $this->postJson("/api/v1/transfers/$id/accept")->assertOk();
        $this->postJson("/api/v1/transfers/$id/accept")->assertStatus(422)->assertJsonPath('reason', 'not_open');

        $this->assertSame(300, LoyaltyTransaction::balanceOf($a->id));
        $this->assertSame(200, LoyaltyTransaction::balanceOf($b->id));
    }

    /** @test */
    public function wallet_money_moves_the_same_way_and_the_recipient_can_be_found_by_card_number(): void
    {
        $this->open();
        [$a] = $this->member('01011111111', 0, 80);
        [$b, $cardB] = $this->member('01022222222');

        Sanctum::actingAs($a);
        $this->postJson('/api/v1/transfers/lookup', ['card' => $cardB->membership_number])->assertOk();
        $id = $this->postJson('/api/v1/transfers', ['card' => $cardB->membership_number, 'kind' => 'wallet', 'amount' => 30.5, 'id' => 'w1'])->assertOk()->json('data.transfer.id');

        Sanctum::actingAs($b);
        $this->postJson("/api/v1/transfers/$id/accept")->assertOk();

        $this->assertEquals(49.5, WalletTransaction::balanceOf($a->id));
        $this->assertEquals(30.5, WalletTransaction::balanceOf($b->id));
    }

    /** @test */
    public function a_declined_or_cancelled_request_moves_nothing_and_cannot_be_accepted_later(): void
    {
        $this->open();
        [$a] = $this->member('01011111111', 500);
        [$b] = $this->member('01022222222');

        Sanctum::actingAs($a);
        $one = $this->postJson('/api/v1/transfers', ['phone' => '01022222222', 'kind' => 'points', 'amount' => 100, 'id' => 'c1'])->json('data.transfer.id');
        $two = $this->postJson('/api/v1/transfers', ['phone' => '01022222222', 'kind' => 'points', 'amount' => 100, 'id' => 'c2'])->json('data.transfer.id');
        $this->postJson("/api/v1/transfers/$one/cancel")->assertOk();

        Sanctum::actingAs($b);
        $this->postJson("/api/v1/transfers/$one/accept")->assertStatus(422);
        $this->postJson("/api/v1/transfers/$two/decline")->assertOk();
        $this->postJson("/api/v1/transfers/$two/accept")->assertStatus(422);

        $this->assertSame(500, LoyaltyTransaction::balanceOf($a->id));
        $this->assertSame(0, LoyaltyTransaction::balanceOf($b->id));
    }

    /** @test */
    public function it_checks_the_balance_when_asked_and_again_when_accepted_and_refuses_expired_ones(): void
    {
        $this->open();
        [$a] = $this->member('01011111111', 100);
        [$b] = $this->member('01022222222');

        Sanctum::actingAs($a);
        $this->postJson('/api/v1/transfers', ['phone' => '01022222222', 'kind' => 'points', 'amount' => 101, 'id' => 'big'])->assertStatus(422)->assertJsonPath('reason', 'not_enough');
        $this->postJson('/api/v1/transfers', ['phone' => '01099999999', 'kind' => 'points', 'amount' => 10, 'id' => 'who'])->assertStatus(422)->assertJsonPath('reason', 'not_found');
        $this->postJson('/api/v1/transfers', ['phone' => '01011111111', 'kind' => 'points', 'amount' => 10, 'id' => 'me'])->assertStatus(422)->assertJsonPath('reason', 'not_found');

        $id = $this->postJson('/api/v1/transfers', ['phone' => '01022222222', 'kind' => 'points', 'amount' => 100, 'id' => 'ok'])->json('data.transfer.id');
        // The sender spends the points in the meantime.
        LoyaltyTransaction::query()->create(['user_id' => $a->id, 'source' => 'admin', 'client_id' => 'spend', 'points' => -50]);

        Sanctum::actingAs($b);
        $this->postJson("/api/v1/transfers/$id/accept")->assertStatus(422)->assertJsonPath('reason', 'not_enough');

        LoyaltyTransaction::query()->create(['user_id' => $a->id, 'source' => 'admin', 'client_id' => 'back', 'points' => 50]);
        \App\Models\WalletTransfer::query()->whereKey($id)->update(['expires_at' => now()->subMinute()]);
        $this->postJson("/api/v1/transfers/$id/accept")->assertStatus(422)->assertJsonPath('reason', 'not_open');
        $this->assertSame(0, LoyaltyTransaction::balanceOf($b->id));
    }

    /** @test */
    public function a_repeated_request_books_once(): void
    {
        $this->open();
        [$a] = $this->member('01011111111', 500);
        $this->member('01022222222');

        Sanctum::actingAs($a);
        $this->postJson('/api/v1/transfers', ['phone' => '01022222222', 'kind' => 'points', 'amount' => 100, 'id' => 'same'])->assertOk()->assertJsonPath('data.duplicate', false);
        $this->postJson('/api/v1/transfers', ['phone' => '01022222222', 'kind' => 'points', 'amount' => 100, 'id' => 'same'])->assertOk()->assertJsonPath('data.duplicate', true);
        $this->assertSame(1, \App\Models\WalletTransfer::query()->count());
    }
}
