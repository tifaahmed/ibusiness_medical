<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Governorate;
use App\Models\Membership;
use App\Models\Setting;
use App\Models\User;
use App\Support\MembershipPlan;
use App\Support\OtpSettings;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The mobile app's guest journey end to end, over the real endpoints:
 * phone check -> code -> (registered, no card) -> gender + national ID -> trial card
 * -> trial reminder state -> expiry -> no member price; plus saving the area to the card.
 *
 * Fixed-code mode (no SMS gateway), so the code is the default 1234.
 */
class GuestTrialJourneyTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '01012345678';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        SiteSettings::put(OtpSettings::SMS_ENABLED, false, Setting::TYPE_BOOLEAN);
        SiteSettings::put(MembershipPlan::PRICE, 600, Setting::TYPE_NUMBER);
        SiteSettings::put(MembershipPlan::MONTHS, 12, Setting::TYPE_NUMBER);
        SiteSettings::put(MembershipPlan::TRIAL_MONTHS, 1, Setting::TYPE_NUMBER);
        SiteSettings::forget();
    }

    /** Signs a phone in through the real code flow and returns its bearer token. */
    private function signIn(string $phone = self::PHONE): string
    {
        $this->postJson('/api/v1/mobile/auth/otp', ['phone' => $phone])->assertOk();

        return $this->postJson('/api/v1/mobile/auth/otp/verify', ['phone' => $phone, 'code' => '1234'])
            ->assertOk()
            ->json('data.token');
    }

    private function asMember(string $token)
    {
        // The guard remembers the first token's user for the whole test; each call is a fresh request.
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    public function test_the_whole_guest_journey_from_unknown_phone_to_an_active_trial_card(): void
    {
        // 1. A new number holds no card, and asking sends no code.
        $this->postJson('/api/v1/mobile/auth/phone-check', ['phone' => self::PHONE])->assertOk()->assertJsonPath('data.has_card', false);

        // 2. The code proves the phone: the account exists, but only as an inert placeholder.
        $this->postJson('/api/v1/mobile/auth/otp', ['phone' => self::PHONE])->assertOk()->assertJsonPath('data.registering', true);
        $verified = $this->postJson('/api/v1/mobile/auth/otp/verify', ['phone' => self::PHONE, 'code' => '1234'])->assertOk();
        $token = $verified->json('data.token');

        $placeholder = User::query()->where('phone', self::PHONE)->firstOrFail()->memberships()->firstOrFail();
        $this->assertStringStartsWith('REG-', $placeholder->membership_number);
        $this->assertFalse((bool) $placeholder->is_active, 'registered but not active yet');
        $this->postJson('/api/v1/mobile/auth/phone-check', ['phone' => self::PHONE])->assertJsonPath('data.has_card', false);

        $this->asMember($token)->getJson('/api/v1/membership')->assertOk()->assertJsonPath('data.has_card', false);

        // 3. Gender + national ID: the card goes active as a trial.
        $this->asMember($token)->postJson('/api/v1/membership/trial', ['name' => 'Mona Hassan', 'national_id' => '29001011234567', 'gender' => 'female'])
            ->assertCreated()
            ->assertJsonPath('data.state', 'trial')
            ->assertJsonPath('data.is_trial', true)
            ->assertJsonPath('data.expired', false);

        $user = User::query()->where('phone', self::PHONE)->firstOrFail();
        $card = $user->memberships()->latest('id')->firstOrFail();

        $this->assertSame($placeholder->id, $card->id, 'the placeholder is upgraded, never duplicated');
        $this->assertSame('female', $user->gender);
        $this->assertSame('Mona Hassan', $user->name);
        $this->assertTrue((bool) $card->is_active);
        $this->assertSame(Membership::PLAN_TRIAL, $card->plan);
        $this->assertTrue($card->expiration_date->between(now()->addDays(27), now()->addDays(32)), 'about a month');

        // 4. The reminder data: trial with days left, a price to pay, and a way to pay online or by receipt.
        $this->asMember($token)->getJson('/api/v1/membership')->assertOk()
            ->assertJsonPath('data.has_card', true)
            ->assertJsonPath('data.is_trial', true)
            ->assertJsonPath('data.expired', false)
            ->assertJsonPath('data.price', 600)
            ->assertJson(fn ($json) => $json->where('data.days_left', fn ($d) => $d >= 27 && $d <= 32)->etc());

        $this->postJson('/api/v1/mobile/auth/phone-check', ['phone' => self::PHONE])->assertJsonPath('data.has_card', true);
        $this->assertTrue(Membership::earnsMemberPrice($card->membership_number), 'a running trial earns the member price');

        // 5. One trial per phone, ever.
        $this->asMember($token)->postJson('/api/v1/membership/trial', ['name' => 'Mona Hassan', 'national_id' => '29001011234567'])
            ->assertUnprocessable()->assertJsonPath('reason', 'already_has_card');

        // A different phone is a different person: the national ID is not part of the trial any more.
        $other = $this->signIn('01123456789');
        $this->asMember($other)->postJson('/api/v1/membership/trial', ['name' => 'Someone Else'])->assertCreated();

        // 6. The 30 days pass without paying: expired, red sign data, and no discount any more.
        $card->forceFill(['expiration_date' => now()->subDay()])->save();

        $this->asMember($token)->getJson('/api/v1/membership')->assertOk()
            ->assertJsonPath('data.state', 'trial_expired')
            ->assertJsonPath('data.expired', true)
            ->assertJsonPath('data.is_trial', true);
        $this->assertFalse(Membership::earnsMemberPrice($card->fresh()->membership_number), 'an expired card pays the full price');
        $this->getJson('/api/v1/mobile/card/'.$card->membership_number)->assertOk()->assertJsonPath('status.expired', true);
    }

    public function test_the_trial_needs_a_known_gender(): void
    {
        $token = $this->signIn();

        $this->asMember($token)->postJson('/api/v1/membership/trial', ['name' => 'Mona Hassan', 'national_id' => '29001011234567', 'gender' => 'x'])->assertUnprocessable();
        $this->assertSame(0, Membership::query()->where('is_active', true)->count(), 'a refused request activates nothing');
    }

    public function test_an_existing_card_holder_is_not_offered_a_second_trial_by_the_phone_check(): void
    {
        $token = $this->signIn();
        $this->asMember($token)->postJson('/api/v1/membership/trial', ['name' => 'Mona Hassan', 'national_id' => '29001011234567'])->assertCreated();

        $this->postJson('/api/v1/mobile/auth/phone-check', ['phone' => '+201012345678'])->assertJsonPath('data.has_card', true);
        $this->postJson('/api/v1/mobile/auth/phone-check', ['phone' => 'abc'])->assertUnprocessable();
    }

    public function test_the_area_picked_on_the_device_can_be_saved_to_the_card(): void
    {
        $token = $this->signIn();
        $governorate = Governorate::factory()->create();
        $city = City::factory()->create(['governorate_id' => $governorate->id]);
        $foreignCity = City::factory()->create();

        $this->asMember($token)->getJson('/api/v1/profile')->assertOk()
            ->assertJsonPath('data.user.membership.governorate_id', null)
            ->assertJsonPath('data.user.membership.city_id', null);

        $this->asMember($token)->putJson('/api/v1/profile', ['governorate_id' => $governorate->id, 'city_id' => $city->id])->assertOk();

        $this->asMember($token)->getJson('/api/v1/profile')
            ->assertJsonPath('data.user.membership.governorate_id', $governorate->id)
            ->assertJsonPath('data.user.membership.city_id', $city->id);

        // A city of another governorate is dropped, never saved as a pair nobody can deliver to.
        $this->asMember($token)->putJson('/api/v1/profile', ['city_id' => $foreignCity->id])->assertOk();
        $this->asMember($token)->getJson('/api/v1/profile')
            ->assertJsonPath('data.user.membership.governorate_id', $governorate->id)
            ->assertJsonPath('data.user.membership.city_id', null);

        // Governorate-only (a governorate with no cities) is fine.
        $this->asMember($token)->putJson('/api/v1/profile', ['governorate_id' => $governorate->id, 'city_id' => null])->assertOk();
    }

    public function test_a_storefront_can_ask_whether_a_phone_holds_a_card_with_its_key(): void
    {
        config(['services.partner_api.key' => 'partner-test-key']);

        $this->postJson('/api/v1/partner/auth/phone-check', ['phone' => self::PHONE])->assertUnauthorized();

        $this->withHeader('X-Api-Key', 'partner-test-key')->postJson('/api/v1/partner/auth/phone-check', ['phone' => self::PHONE])
            ->assertOk()->assertJsonPath('data.has_card', false);

        $token = $this->signIn();
        $this->asMember($token)->postJson('/api/v1/membership/trial', ['name' => 'Mona Hassan', 'national_id' => '29001011234567'])->assertCreated();

        $this->withHeader('X-Api-Key', 'partner-test-key')->postJson('/api/v1/partner/auth/phone-check', ['phone' => self::PHONE])
            ->assertOk()->assertJsonPath('data.has_card', true);
    }

    /** @test */
    public function the_phone_check_says_whether_a_phone_is_registered_even_without_a_card(): void
    {
        // A sign-up placeholder (`REG-`): registered, but not a card.
        $user = \App\Models\User::factory()->create(['phone' => '01055556666']);
        \App\Models\Membership::factory()->create(['user_id' => $user->id, 'membership_number' => 'REG-TESTCASE']);

        $this->postJson('/api/v1/mobile/auth/phone-check', ['phone' => '01055556666'])
            ->assertOk()->assertJsonPath('data.has_card', false)->assertJsonPath('data.registered', true);

        $this->postJson('/api/v1/mobile/auth/phone-check', ['phone' => '01077778888'])
            ->assertOk()->assertJsonPath('data.has_card', false)->assertJsonPath('data.registered', false);
    }
}
