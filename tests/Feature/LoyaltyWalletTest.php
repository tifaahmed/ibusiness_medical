<?php

namespace Tests\Feature;

use App\Models\LoyaltyTransaction;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\LoyaltyPoints;
use App\Support\LoyaltyWallet;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoyaltyWalletTest extends TestCase
{
    use RefreshDatabase;

    private function member(int $points): User
    {
        $user = User::factory()->create();
        LoyaltyTransaction::query()->create(['user_id' => $user->id, 'source' => 'admin', 'client_id' => 'seed', 'points' => $points]);
        Sanctum::actingAs($user);

        return $user;
    }

    private function open(int $perEgp = 10, int $min = 10): void
    {
        SiteSettings::put(LoyaltyWallet::ENABLED, '1', Setting::TYPE_BOOLEAN);
        SiteSettings::put(LoyaltyWallet::MIN_EGP, (string) $min, Setting::TYPE_NUMBER);
        SiteSettings::put(LoyaltyPoints::POINTS_PER_EGP, (string) $perEgp, Setting::TYPE_NUMBER);
        SiteSettings::forget();
    }

    /** @test */
    public function the_points_show_their_value_in_pounds_even_while_converting_is_closed(): void
    {
        $this->member(1234);

        $data = $this->getJson('/api/v1/loyalty')->assertOk()->json('data');
        $this->assertEquals(123.4, $data['egp_value']);
        $this->assertSame(123, $data['convertible_egp']);
        $this->assertFalse($data['wallet_enabled']);
        $this->assertEquals(0, $data['wallet_balance']);
    }

    /** @test */
    public function converting_is_refused_until_the_admin_turns_it_on(): void
    {
        $user = $this->member(1000);

        $this->postJson('/api/v1/loyalty/convert', ['egp' => 50, 'id' => 'a'])->assertStatus(422)->assertJsonPath('reason', 'disabled');
        $this->assertSame(1000, LoyaltyTransaction::balanceOf($user->id));
        $this->assertSame(0, WalletTransaction::query()->count());
    }

    /** @test */
    public function points_become_pounds_in_the_wallet_and_the_points_are_spent(): void
    {
        $this->open();
        $user = $this->member(1000);

        $this->postJson('/api/v1/loyalty/convert', ['egp' => 50, 'id' => 'a'])
            ->assertOk()->assertJsonPath('data.converted_egp', 50)->assertJsonPath('data.spent_points', 500)
            ->assertJsonPath('data.points', 500)->assertJsonPath('data.wallet_balance', 50);

        $this->assertSame(500, LoyaltyTransaction::balanceOf($user->id));
        $this->assertEquals(50, WalletTransaction::balanceOf($user->id));
        $this->getJson('/api/v1/wallet')->assertOk()->assertJsonPath('data.balance', 50)->assertJsonPath('data.entries.0.amount', 50);
        $this->assertSame('wallet', collect($this->getJson('/api/v1/loyalty/history')->json('data.entries'))->first()['type']);
    }

    /** @test */
    public function the_same_request_twice_converts_once(): void
    {
        $this->open();
        $user = $this->member(1000);

        $this->postJson('/api/v1/loyalty/convert', ['egp' => 30, 'id' => 'same'])->assertOk()->assertJsonPath('data.duplicate', false);
        $this->postJson('/api/v1/loyalty/convert', ['egp' => 30, 'id' => 'same'])->assertOk()->assertJsonPath('data.duplicate', true);

        $this->assertSame(700, LoyaltyTransaction::balanceOf($user->id));
        $this->assertEquals(30, WalletTransaction::balanceOf($user->id));
    }

    /** @test */
    public function without_an_admin_value_one_hundred_points_are_one_pound(): void
    {
        $this->assertSame(100.0, LoyaltyPoints::pointsPerEgp());
        $this->assertSame(0, LoyaltyWallet::wholeEgp(99));
        $this->assertSame(1, LoyaltyWallet::wholeEgp(100));
        $this->assertSame(2000, LoyaltyWallet::pointsFor(LoyaltyWallet::minEgp()), 'the 20 pound minimum costs 2000 points');
    }

    /** @test */
    public function without_an_admin_value_the_points_must_be_worth_twenty_pounds_to_convert(): void
    {
        SiteSettings::put(LoyaltyWallet::ENABLED, '1', Setting::TYPE_BOOLEAN);
        SiteSettings::put(LoyaltyPoints::POINTS_PER_EGP, '10', Setting::TYPE_NUMBER);
        SiteSettings::forget();
        $user = $this->member(300);

        $this->assertSame(20, LoyaltyWallet::minEgp());
        $this->postJson('/api/v1/loyalty/convert', ['egp' => 19, 'id' => 'nineteen'])->assertStatus(422)->assertJsonPath('reason', 'below_minimum');
        $this->postJson('/api/v1/loyalty/convert', ['egp' => 20, 'id' => 'twenty'])->assertOk();

        $this->assertSame(100, LoyaltyTransaction::balanceOf($user->id));
        $this->assertEquals(20, WalletTransaction::balanceOf($user->id));
    }

    /** @test */
    public function it_refuses_less_than_the_minimum_and_more_than_the_member_has(): void
    {
        $this->open(10, 20);
        $user = $this->member(300);

        $this->postJson('/api/v1/loyalty/convert', ['egp' => 10, 'id' => 'low'])->assertStatus(422)->assertJsonPath('reason', 'below_minimum');
        $this->postJson('/api/v1/loyalty/convert', ['egp' => 31, 'id' => 'high'])->assertStatus(422)->assertJsonPath('reason', 'not_enough_points');
        $this->postJson('/api/v1/loyalty/convert', ['egp' => 30, 'id' => 'all'])->assertOk();

        $this->assertSame(0, LoyaltyTransaction::balanceOf($user->id));
        $this->postJson('/api/v1/loyalty/convert', ['egp' => 20, 'id' => 'none'])->assertStatus(422)->assertJsonPath('reason', 'not_enough_points');
    }

    /** @test */
    public function the_rate_is_the_admin_setting_and_a_member_cannot_spend_points_they_do_not_have(): void
    {
        $this->open(4, 1);
        $user = $this->member(100);

        $this->postJson('/api/v1/loyalty/convert', ['egp' => 25, 'id' => 'r'])->assertOk()->assertJsonPath('data.spent_points', 100);
        $this->assertSame(0, LoyaltyTransaction::balanceOf($user->id));
        $this->postJson('/api/v1/loyalty/convert', ['egp' => 1, 'id' => 'r2'])->assertStatus(422);
    }

    /** @test */
    public function it_needs_a_token(): void
    {
        $this->postJson('/api/v1/loyalty/convert', ['egp' => 10, 'id' => 'x'])->assertUnauthorized();
        $this->getJson('/api/v1/wallet')->assertUnauthorized();
    }
}
