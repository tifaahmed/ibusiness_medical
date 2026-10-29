<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\Setting;
use App\Models\User;
use App\Services\Otp\MemberOtp;
use App\Support\OtpSettings;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A relative's phone is typed in by the primary member, so it starts UNVERIFIED. Only a code that really travelled by SMS
 * to that phone and was typed back verifies it; a fixed/admin code proves nothing about the number.
 */
class RelativePhoneVerifiedTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_relative_added_with_a_phone_is_unverified_without_any_sms(): void
    {
        $father = User::factory()->create(['name' => 'Ahmed Ali Hassan', 'gender' => 'male', 'phone' => '01062587475']);
        Membership::factory()->create(['user_id' => $father->id, 'is_active' => true, 'is_visible' => true]);
        Sanctum::actingAs($father);

        $this->postJson('/api/v1/family/children', ['first_name' => 'Omar', 'relationship' => 'son', 'phone' => '01012345678'])
            ->assertCreated()
            ->assertJsonPath('data.family_member.phone', '01012345678')
            ->assertJsonPath('data.family_member.phone_verified', false);

        $this->assertNull(User::query()->where('phone', '01012345678')->value('phone_verified_at'));
    }

    /** @test */
    public function an_sms_code_typed_back_verifies_the_phone_but_a_fixed_code_does_not(): void
    {
        Cache::flush();
        $user = User::factory()->create(['phone' => '01012345678']);
        Membership::factory()->create(['user_id' => $user->id, 'is_active' => true, 'is_visible' => true]);
        $otp = app(MemberOtp::class);

        // Fixed-code mode (no SMS): the number is not proven.
        SiteSettings::put(OtpSettings::SMS_ENABLED, false, Setting::TYPE_BOOLEAN);
        SiteSettings::forget();
        $this->assertSame('fixed', $otp->issue('01012345678')['delivery']);
        $this->assertTrue($otp->verify('01012345678', (string) OtpSettings::fixedCode())['verified']);
        $this->assertNull($user->refresh()->phone_verified_at);

        // What an SMS-delivered issue looks like once typed back.
        $otp->confirmPhone('01012345678');
        $this->assertNotNull($user->refresh()->phone_verified_at);
    }
}
