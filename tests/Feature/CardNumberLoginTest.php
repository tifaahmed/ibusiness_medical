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

/**
 * The app's card-number sign-in: the code goes to the phone ON the card, and a card with no phone may ADD one
 * (never change one). Fixed-code mode (1234), so no SMS is involved.
 */
class CardNumberLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        SiteSettings::put(OtpSettings::SMS_ENABLED, false, Setting::TYPE_BOOLEAN);
        SiteSettings::forget();
    }

    /** @test */
    public function a_card_with_a_phone_texts_a_code_there_and_the_code_signs_in(): void
    {
        $card = $this->card('01062587475');

        $this->postJson('/api/v1/mobile/auth/card/otp', ['card_number' => $card->membership_number])
            ->assertOk()
            ->assertJsonPath('data.has_phone', true)
            ->assertJsonPath('data.phone_masked', '•••••••7475');

        $this->postJson('/api/v1/mobile/auth/card/otp/verify', ['card_number' => $card->membership_number, 'code' => '1234'])
            ->assertOk()
            ->assertJsonPath('data.membership.membership_number', $card->membership_number)
            ->assertJsonStructure(['data' => ['token']]);
    }

    /** @test */
    public function a_wrong_code_does_not_sign_in(): void
    {
        $card = $this->card('01062587475');
        $this->postJson('/api/v1/mobile/auth/card/otp', ['card_number' => $card->membership_number])->assertOk();

        $this->postJson('/api/v1/mobile/auth/card/otp/verify', ['card_number' => $card->membership_number, 'code' => '0000'])
            ->assertStatus(422)->assertJsonPath('reason', 'invalid');
    }

    /** @test */
    public function an_unknown_card_or_a_placeholder_is_refused(): void
    {
        $this->postJson('/api/v1/mobile/auth/card/otp', ['card_number' => 'NOPE'])->assertNotFound()->assertJsonPath('reason', 'unknown_card');

        $reg = $this->card('01000000001', 'REG-ABC');
        $this->postJson('/api/v1/mobile/auth/card/otp', ['card_number' => $reg->membership_number])->assertNotFound();
    }

    /** @test */
    public function a_card_without_a_phone_sends_nothing_and_says_so(): void
    {
        $card = $this->card(null);

        $this->postJson('/api/v1/mobile/auth/card/otp', ['card_number' => $card->membership_number])
            ->assertOk()->assertJsonPath('data.has_phone', false);

        $this->getJson('/api/v1/mobile/card/'.$card->membership_number)->assertOk()->assertJsonPath('has_phone', false);
    }

    /** @test */
    public function a_phone_can_be_added_to_a_card_that_has_none_and_signs_in(): void
    {
        $card = $this->card(null);

        $this->postJson('/api/v1/mobile/auth/card/phone', ['card_number' => $card->membership_number, 'phone' => '01055555555'])
            ->assertOk()->assertJsonPath('data.has_phone', false);

        // Nothing is saved until the code is right.
        $this->assertNull($card->user->fresh()->phone);

        $this->postJson('/api/v1/mobile/auth/card/phone/verify', ['card_number' => $card->membership_number, 'phone' => '01055555555', 'code' => '1234'])
            ->assertOk()->assertJsonStructure(['data' => ['token']]);

        $this->assertSame('01055555555', $card->user->fresh()->phone);
        $this->getJson('/api/v1/mobile/card/'.$card->membership_number)->assertJsonPath('has_phone', true);
    }

    /** @test */
    public function a_phone_already_on_the_card_can_never_be_replaced(): void
    {
        $card = $this->card('01062587475');

        $this->postJson('/api/v1/mobile/auth/card/phone', ['card_number' => $card->membership_number, 'phone' => '01055555555'])
            ->assertStatus(409)->assertJsonPath('reason', 'phone_already_set');
        $this->postJson('/api/v1/mobile/auth/card/phone/verify', ['card_number' => $card->membership_number, 'phone' => '01055555555', 'code' => '1234'])
            ->assertStatus(409);

        $this->assertSame('01062587475', $card->user->fresh()->phone);
    }

    /** @test */
    public function a_phone_that_belongs_to_another_account_is_refused(): void
    {
        $this->card('01055555555');
        $card = $this->card(null);

        $this->postJson('/api/v1/mobile/auth/card/phone', ['card_number' => $card->membership_number, 'phone' => '+20 105 555 5555'])
            ->assertStatus(422)->assertJsonPath('reason', 'phone_taken');
    }

    private function card(?string $phone, ?string $number = null): Membership
    {
        $user = User::factory()->create(['phone' => $phone]);

        return Membership::factory()->create(array_filter([
            'user_id' => $user->id,
            'is_active' => true,
            'is_visible' => true,
            'membership_number' => $number,
        ]));
    }
}
