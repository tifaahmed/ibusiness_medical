<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\Setting;
use App\Models\User;
use App\Support\OtpSettings;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** A relative opens the app with THEIR card number: the code goes to the phone on that card, or they add their own once. */
class RelativeCardLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        SiteSettings::put(OtpSettings::SMS_ENABLED, false, Setting::TYPE_BOOLEAN);
        SiteSettings::forget();
    }

    private function relative(?string $phone): Membership
    {
        $owner = Membership::factory()->create(['user_id' => User::factory()->create(['phone' => '01000000009'])->id, 'is_active' => true]);

        return Membership::factory()->create([
            'user_id' => User::factory()->create(['phone' => $phone])->id, 'member_type' => Membership::TYPE_RELATIVE,
            'parent_membership_id' => $owner->id, 'relationship' => 'wife', 'national_id' => '29801010102345', 'is_active' => true, 'is_visible' => true,
        ]);
    }

    /** @test */
    public function a_relative_with_a_phone_gets_the_code_there_and_signs_in(): void
    {
        $card = $this->relative('01062587475');

        $this->postJson('/api/v1/mobile/auth/card/otp', ['card_number' => $card->membership_number])
            ->assertOk()->assertJsonPath('data.has_phone', true);

        $this->postJson('/api/v1/mobile/auth/card/otp/verify', ['card_number' => $card->membership_number, 'code' => '1234'])
            ->assertOk()->assertJsonPath('data.membership.membership_number', $card->membership_number);
    }

    /** @test */
    public function a_relative_without_a_phone_adds_their_own_once_and_it_stays(): void
    {
        $card = $this->relative(null);

        $this->postJson('/api/v1/mobile/auth/card/otp', ['card_number' => $card->membership_number])
            ->assertOk()->assertJsonPath('data.has_phone', false);

        $this->postJson('/api/v1/mobile/auth/card/phone', ['card_number' => $card->membership_number, 'phone' => '01144444444'])->assertOk();
        $this->postJson('/api/v1/mobile/auth/card/phone/verify', ['card_number' => $card->membership_number, 'phone' => '01144444444', 'code' => '1234'])->assertOk();

        $this->assertSame('01144444444', $card->user->fresh()->phone);

        // Never replaced from the app afterwards.
        $this->postJson('/api/v1/mobile/auth/card/phone', ['card_number' => $card->membership_number, 'phone' => '01155555555'])
            ->assertStatus(409)->assertJsonPath('reason', 'phone_already_set');
    }
}
