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
        SiteSettings::put(MemberTransfers::POINTS_ENABLED, '1', Setting::TYPE_BOOLEAN);
        SiteSettings::put(MemberTransfers::WALLET_ENABLED, '1', Setting::TYPE_BOOLEAN);
        // These tests are about WHO and WHEN; the fee has its own test below.
        SiteSettings::put(MemberTransfers::FEE_PERCENT, '0', Setting::TYPE_NUMBER);
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
    public function a_transfer_moves_at_once_without_the_recipient_accepting_and_books_both_balances_once(): void
    {
        $this->open();
        [$a] = $this->member('01011111111', 500);
        [$b] = $this->member('01022222222');

        Sanctum::actingAs($a);
        $id = $this->postJson('/api/v1/transfers', ['phone' => '01022222222', 'kind' => 'points', 'amount' => 200, 'id' => 't1'])
            ->assertOk()->assertJsonPath('data.transfer.status', 'accepted')->json('data.transfer.id');
        $this->assertSame(300, LoyaltyTransaction::balanceOf($a->id));
        $this->assertSame(200, LoyaltyTransaction::balanceOf($b->id));

        // Nothing is left to accept, for either side.
        $this->postJson("/api/v1/transfers/$id/accept")->assertStatus(404);
        Sanctum::actingAs($b);
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
        $this->postJson('/api/v1/transfers', ['card' => $cardB->membership_number, 'kind' => 'wallet', 'amount' => 30.5, 'id' => 'w1'])->assertOk();

        $this->assertEquals(49.5, WalletTransaction::balanceOf($a->id));
        $this->assertEquals(30.5, WalletTransaction::balanceOf($b->id));
    }

    /** @test */
    public function it_refuses_more_than_the_balance_an_unknown_number_and_yourself_and_moves_nothing(): void
    {
        $this->open();
        [$a] = $this->member('01011111111', 100);
        [$b] = $this->member('01022222222');

        Sanctum::actingAs($a);
        $this->postJson('/api/v1/transfers', ['phone' => '01022222222', 'kind' => 'points', 'amount' => 101, 'id' => 'big'])->assertStatus(422)->assertJsonPath('reason', 'not_enough');
        $this->postJson('/api/v1/transfers', ['phone' => '01099999999', 'kind' => 'points', 'amount' => 10, 'id' => 'who'])->assertStatus(422)->assertJsonPath('reason', 'not_found');
        $this->postJson('/api/v1/transfers', ['phone' => '01011111111', 'kind' => 'points', 'amount' => 10, 'id' => 'me'])->assertStatus(422)->assertJsonPath('reason', 'not_found');

        $this->assertSame(100, LoyaltyTransaction::balanceOf($a->id));
        $this->assertSame(0, LoyaltyTransaction::balanceOf($b->id));
    }

    /** @test */
    public function a_repeated_request_books_once(): void
    {
        $this->open();
        [$a] = $this->member('01011111111', 500);
        [$b] = $this->member('01022222222');

        Sanctum::actingAs($a);
        $this->postJson('/api/v1/transfers', ['phone' => '01022222222', 'kind' => 'points', 'amount' => 100, 'id' => 'same'])->assertOk()->assertJsonPath('data.duplicate', false);
        $this->postJson('/api/v1/transfers', ['phone' => '01022222222', 'kind' => 'points', 'amount' => 100, 'id' => 'same'])->assertOk()->assertJsonPath('data.duplicate', true);
        $this->assertSame(1, \App\Models\WalletTransfer::query()->count());
        $this->assertSame(400, LoyaltyTransaction::balanceOf($a->id));
        $this->assertSame(100, LoyaltyTransaction::balanceOf($b->id));
    }

    /** @test */
    public function points_and_wallet_transfers_have_their_own_switches(): void
    {
        $this->open();
        SiteSettings::put(MemberTransfers::WALLET_ENABLED, '0', Setting::TYPE_BOOLEAN);
        SiteSettings::forget();
        [$a] = $this->member('01011111111', 500, 100);
        $this->member('01022222222');

        Sanctum::actingAs($a);
        $this->postJson('/api/v1/transfers', ['phone' => '01022222222', 'kind' => 'wallet', 'amount' => 10, 'id' => 'w1'])->assertStatus(422)->assertJsonPath('reason', 'kind_disabled');
        $this->postJson('/api/v1/transfers', ['phone' => '01022222222', 'kind' => 'points', 'amount' => 10, 'id' => 'p1'])->assertOk();
        $this->getJson('/api/v1/transfers')->assertJsonPath('data.kinds.points', true)->assertJsonPath('data.kinds.wallet', false);
    }

    /** @test */
    public function half_is_kept_as_a_transfer_fee_by_default_and_the_sender_is_told_before_and_after(): void
    {
        $this->open();
        SiteSettings::put(MemberTransfers::FEE_PERCENT, '50', Setting::TYPE_NUMBER);
        SiteSettings::forget();
        [$a] = $this->member('01011111111', 500, 100);
        [$b] = $this->member('01022222222');
        Sanctum::actingAs($a);

        // Before sending: the lookup previews the fee and what arrives.
        $this->postJson('/api/v1/transfers/lookup', ['phone' => '01022222222', 'kind' => 'points', 'amount' => 201])->assertOk()
            ->assertJsonPath('data.fee_percent', 50)->assertJsonPath('data.preview.fee', 101)->assertJsonPath('data.preview.net', 100);

        $this->postJson('/api/v1/transfers', ['phone' => '01022222222', 'kind' => 'points', 'amount' => 200, 'id' => 'p1'])->assertOk()
            ->assertJsonPath('data.transfer.fee', 100)->assertJsonPath('data.transfer.net', 100);
        $this->postJson('/api/v1/transfers', ['phone' => '01022222222', 'kind' => 'wallet', 'amount' => 40, 'id' => 'w1'])->assertOk()
            ->assertJsonPath('data.transfer.fee', 20)->assertJsonPath('data.transfer.net', 20);

        $this->assertSame(300, LoyaltyTransaction::balanceOf($a->id));
        $this->assertSame(100, LoyaltyTransaction::balanceOf($b->id));
        $this->assertEquals(60, WalletTransaction::balanceOf($a->id));
        $this->assertEquals(20, WalletTransaction::balanceOf($b->id));
    }

    /** @test */
    public function the_recipient_gets_a_push_on_their_devices_when_points_arrive(): void
    {
        $this->open();
        [$a] = $this->member('01011111111', 500);
        [$b] = $this->member('01022222222');
        \App\Models\DevicePushToken::query()->create(['user_id' => $b->id, 'token_hash' => hash('sha256', 'tok-b'), 'token' => 'tok-b', 'platform' => 'android', 'locale' => 'ar']);
        \App\Models\DevicePushToken::query()->create(['user_id' => $a->id, 'token_hash' => hash('sha256', 'tok-a'), 'token' => 'tok-a', 'platform' => 'android', 'locale' => 'ar']);

        $sent = [];
        $fake = \Mockery::mock(\App\Services\Push\FcmClient::class);
        $fake->shouldReceive('isConfigured')->andReturn(true);
        $fake->shouldReceive('sendMany')->andReturnUsing(function (array $tokens, string $title, string $body, array $data = []) use (&$sent) {
            $sent[] = compact('tokens', 'title', 'body', 'data');

            return array_fill_keys($tokens, 'ok');
        });
        $this->app->instance(\App\Services\Push\FcmClient::class, $fake);

        Sanctum::actingAs($a);
        $this->postJson('/api/v1/transfers', ['phone' => '01022222222', 'kind' => 'points', 'amount' => 200, 'id' => 'push1'])->assertOk();

        $this->assertCount(1, $sent);
        $this->assertSame(['tok-b'], $sent[0]['tokens']);
        $this->assertStringContainsString('200', $sent[0]['body']);
        $this->assertSame('/account/share', $sent[0]['data']['url']);
    }
}
