<?php

namespace Tests\Feature;

use App\Enums\User\UserRoleEnum;
use App\Models\Membership;
use App\Models\Setting;
use App\Models\User;
use App\Services\Otp\MemberOtp;
use App\Services\Otp\MemberRegistration;
use App\Support\OtpSettings;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The storefront's phone login as the Deilar site drives it: a member's number
 * in, a code out, a Sanctum token back.
 *
 * Both endpoints are key-gated, because both of them mint or check a
 * credential, and every decision about the code — real or fixed, how long, how
 * many guesses — is made here rather than by the caller.
 */
class PartnerPhoneLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.partner_api.key' => 'partner-test-key']);

        /*
         * Fixed-code mode unless a test says otherwise: it is the state a fresh
         * install is in (no gateway credentials), and it keeps every test that
         * is not about SMS from having to fake an HTTP call.
         */
        $this->useOtpSettings([OtpSettings::SMS_ENABLED => false]);
    }

    /** @test */
    public function requesting_a_code_needs_the_shared_key(): void
    {
        $member = $this->member('01062587475');

        $this->postJson('/api/v1/partner/auth/otp', ['phone' => $member->phone])
            ->assertUnauthorized();
    }

    /** @test */
    public function verifying_a_code_needs_the_shared_key(): void
    {
        $member = $this->member('01062587475');

        $this->postJson('/api/v1/partner/auth/otp/verify', [
            'phone' => $member->phone,
            'code' => '1234',
        ])->assertUnauthorized();
    }

    /** @test */
    public function an_unknown_number_is_sent_a_code_rather_than_refused(): void
    {
        /*
         * The storefront has no separate sign-up: an unknown number gets a code
         * like any other, and answering it registers whoever did. The only
         * difference in the answer is one flag the storefront uses for a line
         * of copy.
         */
        $this->requestCode('01099999999')
            ->assertOk()
            ->assertJsonPath('data.registering', true);
    }

    /** @test */
    public function a_correct_code_on_an_unknown_number_creates_an_inert_member(): void
    {
        $this->requestCode('01099999999')->assertOk();

        $this->verifyCode('01099999999', '1234')
            ->assertOk()
            ->assertJsonPath('data.registered', true)
            /*
             * A registration buys nothing a card buys. The membership is
             * inactive and its number is a placeholder until staff issue a real
             * one — which is what the storefront reports as "customer services
             * will contact you soon".
             */
            ->assertJsonPath('data.membership.is_active', false)
            ->assertJsonPath('data.registration.pending_card', true);

        $member = User::query()->where('phone', '01099999999')->first();

        $this->assertNotNull($member);

        $membership = $member->memberships()->first();

        $this->assertFalse((bool) $membership->is_active);
        $this->assertFalse((bool) $membership->is_visible);
        $this->assertStringStartsWith(MemberRegistration::PENDING_PREFIX, $membership->membership_number);
    }

    /** @test */
    public function a_wrong_code_on_an_unknown_number_creates_nothing(): void
    {
        $this->requestCode('01099999999')->assertOk();

        $this->verifyCode('01099999999', '0000')->assertStatus(422);

        $this->assertSame(0, User::query()->where('phone', '01099999999')->count());
    }

    /** @test */
    public function a_new_member_starts_with_an_empty_progress_bar(): void
    {
        $this->requestCode('01099999999')->assertOk();

        $this->verifyCode('01099999999', '1234')
            ->assertOk()
            ->assertJsonPath('data.registration.essentials.percent', 0)
            ->assertJsonPath('data.registration.essentials.complete', false)
            ->assertJsonPath('data.registration.details.complete', false);
    }

    /** @test */
    public function a_staff_account_cannot_sign_in_or_be_registered_over(): void
    {
        /*
         * Staff hold roles and hold no membership, so they never resolve as a
         * member. Now that an unknown number is a registration, that alone is
         * not enough: without the staff check, a code sent to an
         * administrator's phone would attach a storefront membership to an
         * account carrying admin roles, and the token handed back would carry
         * them too.
         */
        $admin = User::factory()->create(['phone' => '01055555555']);
        $admin->assignRole(UserRoleEnum::ADMIN);

        $this->requestCode('01055555555')->assertOk();

        $this->verifyCode('01055555555', '1234')
            ->assertNotFound()
            ->assertJsonPath('reason', 'unknown_phone');

        $this->assertSame(0, $admin->memberships()->count());
        $this->assertSame(0, $admin->tokens()->count());
    }

    /** @test */
    public function a_member_is_found_however_they_write_their_own_number(): void
    {
        $this->member('01062587475');

        foreach (['01062587475', '+201062587475', '0106 258 7475', '201062587475'] as $typed) {
            $this->requestCode($typed)
                ->assertOk()
                ->assertJsonPath('data.phone_masked', '•••••••7475');

            // The cooldown is per member, so clear it between spellings.
            $this->travel(MemberOtp::RESEND_AFTER_SECONDS + 1)->seconds();
        }
    }

    /** @test */
    public function the_response_never_carries_the_code_or_the_stored_number(): void
    {
        $member = $this->member('01062587475');

        $response = $this->requestCode($member->phone)->assertOk();

        $body = $response->getContent();

        $this->assertStringNotContainsString('1234', $body);
        $this->assertStringNotContainsString('01062587475', $body);
    }

    /** @test */
    public function with_sms_off_the_fixed_code_is_what_verifies(): void
    {
        $member = $this->member('01062587475');

        $this->requestCode($member->phone)
            ->assertOk()
            ->assertJsonPath('data.delivery', 'fixed');

        $this->verifyCode($member->phone, '1234')
            ->assertOk()
            ->assertJsonPath('reason', 'verified')
            ->assertJsonPath('data.user.id', $member->id)
            ->assertJsonStructure(['data' => ['token', 'user', 'membership']]);

        $this->assertSame(1, $member->tokens()->count());
    }

    /** @test */
    public function the_fixed_code_is_refused_once_sms_is_switched_back_on(): void
    {
        /*
         * The whole point of the fixed code is that it is a testing mode. If it
         * kept working while real codes were being sent, leaving one set would
         * be a permanent way past every code this application issues.
         */
        Http::fake(['*' => Http::response('1912181513205684021')]);

        $this->useOtpSettings([OtpSettings::SMS_ENABLED => true]);
        config(['services.sms.user' => 'u', 'services.sms.password' => 'p', 'services.sms.sender' => 'Deilar']);

        $member = $this->member('01062587475');

        $this->requestCode($member->phone)
            ->assertOk()
            ->assertJsonPath('data.delivery', 'sms');

        $this->verifyCode($member->phone, '1234')
            ->assertStatus(422)
            ->assertJsonPath('reason', 'invalid');
    }

    /** @test */
    public function a_real_code_is_sent_through_the_gateway_and_verifies(): void
    {
        Http::fake(['*' => Http::response('1912181513205684021')]);

        $this->useOtpSettings([OtpSettings::SMS_ENABLED => true]);
        config(['services.sms.user' => 'u', 'services.sms.password' => 'p', 'services.sms.sender' => 'Deilar']);

        $member = $this->member('01062587475');

        $this->requestCode($member->phone)->assertOk();

        $sent = null;

        Http::assertSent(function ($request) use (&$sent) {
            $query = $request->data();

            // The gateway wants the country code and no trunk zero.
            $this->assertSame('201062587475', $query['mno']);
            $this->assertSame('1', $query['type']);

            preg_match('/\b(\d{4})\b/', (string) $query['text'], $matches);
            $sent = $matches[1] ?? null;

            return true;
        });

        $this->assertNotNull($sent, 'The message should carry the code.');

        $this->verifyCode($member->phone, $sent)
            ->assertOk()
            ->assertJsonPath('reason', 'verified');
    }

    /** @test */
    public function a_gateway_refusal_issues_nothing(): void
    {
        Http::fake(['*' => Http::response('ERROR - HTTP18')]);

        $this->useOtpSettings([OtpSettings::SMS_ENABLED => true]);
        config(['services.sms.user' => 'u', 'services.sms.password' => 'p', 'services.sms.sender' => 'Deilar']);

        $member = $this->member('01062587475');

        $this->requestCode($member->phone)
            ->assertStatus(503)
            ->assertJsonPath('reason', 'send_failed');

        // Nothing was stored, so there is no code to guess at afterwards.
        $this->verifyCode($member->phone, '1234')
            ->assertStatus(422)
            ->assertJsonPath('reason', 'expired');
    }

    /** @test */
    public function clearing_the_fixed_code_with_sms_off_closes_the_login(): void
    {
        $this->useOtpSettings([
            OtpSettings::SMS_ENABLED => false,
            OtpSettings::FIXED_CODE => '',
        ]);

        $member = $this->member('01062587475');

        $this->requestCode($member->phone)
            ->assertStatus(503)
            ->assertJsonPath('reason', 'not_configured');
    }

    /** @test */
    public function a_second_code_has_to_wait_for_the_cooldown(): void
    {
        $member = $this->member('01062587475');

        $this->requestCode($member->phone)->assertOk();

        $this->requestCode($member->phone)
            ->assertStatus(429)
            ->assertJsonPath('reason', 'cooldown');

        $this->travel(MemberOtp::RESEND_AFTER_SECONDS + 1)->seconds();

        $this->requestCode($member->phone)->assertOk();
    }

    /** @test */
    public function a_code_expires_and_stops_verifying(): void
    {
        $member = $this->member('01062587475');

        $this->requestCode($member->phone)->assertOk();

        $this->travel(OtpSettings::ttlMinutes() + 1)->minutes();

        $this->verifyCode($member->phone, '1234')
            ->assertStatus(422)
            ->assertJsonPath('reason', 'expired');
    }

    /** @test */
    public function five_wrong_codes_burn_the_issue(): void
    {
        $member = $this->member('01062587475');

        $this->requestCode($member->phone)->assertOk();

        for ($attempt = 1; $attempt < MemberOtp::MAX_ATTEMPTS; $attempt++) {
            $this->verifyCode($member->phone, '0000')
                ->assertStatus(422)
                ->assertJsonPath('reason', 'invalid')
                ->assertJsonPath('attempts_left', MemberOtp::MAX_ATTEMPTS - $attempt);
        }

        $this->verifyCode($member->phone, '0000')
            ->assertStatus(429)
            ->assertJsonPath('reason', 'too_many_attempts');

        // Even the right code is worth nothing now — the issue is gone.
        $this->verifyCode($member->phone, '1234')
            ->assertStatus(422)
            ->assertJsonPath('reason', 'expired');
    }

    /** @test */
    public function a_code_verifies_exactly_once(): void
    {
        $member = $this->member('01062587475');

        $this->requestCode($member->phone)->assertOk();

        $this->verifyCode($member->phone, '1234')->assertOk();

        $this->verifyCode($member->phone, '1234')
            ->assertStatus(422)
            ->assertJsonPath('reason', 'expired');
    }

    /**
     * A member: a user with an active membership, which is the only thing this
     * login resolves.
     */
    private function member(string $phone): User
    {
        $user = User::factory()->create(['phone' => $phone]);

        Membership::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
            'is_visible' => true,
        ]);

        return $user->refresh();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function useOtpSettings(array $values): void
    {
        foreach ($values as $slug => $value) {
            SiteSettings::put(
                $slug,
                $value,
                is_bool($value) ? Setting::TYPE_BOOLEAN : Setting::TYPE_STRING,
            );
        }

        SiteSettings::forget();
    }

    private function requestCode(string $phone): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('X-Api-Key', 'partner-test-key')
            ->postJson('/api/v1/partner/auth/otp', ['phone' => $phone]);
    }

    private function verifyCode(string $phone, string $code): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('X-Api-Key', 'partner-test-key')
            ->postJson('/api/v1/partner/auth/otp/verify', ['phone' => $phone, 'code' => $code]);
    }
}
