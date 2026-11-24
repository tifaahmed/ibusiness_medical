<?php

namespace Tests\Feature;

use App\Models\LoyaltyTransaction;
use App\Models\Membership;
use App\Models\MembershipSubscription;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\MembershipPlan;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Loyalty points and the wallet as a discount on a membership payment: only on a press, one give-back, full cover activates. */
class MembershipDiscountsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Membership $card;

    protected function setUp(): void
    {
        parent::setUp();

        $this->showAllPaymentMethods();
        SiteSettings::put(MembershipPlan::PRICE, 600, Setting::TYPE_NUMBER);
        SiteSettings::put(MembershipPlan::MONTHS, 12, Setting::TYPE_NUMBER);
        SiteSettings::put('loyalty_points_per_egp', 100, Setting::TYPE_NUMBER);
        SiteSettings::forget();

        $this->user = User::factory()->create();
        $this->card = Membership::factory()->create(['user_id' => $this->user->id, 'is_active' => true, 'is_visible' => true, 'plan' => Membership::PLAN_TRIAL, 'expiration_date' => now()->addDays(5)]);
        Sanctum::actingAs($this->user);

        LoyaltyTransaction::query()->create(['user_id' => $this->user->id, 'source' => 'admin', 'client_id' => 'seed', 'points' => 10000, 'answered_at' => now()]); // 100 EGP
        WalletTransaction::query()->create(['user_id' => $this->user->id, 'source' => 'admin', 'client_id' => 'seed-w', 'amount' => 50]);
    }

    /** @test */
    public function the_quote_shows_what_a_press_takes_off_and_writes_nothing(): void
    {
        $this->postJson('/api/v1/membership/quote', ['plan' => 'year'])->assertOk()->assertJsonPath('data.due', 600)->assertJsonPath('data.points_amount', 0);
        $this->postJson('/api/v1/membership/quote', ['plan' => 'year', 'use_points' => true, 'use_wallet' => true])
            ->assertOk()
            ->assertJsonPath('data.points_amount', 100)
            ->assertJsonPath('data.wallet_amount', 50)
            ->assertJsonPath('data.due', 450);

        $this->assertSame(10000, LoyaltyTransaction::balanceOf($this->user->id));
        $this->assertDatabaseCount('membership_subscriptions', 0);
    }

    /** @test */
    public function a_press_takes_points_and_wallet_and_a_rejected_payment_gives_them_back_once(): void
    {
        $this->postJson('/api/v1/membership/subscribe', ['method' => 'cash_on_delivery', 'plan' => 'year', 'use_points' => true, 'use_wallet' => true])->assertCreated();

        $sub = MembershipSubscription::query()->firstOrFail();
        $this->assertEquals(450, $sub->amount);
        $this->assertSame(10000, $sub->points_used);
        $this->assertSame(0, LoyaltyTransaction::balanceOf($this->user->id));
        $this->assertEquals(0, WalletTransaction::balanceOf($this->user->id));

        $sub->update(['status' => MembershipSubscription::STATUS_REJECTED]);
        $sub->update(['status' => MembershipSubscription::STATUS_CANCELED]);

        $this->assertSame(10000, LoyaltyTransaction::balanceOf($this->user->id));
        $this->assertEquals(50, WalletTransaction::balanceOf($this->user->id));
    }

    /** @test */
    public function nothing_is_spent_without_a_press(): void
    {
        $this->postJson('/api/v1/membership/subscribe', ['method' => 'cash_on_delivery', 'plan' => 'year'])->assertCreated();

        $this->assertEquals(600, MembershipSubscription::query()->value('amount'));
        $this->assertSame(10000, LoyaltyTransaction::balanceOf($this->user->id));
    }

    /** @test */
    public function a_new_attempt_returns_the_discount_of_the_older_open_one(): void
    {
        $this->postJson('/api/v1/membership/subscribe', ['method' => 'cash_on_delivery', 'plan' => 'year', 'use_points' => true])->assertCreated();
        $this->assertSame(0, LoyaltyTransaction::balanceOf($this->user->id));

        $this->postJson('/api/v1/membership/subscribe', ['method' => 'cash_on_delivery', 'plan' => 'year'])->assertCreated();
        $this->assertSame(10000, LoyaltyTransaction::balanceOf($this->user->id));
        $this->assertEquals(600, MembershipSubscription::query()->latest('id')->value('amount'));
    }

    /** @test */
    public function when_points_and_wallet_cover_everything_the_card_is_activated_at_once(): void
    {
        LoyaltyTransaction::query()->create(['user_id' => $this->user->id, 'source' => 'admin', 'client_id' => 'more', 'points' => 50000, 'answered_at' => now()]); // 500 more
        $this->postJson('/api/v1/membership/subscribe', ['method' => 'paymob', 'plan' => 'year', 'use_points' => true, 'use_wallet' => true])->assertCreated();

        $this->assertSame(Membership::PLAN_FULL, $this->card->fresh()->plan);
        $this->assertTrue($this->card->fresh()->expiration_date->isAfter(now()->addMonths(11)));
        $this->assertEquals(0, MembershipSubscription::query()->value('amount'));
        $this->assertEquals(50, WalletTransaction::balanceOf($this->user->id)); // points covered all 600
    }
}
