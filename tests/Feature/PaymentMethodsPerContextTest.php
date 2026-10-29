<?php

namespace Tests\Feature;

use App\Models\MemberPayment;
use App\Models\Membership;
use App\Models\MembershipSubscription;
use App\Models\Setting;
use App\Models\User;
use App\Support\PaymentMethods;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** A payment method can be shown for shop products, physical cards and memberships independently. */
class PaymentMethodsPerContextTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_method_hidden_for_cards_stays_on_for_products_and_memberships(): void
    {
        SiteSettings::put(PaymentMethods::COD, true, Setting::TYPE_BOOLEAN);
        SiteSettings::put(PaymentMethods::contextSlug(PaymentMethods::CONTEXT_CARDS, PaymentMethods::COD), false, Setting::TYPE_BOOLEAN);
        SiteSettings::forget();

        $this->assertTrue(PaymentMethods::cod(PaymentMethods::CONTEXT_PRODUCTS));
        $this->assertFalse(PaymentMethods::cod(PaymentMethods::CONTEXT_CARDS));
        $this->assertContains('cod', PaymentMethods::orderTypes(PaymentMethods::CONTEXT_PRODUCTS));
        $this->assertNotContains('cod', PaymentMethods::orderTypes(PaymentMethods::CONTEXT_CARDS));
        $this->assertContains(MembershipSubscription::METHOD_COD, PaymentMethods::subscriptionMethods());
        $this->assertTrue(PaymentMethods::flags(PaymentMethods::CONTEXT_PRODUCTS)['cod']);
        $this->assertFalse(PaymentMethods::flags(PaymentMethods::CONTEXT_CARDS)['cod']);
    }

    /** @test */
    public function the_main_switch_still_hides_a_method_everywhere(): void
    {
        SiteSettings::put(PaymentMethods::COD, false, Setting::TYPE_BOOLEAN);
        SiteSettings::forget();

        foreach (PaymentMethods::CONTEXTS as $context) {
            $this->assertFalse(PaymentMethods::cod($context));
        }
    }

    /** @test */
    public function a_hidden_method_is_refused_when_ordering_cards_and_not_offered_for_the_screen(): void
    {
        SiteSettings::put(PaymentMethods::COD, true, Setting::TYPE_BOOLEAN);
        SiteSettings::put(PaymentMethods::contextSlug(PaymentMethods::CONTEXT_CARDS, PaymentMethods::COD), false, Setting::TYPE_BOOLEAN);
        SiteSettings::forget();

        $user = User::factory()->create(['name' => 'Ahmed Ali Hassan', 'gender' => 'male', 'phone' => '01062587475']);
        $card = Membership::factory()->create(['user_id' => $user->id, 'is_active' => true, 'is_visible' => true, 'plan' => Membership::PLAN_FULL, 'expiration_date' => now()->addMonths(6)]);
        MemberPayment::query()->create(['membership_id' => $card->id, 'amount' => 700, 'months_paid' => 12, 'from_date' => now()->toDateString(), 'to_date' => now()->addYear()->toDateString(), 'type' => 'cash']);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/mobile/physical-cards')->assertOk()->assertJsonPath('data.payment_methods.cod', false);

        $this->postJson('/api/v1/mobile/physical-cards/orders', [
            'items' => [['membership_number' => $card->membership_number, 'quantity' => 1]],
            'customer_full_name' => 'Ahmed Ali', 'customer_phone' => '01062587475', 'customer_address' => '1 Tahrir St',
            'payment_type' => 'cod',
        ])->assertUnprocessable()->assertJsonValidationErrors('payment_type');
    }
}
