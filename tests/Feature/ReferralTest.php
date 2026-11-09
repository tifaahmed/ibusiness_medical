<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\MembershipSubscription;
use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\MembershipPlan;
use App\Support\Referrals;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Member-gets-member: 10 to the referrer when the new member STARTS A TRIAL, 10% of EVERY paid subscription, 50 off the new member's first one. */
class ReferralTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->showAllPaymentMethods();
        SiteSettings::put(MembershipPlan::PRICE, 600, Setting::TYPE_NUMBER);
        SiteSettings::put(MembershipPlan::MONTHS, 12, Setting::TYPE_NUMBER);
        /* The yearly discount these older tests were written for; the per-plan tests below set their own. */
        SiteSettings::put('referral_discount_year', 50, Setting::TYPE_NUMBER);
        SiteSettings::forget();
    }

    private function member(string $number, string $phone, bool $trial = true, bool $verified = true): array
    {
        $user = User::factory()->create(['phone' => $phone, 'phone_verified_at' => $verified ? now() : null]);
        $card = Membership::factory()->create([
            'user_id' => $user->id, 'membership_number' => $number, 'is_active' => true, 'is_visible' => true,
            'plan' => Membership::PLAN_TRIAL, 'expiration_date' => now()->addDays(5),
            'trial_started_at' => $trial ? now() : null,
        ]);

        return [$user, $card];
    }

    private function wallet(User $user, float $amount): void
    {
        WalletTransaction::query()->create(['user_id' => $user->id, 'source' => 'admin', 'client_id' => 'seed'.uniqid(), 'amount' => $amount]);
    }

    /** A paid subscription of the member's card, accepted the way every method does it. */
    private function pay(Membership $card, float $amount, int $months, string $method = 'paymob'): MembershipSubscription
    {
        $sub = $card->subscriptions()->create(['method' => $method, 'status' => MembershipSubscription::STATUS_PENDING, 'amount' => $amount, 'months' => $months]);
        app(\App\Actions\Membership\ActivateSubscription::class)->handle($sub);

        return $sub->fresh();
    }

    /** @test */
    public function a_link_or_a_claim_alone_pays_nothing_the_trial_start_pays_ten_once(): void
    {
        [$referrer] = $this->member('111111', '01011111111');
        [$friend] = $this->member('222222', '01022222222', trial: false);

        Sanctum::actingAs($friend);
        $this->postJson('/api/v1/referral/claim', ['number' => '111111', 'source' => 'link'])->assertCreated();
        $this->assertSame(0.0, WalletTransaction::balanceOf($referrer->id)); // account exists, trial not started

        Membership::query()->where('user_id', $friend->id)->update(['trial_started_at' => now()]);
        Referrals::trialStarted($friend);
        Referrals::trialStarted($friend);
        Referrals::trialStarted($friend);

        $this->assertSame(10.0, WalletTransaction::balanceOf($referrer->id));
        $this->assertSame(1, ReferralReward::query()->where('kind', 'trial')->count());
        $this->assertSame(10.0, (float) Referral::query()->first()->trial_bonus);
    }

    /** @test */
    public function a_member_who_already_started_the_trial_is_rewarded_the_moment_the_code_is_accepted(): void
    {
        [$referrer] = $this->member('111111', '01011111111');
        [$friend] = $this->member('222222', '01022222222');

        Sanctum::actingAs($friend);
        $this->postJson('/api/v1/referral/claim', ['number' => '111111', 'source' => 'card'])->assertCreated();
        $this->assertSame(10.0, WalletTransaction::balanceOf($referrer->id));
    }

    /** @test */
    public function the_real_trial_start_endpoint_pays_the_referrer(): void
    {
        [$referrer] = $this->member('111111', '01011111111');
        $friend = User::factory()->create(['phone' => '01022222222', 'phone_verified_at' => now()]);
        Sanctum::actingAs($friend);
        $this->postJson('/api/v1/referral/claim', ['number' => '111111'])->assertCreated();
        $this->assertSame(0.0, WalletTransaction::balanceOf($referrer->id));

        $this->postJson('/api/v1/membership/trial', ['name' => 'Friend Name', 'gender' => 'male'])->assertSuccessful();

        $this->assertSame(10.0, WalletTransaction::balanceOf($referrer->id));
        $this->assertNotNull(Referral::query()->first()->trial_started_at);
    }

    /** @test */
    public function an_unverified_phone_earns_nothing_until_a_real_sms_code_verifies_it(): void
    {
        [$referrer] = $this->member('111111', '01011111111');
        [$friend] = $this->member('222222', '01022222222', verified: false);

        Sanctum::actingAs($friend);
        $this->postJson('/api/v1/referral/claim', ['number' => '111111'])->assertCreated();
        $this->assertSame(0.0, WalletTransaction::balanceOf($referrer->id));

        Sanctum::actingAs($referrer);
        $this->assertSame(10.0, (float) $this->getJson('/api/v1/referral')->json('data.totals.pending')); // waiting, not lost

        app(\App\Services\Otp\MemberOtp::class)->confirmPhone('01022222222');
        $this->assertSame(10.0, WalletTransaction::balanceOf($referrer->id));
        $this->assertSame(0.0, (float) $this->getJson('/api/v1/referral')->json('data.totals.pending'));
    }

    /** @test */
    public function the_commission_is_ten_percent_of_every_paid_subscription_and_renewals_too(): void
    {
        foreach (['month', 'half', 'year'] as $plan) {
            SiteSettings::put('referral_discount_'.$plan, 0, Setting::TYPE_NUMBER);
        }
        SiteSettings::forget();
        [$referrer] = $this->member('111111', '01011111111');
        [$friend, $card] = $this->member('222222', '01022222222');
        Sanctum::actingAs($friend);
        $this->postJson('/api/v1/referral/claim', ['number' => '111111'])->assertCreated();

        $this->pay($card, 100, 1);   // monthly
        $this->pay($card, 400, 6);   // half-year (renewal)
        $this->pay($card, 600, 12);  // yearly (renewal)

        $this->assertSame(10.0 + 10 + 40 + 60, WalletTransaction::balanceOf($referrer->id));
        $this->assertSame(['month', 'half', 'year'], ReferralReward::query()->where('kind', 'commission')->orderBy('id')->pluck('plan')->all());
        $this->assertSame([10.0, 40.0, 60.0], ReferralReward::query()->where('kind', 'commission')->orderBy('id')->get()->map(fn ($r) => (float) $r->amount)->all());
    }

    /** @test */
    public function the_commission_is_on_what_was_really_paid_after_the_discount(): void
    {
        [$referrer] = $this->member('111111', '01011111111');
        [$friend] = $this->member('222222', '01022222222');
        Sanctum::actingAs($friend);
        $this->postJson('/api/v1/referral/claim', ['number' => '111111', 'source' => 'link'])->assertCreated();
        $this->assertSame(50.0, (float) $this->getJson('/api/v1/membership')->json('data.referral_discount'));

        $this->wallet($friend, 1200);
        $this->postJson('/api/v1/membership/subscribe', ['method' => 'wallet', 'plan' => 'year'])->assertSuccessful(); // 600 - 50
        $this->assertSame(10.0 + 55, WalletTransaction::balanceOf($referrer->id));

        $this->postJson('/api/v1/membership/subscribe', ['method' => 'wallet', 'plan' => 'year'])->assertSuccessful(); // renewal: no discount, 600
        $this->assertSame(1200.0 - 550 - 600, WalletTransaction::balanceOf($friend->id));
        $this->assertSame(10.0 + 55 + 60, WalletTransaction::balanceOf($referrer->id));

        Sanctum::actingAs($referrer);
        $data = $this->getJson('/api/v1/referral')->assertOk()->json('data');
        $this->assertSame('111111', $data['code']);
        $this->assertSame(10.0, (float) $data['amounts']['commission_percent']);
        $this->assertSame(1, $data['totals']['registered']);
        $this->assertSame(1, $data['totals']['trials']);
        $this->assertSame(1, $data['totals']['subscribed']);
        $this->assertSame(10.0, (float) $data['totals']['trial_earned']);
        $this->assertSame(115.0, (float) $data['totals']['commission_earned']);
        $this->assertSame(125.0, (float) $data['totals']['earned']);
        $this->assertSame(125.0, (float) $data['totals']['wallet_balance']);
        $row = $data['referrals'][0];
        $this->assertSame('renewed', $row['stage']);
        $this->assertSame(2, $row['payments']);
        $this->assertSame(125.0, (float) $row['earned']);
        $this->assertCount(3, $row['rewards']); // 1 trial + 2 commissions
    }

    /** @test */
    public function one_payment_pays_one_commission_even_if_activated_or_booked_again(): void
    {
        [$referrer] = $this->member('111111', '01011111111');
        [$friend, $card] = $this->member('222222', '01022222222');
        Sanctum::actingAs($friend);
        $this->postJson('/api/v1/referral/claim', ['number' => '111111'])->assertCreated();

        $sub = $this->pay($card, 600, 12);
        app(\App\Actions\Membership\ActivateSubscription::class)->handle($sub);
        Referrals::subscriptionPaid($card->fresh(), $sub);
        Referrals::subscriptionPaid($card->fresh(), $sub);

        $this->assertSame(1, ReferralReward::query()->where('kind', 'commission')->count());
        $this->assertSame(10.0 + 60, WalletTransaction::balanceOf($referrer->id));
        $this->assertSame(1, WalletTransaction::query()->where('client_id', 'referral-commission:'.$sub->id)->count());
    }

    /** @test */
    public function an_unpaid_failed_or_rejected_payment_pays_nothing_and_a_pending_one_is_shown_as_pending(): void
    {
        [$referrer] = $this->member('111111', '01011111111');
        [$friend, $card] = $this->member('222222', '01022222222');
        Sanctum::actingAs($friend);
        $this->postJson('/api/v1/referral/claim', ['number' => '111111'])->assertCreated();

        $card->subscriptions()->create(['method' => 'vodafone_cash', 'status' => 'pending', 'amount' => 600, 'months' => 12]);
        $card->subscriptions()->create(['method' => 'paymob', 'status' => 'rejected', 'amount' => 600, 'months' => 12]);
        $card->subscriptions()->create(['method' => 'paymob', 'status' => 'canceled', 'amount' => 600, 'months' => 12]);

        $this->assertSame(10.0, WalletTransaction::balanceOf($referrer->id));

        Sanctum::actingAs($referrer);
        $this->assertSame(60.0, (float) $this->getJson('/api/v1/referral')->json('data.totals.pending')); // 10% of the one waiting 600
    }

    /** @test */
    public function a_refunded_payment_takes_its_commission_back_once(): void
    {
        [$referrer] = $this->member('111111', '01011111111');
        [$friend, $card] = $this->member('222222', '01022222222');
        Sanctum::actingAs($friend);
        $this->postJson('/api/v1/referral/claim', ['number' => '111111'])->assertCreated();

        $first = $this->pay($card, 600, 12);
        $second = $this->pay($card, 400, 6);
        $this->assertSame(10.0 + 60 + 40, WalletTransaction::balanceOf($referrer->id));

        Referrals::subscriptionRefunded($first);
        Referrals::subscriptionRefunded($first);

        $this->assertSame(10.0 + 40, WalletTransaction::balanceOf($referrer->id));
        $this->assertSame('reversed', ReferralReward::query()->where('reward_key', 'commission:'.$first->id)->first()->status);
        $this->assertSame('paid', ReferralReward::query()->where('reward_key', 'commission:'.$second->id)->first()->status);

        Sanctum::actingAs($referrer);
        $this->assertSame(40.0, (float) $this->getJson('/api/v1/referral')->json('data.totals.commission_earned'));
    }

    /** @test */
    public function a_refund_never_takes_the_wallet_below_zero(): void
    {
        [$referrer] = $this->member('111111', '01011111111');
        [$friend, $card] = $this->member('222222', '01022222222');
        Sanctum::actingAs($friend);
        $this->postJson('/api/v1/referral/claim', ['number' => '111111'])->assertCreated();
        $sub = $this->pay($card, 600, 12); // referrer holds 10 + 60
        WalletTransaction::query()->create(['user_id' => $referrer->id, 'source' => 'order', 'client_id' => 'spent', 'amount' => -50]); // spent, 20 left

        Referrals::subscriptionRefunded($sub);
        Referrals::subscriptionRefunded($sub);

        $this->assertSame(0.0, WalletTransaction::balanceOf($referrer->id)); // only the 20 that was left
        $reward = ReferralReward::query()->where('reward_key', 'commission:'.$sub->id)->first();
        $this->assertSame('reversed', $reward->status);
        $this->assertStringContainsString('40', (string) $reward->note);
    }

    /** @test */
    public function a_code_is_refused_for_yourself_for_an_unknown_card_twice_or_after_paying(): void
    {
        [$referrer] = $this->member('111111', '01011111111');
        [$friend] = $this->member('222222', '01022222222');

        Sanctum::actingAs($friend);
        $this->postJson('/api/v1/referral/claim', ['number' => '222222'])->assertStatus(422)->assertJsonPath('reason', 'self');
        $this->postJson('/api/v1/referral/claim', ['number' => '999999'])->assertStatus(422)->assertJsonPath('reason', 'not_found');
        $this->postJson('/api/v1/referral/claim', ['number' => 'REG-1'])->assertStatus(422)->assertJsonPath('reason', 'not_found');
        $this->postJson('/api/v1/referral/claim', ['number' => '111111'])->assertCreated();
        $this->postJson('/api/v1/referral/claim', ['number' => '111111'])->assertStatus(422)->assertJsonPath('reason', 'already_referred');

        $this->assertSame(1, Referral::query()->count());
        $this->assertSame(10.0, WalletTransaction::balanceOf($referrer->id));

        [$payer, $payerCard] = $this->member('333333', '01033333333');
        $this->pay($payerCard, 600, 12);
        Sanctum::actingAs($payer);
        $this->postJson('/api/v1/referral/claim', ['number' => '111111'])->assertStatus(422)->assertJsonPath('reason', 'already_paid');
        $this->assertSame(10.0, WalletTransaction::balanceOf($referrer->id));
    }

    /** @test */
    public function the_admin_switch_and_amounts_are_followed(): void
    {
        [$referrer] = $this->member('111111', '01011111111');
        [$friend, $card] = $this->member('222222', '01022222222');

        SiteSettings::put('referral_enabled', '0', Setting::TYPE_BOOLEAN);
        Sanctum::actingAs($friend);
        $this->postJson('/api/v1/referral/claim', ['number' => '111111'])->assertStatus(422)->assertJsonPath('reason', 'disabled');

        SiteSettings::put('referral_enabled', '1', Setting::TYPE_BOOLEAN);
        SiteSettings::put('referral_signup_bonus', 5, Setting::TYPE_NUMBER);
        SiteSettings::put('referral_commission_percent_year', 20, Setting::TYPE_NUMBER);
        SiteSettings::put('referral_discount_year', 20, Setting::TYPE_NUMBER);
        SiteSettings::forget();
        $this->postJson('/api/v1/referral/claim', ['number' => '111111'])->assertCreated();
        $this->assertSame(5.0, WalletTransaction::balanceOf($referrer->id));
        $this->assertSame(20.0, (float) $this->getJson('/api/v1/membership')->json('data.referral_discount'));

        $this->pay($card, 100, 12);
        $this->assertSame(5.0 + 20, WalletTransaction::balanceOf($referrer->id));
    }

    /** @test */
    public function a_tapped_link_is_found_again_by_the_app_from_the_same_ip_once_and_only_when_unambiguous(): void
    {
        config(['services.partner_api.key' => 'partner-test-key']);
        $this->member('111111', '01011111111');
        $this->member('444444', '01044444444');
        $site = fn (string $code, string $ip) => $this->withHeader('X-Api-Key', 'partner-test-key')->postJson('/api/v1/partner/referral/click', ['code' => $code, 'ip' => $ip]);

        $this->postJson('/api/v1/partner/referral/click', ['code' => '111111', 'ip' => '9.9.9.9'])->assertUnauthorized();
        $site('999999', '9.9.9.9')->assertOk()->assertJsonPath('data.recorded', false);
        $site('REG-1', '9.9.9.9')->assertOk()->assertJsonPath('data.recorded', false);

        $site('111111', '9.9.9.9')->assertOk()->assertJsonPath('data.recorded', true);
        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])->postJson('/api/v1/mobile/referral/match')->assertOk()->assertJsonPath('data.code', null);
        $this->withServerVariables(['REMOTE_ADDR' => '9.9.9.9'])->postJson('/api/v1/mobile/referral/match')->assertOk()->assertJsonPath('data.code', '111111');
        $this->withServerVariables(['REMOTE_ADDR' => '9.9.9.9'])->postJson('/api/v1/mobile/referral/match')->assertOk()->assertJsonPath('data.code', null);

        /* Two different codes from one connection: nobody is guessed at. */
        $site('111111', '7.7.7.7');
        $site('444444', '7.7.7.7');
        $this->withServerVariables(['REMOTE_ADDR' => '7.7.7.7'])->postJson('/api/v1/mobile/referral/match')->assertOk()->assertJsonPath('data.code', null);

        /* A day later a tap is forgotten. */
        $site('111111', '6.6.6.6');
        \App\Models\ReferralClick::query()->update(['created_at' => now()->subHours(25)]);
        $this->withServerVariables(['REMOTE_ADDR' => '6.6.6.6'])->postJson('/api/v1/mobile/referral/match')->assertOk()->assertJsonPath('data.code', null);
    }

    /** @test */
    public function two_members_cannot_bring_each_other(): void
    {
        [$a] = $this->member('111111', '01011111111');
        [$b] = $this->member('222222', '01022222222');

        Sanctum::actingAs($b);
        $this->postJson('/api/v1/referral/claim', ['number' => '111111'])->assertCreated();

        Sanctum::actingAs($a);
        $this->postJson('/api/v1/referral/claim', ['number' => '222222'])->assertStatus(422)->assertJsonPath('reason', 'mutual');
        $this->assertSame(10.0, WalletTransaction::balanceOf($a->id)); // b's trial reward
        $this->assertSame(0.0, WalletTransaction::balanceOf($b->id));
    }

    /** @test */
    public function past_the_daily_limit_the_trial_reward_is_zero_but_the_member_stays_referred_with_the_discount(): void
    {
        SiteSettings::put('referral_signup_daily_limit', 2, Setting::TYPE_NUMBER);
        SiteSettings::forget();
        [$referrer] = $this->member('111111', '01011111111');

        foreach (['01022222222', '01033333333', '01044444444'] as $i => $phone) {
            [$friend] = $this->member('30000'.$i, $phone);
            Sanctum::actingAs($friend);
            $this->postJson('/api/v1/referral/claim', ['number' => '111111'])->assertCreated();
        }

        $this->assertSame(3, Referral::query()->count());
        $this->assertSame(20.0, WalletTransaction::balanceOf($referrer->id)); // two paid, the third paid 0
        $this->assertSame(0.0, (float) Referral::query()->latest('id')->first()->trial_bonus);
        $this->assertNotNull(Referral::query()->latest('id')->first()->trial_rewarded_at); // settled, never paid later
        $this->assertSame(50.0, (float) Referral::query()->latest('id')->first()->discount_amount);
    }

    /** @test */
    public function every_plan_has_its_own_discount_and_its_own_commission_percent_or_fixed_sum(): void
    {
        SiteSettings::put(MembershipPlan::HALF_PRICE, 400, Setting::TYPE_NUMBER);
        SiteSettings::put(MembershipPlan::INDIVIDUAL_MONTH_PRICE, 100, Setting::TYPE_NUMBER);
        foreach (['month' => 25, 'half' => 50, 'year' => 100] as $plan => $off) {
            SiteSettings::put('referral_discount_'.$plan, $off, Setting::TYPE_NUMBER);
        }
        SiteSettings::put('referral_commission_use_fixed_month', true, Setting::TYPE_BOOLEAN);
        SiteSettings::put('referral_commission_fixed_month', 15, Setting::TYPE_NUMBER);
        SiteSettings::put('referral_commission_percent_half', 5, Setting::TYPE_NUMBER);
        SiteSettings::put('referral_commission_percent_year', 10, Setting::TYPE_NUMBER);
        SiteSettings::forget();

        [$referrer] = $this->member('111111', '01011111111');
        [$friend, $card] = $this->member('222222', '01022222222');
        Sanctum::actingAs($friend);
        $this->postJson('/api/v1/referral/claim', ['number' => '111111'])->assertCreated();

        $plans = collect($this->getJson('/api/v1/membership')->json('data.plans'))->keyBy('key');
        $this->assertSame(75.0, (float) $plans['month']['discounted_price']);
        $this->assertSame(350.0, (float) $plans['half']['discounted_price']);
        $this->assertSame(500.0, (float) $plans['year']['discounted_price']);
        $this->assertSame(25.0, (float) $plans['month']['referral_discount']);

        $rules = $this->getJson('/api/v1/referral')->json('data.amounts.plans');
        $this->assertEquals(['discount' => 50, 'commission_type' => 'percent', 'commission_percent' => 5, 'commission_fixed' => 0], $rules['half']);
        $this->assertSame('fixed', $rules['month']['commission_type']);

        $this->pay($card, 75, 1);     // fixed 15
        $this->pay($card, 350, 6);    // 5% of 350 = 17.5 (renewal: no discount was meant, amount is what was paid)
        $this->pay($card, 500, 12);   // 10% of 500 = 50

        $this->assertSame([15.0, 17.5, 50.0], ReferralReward::query()->where('kind', 'commission')->orderBy('id')->get()->map(fn ($r) => (float) $r->amount)->all());
        $this->assertSame(10.0 + 15 + 17.5 + 50, WalletTransaction::balanceOf($referrer->id));
        $this->assertSame(25.0, (float) Referral::query()->first()->discount_amount); // the discount of the plan that was bought first
    }

    /** @test */
    public function a_fixed_commission_is_never_more_than_what_the_member_paid(): void
    {
        SiteSettings::put('referral_commission_use_fixed_year', true, Setting::TYPE_BOOLEAN);
        SiteSettings::put('referral_commission_fixed_year', 900, Setting::TYPE_NUMBER);
        SiteSettings::forget();

        $this->assertSame(['type' => 'fixed', 'rate' => 0.0, 'amount' => 550.0], Referrals::commissionFor('year', 550));
    }
}
