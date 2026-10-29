<?php

namespace Tests\Feature;

use App\Actions\Membership\CreateRelative;
use App\Models\MemberPayment;
use App\Models\Membership;
use App\Models\Setting;
use App\Models\User;
use App\Support\PhysicalCard;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** A primary member with a paid card (yearly or half-year) orders plastic cards (an ordinary order); prices come from the settings. */
class PhysicalCardOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SiteSettings::put('payment_cod_enabled', true, Setting::TYPE_BOOLEAN);
        SiteSettings::forget();
    }

    /** @test */
    public function an_annual_primary_orders_copies_for_self_and_relative_and_pays_the_settings_prices(): void
    {
        [$user, $card] = $this->member(12);
        $wife = app(CreateRelative::class)->handle($card, ['name' => 'Mona Hassan Ali', 'relationship' => 'wife', 'date_of_birth' => '1990-01-01', 'is_active' => true]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/mobile/physical-cards')
            ->assertOk()
            ->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.price', 50)
            ->assertJsonPath('data.shipping_price', 50)
            ->assertJsonCount(2, 'data.cards');

        // 2 copies of the holder's own number + 1 for the wife = 3 × 50, plus 50 shipping once.
        $this->postJson('/api/v1/mobile/physical-cards/orders', $this->body([
            ['membership_number' => $card->membership_number, 'quantity' => 2],
            ['membership_number' => $wife->membership_number, 'quantity' => 1],
        ]))->assertCreated()->assertJsonPath('order.total_amount', 200);

        $this->assertDatabaseHas('orders', ['user_id' => $user->id, 'source' => 'physical_card', 'total_amount' => 200, 'delivery_price' => 50, 'payment_status' => 'pending']);
        $this->assertDatabaseCount('order_products', 2);
    }

    /** @test */
    public function the_admin_settings_decide_the_prices(): void
    {
        [$user, $card] = $this->member(12);
        SiteSettings::put(PhysicalCard::PRICE, 80, Setting::TYPE_NUMBER);
        SiteSettings::put(PhysicalCard::SHIPPING, 30, Setting::TYPE_NUMBER);
        SiteSettings::forget();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/mobile/physical-cards/orders', $this->body([['membership_number' => $card->membership_number, 'quantity' => 1]]))
            ->assertCreated()
            ->assertJsonPath('order.total_amount', 110);
    }

    /** @test */
    public function a_half_year_subscriber_may_order_but_a_trial_a_relative_and_a_stranger_number_may_not(): void
    {
        [$half, $halfCard] = $this->member(6);
        Sanctum::actingAs($half);
        $this->postJson('/api/v1/mobile/physical-cards/orders', $this->body([['membership_number' => $halfCard->membership_number, 'quantity' => 1]]))
            ->assertCreated();

        [$trial, $trialCard] = $this->member(6, '01055555555');
        $trialCard->forceFill(['plan' => Membership::PLAN_TRIAL])->save();
        Sanctum::actingAs($trial);
        $this->postJson('/api/v1/mobile/physical-cards/orders', $this->body([['membership_number' => $trialCard->membership_number, 'quantity' => 1]]))
            ->assertForbidden()->assertJsonPath('reason', 'not_current');

        [$annual, $card] = $this->member(12, '01077777777');
        [, $otherCard] = $this->member(12, '01088888888');
        Sanctum::actingAs($annual);
        $this->postJson('/api/v1/mobile/physical-cards/orders', $this->body([['membership_number' => $otherCard->membership_number, 'quantity' => 1]]))
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.membership_number');

        $wife = app(CreateRelative::class)->handle($card, ['name' => 'Mona Hassan Ali', 'relationship' => 'wife', 'date_of_birth' => '1990-01-01', 'is_active' => true]);
        Sanctum::actingAs($wife->user);
        $this->getJson('/api/v1/mobile/physical-cards')->assertOk()->assertJsonPath('data.eligible', false)->assertJsonPath('data.reason', 'relative');
    }

    /** @return array{0: User, 1: Membership} */
    private function member(int $months, string $phone = '01062587475'): array
    {
        $user = User::factory()->create(['name' => 'Ahmed Ali Hassan', 'gender' => 'male', 'phone' => $phone]);
        $card = Membership::factory()->create([
            'user_id' => $user->id, 'is_active' => true, 'is_visible' => true, 'plan' => Membership::PLAN_FULL, 'expiration_date' => now()->addMonths(6),
        ]);
        MemberPayment::query()->create(['membership_id' => $card->id, 'amount' => 700, 'months_paid' => $months, 'from_date' => now()->toDateString(), 'to_date' => now()->addMonths($months)->toDateString(), 'type' => 'cash']);

        return [$user, $card];
    }

    /** @param  list<array{membership_number: string, quantity: int}>  $items */
    private function body(array $items): array
    {
        return [
            'items' => $items,
            'customer_full_name' => 'Ahmed Ali', 'customer_phone' => '01062587475', 'customer_address' => '1 Tahrir St, Cairo',
            'payment_type' => 'cod',
        ];
    }
}
