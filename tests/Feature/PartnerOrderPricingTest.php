<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Support\ShopDelivery;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a storefront order is actually charged.
 *
 * `new_price` on a product is the MEMBER price and `old_price` is what anyone
 * else pays. The discount is earned by a CARD, not by the membership box being
 * filled in, so the number is looked up as the order is written: missing,
 * unknown, hidden, switched off or expired all pay the full price.
 *
 * These are the tests that would have caught DL-AFH6MJGD, which was written at
 * 7,749.82 with no membership number at all when it should have been 11,004.74.
 */
class PartnerOrderPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.partner_api.key' => 'partner-test-key']);

        /* No delivery unless a test sets it, so the price tests read the lines alone. */
        $this->deliverySettings(0, 0);
    }

    /** Delivery lives in this application's settings; callers cannot set it on an order. */
    private function deliverySettings(float $cost, float $price, ?float $threshold = null): void
    {
        SiteSettings::put(ShopDelivery::COST, $cost, Setting::TYPE_NUMBER);
        SiteSettings::put(ShopDelivery::PRICE, $price, Setting::TYPE_NUMBER);
        SiteSettings::put(ShopDelivery::THRESHOLD, $threshold ?? '', Setting::TYPE_NUMBER);
    }

    /**
     * A product marked down from 1,000 to 750.
     */
    private function markedDownProduct(): Product
    {
        return Product::query()->create([
            'name' => ['en' => 'Steam autoclave', 'ar' => 'أوتوكلاف بخاري'],
            'slug' => 'steam-autoclave',
            'old_price' => 1000,
            'new_price' => 750,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function placeOrder(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('X-Api-Key', 'partner-test-key')
            ->postJson('/api/v1/partner/orders', array_merge([
                'customer_full_name' => 'Mona Adel',
                'customer_phone' => '01000000000',
                'customer_address' => 'Flat 4, 12 Nile Street, Giza',
                'payment_type' => 'cod',
                'items' => [['slug' => 'steam-autoclave', 'quantity' => 2]],
            ], $overrides));
    }

    /** @test */
    public function an_order_with_no_membership_number_is_charged_the_full_price(): void
    {
        $this->markedDownProduct();

        $this->placeOrder()->assertCreated();

        $order = Order::query()->with('products')->sole();

        $this->assertSame('2000.00', $order->total_amount);
        $this->assertSame('2000.00', $order->total_amount_before_discount);

        $line = $order->products->sole();

        $this->assertSame('1000.00', $line->new_price);
        $this->assertSame('2000.00', $line->line_total);
        /* No markdown is archived: the buyer paid that figure, so striking it
           through on the receipt would show a saving they did not get. */
        $this->assertNull($line->old_price);
    }

    /** @test */
    public function an_order_with_a_valid_card_is_charged_the_member_price(): void
    {
        $this->markedDownProduct();

        $membership = Membership::factory()->active()->create(['is_visible' => true]);

        $this->placeOrder(['membership_number' => $membership->membership_number])
            ->assertCreated();

        $order = Order::query()->with('products')->sole();

        $this->assertSame('1500.00', $order->total_amount);
        $this->assertSame('2000.00', $order->total_amount_before_discount);

        $line = $order->products->sole();

        $this->assertSame('750.00', $line->new_price);
        $this->assertSame('1000.00', $line->old_price);
        $this->assertSame('1500.00', $line->line_total);
    }

    /**
     * The slug is what a member sees in their own card URL, so it is what they
     * are most likely to paste into the box.
     *
     * @test
     */
    public function the_card_slug_earns_the_member_price_too(): void
    {
        $this->markedDownProduct();

        $membership = Membership::factory()->active()->create(['is_visible' => true]);

        $this->placeOrder(['membership_number' => $membership->slug])->assertCreated();

        $this->assertSame('1500.00', Order::query()->sole()->total_amount);
    }

    /**
     * The whole point of looking the number up rather than checking the box is
     * filled in: without it, any string would buy the discount.
     *
     * @test
     */
    public function a_number_nobody_recognises_pays_the_full_price(): void
    {
        $this->markedDownProduct();

        $this->placeOrder(['membership_number' => 'NOT-A-CARD'])->assertCreated();

        $this->assertSame('2000.00', Order::query()->sole()->total_amount);
        /* Still recorded: what the buyer typed is worth keeping even when it
           bought them nothing. */
        $this->assertSame('NOT-A-CARD', Order::query()->sole()->membership_number);
    }

    /** @test */
    public function an_expired_card_pays_the_full_price(): void
    {
        $this->markedDownProduct();

        $membership = Membership::factory()->create([
            'is_active' => true,
            'is_visible' => true,
            'expiration_date' => now()->subDay(),
        ]);

        $this->placeOrder(['membership_number' => $membership->membership_number])
            ->assertCreated();

        $this->assertSame('2000.00', Order::query()->sole()->total_amount);
    }

    /** @test */
    public function a_switched_off_card_pays_the_full_price(): void
    {
        $this->markedDownProduct();

        $membership = Membership::factory()->inactive()->create(['is_visible' => true]);

        $this->placeOrder(['membership_number' => $membership->membership_number])
            ->assertCreated();

        $this->assertSame('2000.00', Order::query()->sole()->total_amount);
    }

    /**
     * A hidden card answers "no such card" at the partner lookup, so the
     * checkout tells the buyer it was not found. It must not then quietly
     * price as one.
     *
     * @test
     */
    public function a_hidden_card_pays_the_full_price(): void
    {
        $this->markedDownProduct();

        $membership = Membership::factory()->active()->create(['is_visible' => false]);

        $this->placeOrder(['membership_number' => $membership->membership_number])
            ->assertCreated();

        $this->assertSame('2000.00', Order::query()->sole()->total_amount);
    }

    /**
     * A product that has only ever had one price has no member discount to
     * withhold — a non-member must not be charged something that does not
     * exist.
     *
     * @test
     */
    public function a_product_that_was_never_marked_down_costs_the_same_either_way(): void
    {
        Product::query()->create([
            'name' => ['en' => 'Gloves', 'ar' => 'قفازات'],
            'slug' => 'steam-autoclave',
            'old_price' => null,
            'new_price' => 50,
        ]);

        $this->placeOrder()->assertCreated();

        $order = Order::query()->with('products')->sole();

        $this->assertSame('100.00', $order->total_amount);
        $this->assertSame('100.00', $order->total_amount_before_discount);
        $this->assertNull($order->products->sole()->old_price);
    }

    /**
     * An `old_price` at or below the selling price is a data slip, not a
     * markdown. It must never make the full price the cheaper of the two.
     *
     * @test
     */
    public function a_mis_keyed_old_price_never_undercuts_the_member_price(): void
    {
        Product::query()->create([
            'name' => ['en' => 'Nebuliser', 'ar' => 'جهاز بخار'],
            'slug' => 'steam-autoclave',
            'old_price' => 400,
            'new_price' => 500,
        ]);

        $this->placeOrder()->assertCreated();

        $this->assertSame('1000.00', Order::query()->sole()->total_amount);
    }

    /**
     * A basket can sit in a session for an hour. `is_purchasable` is enforced
     * where the order is actually written, not only on the storefront, so a
     * product taken off sale in the meantime never becomes a sale.
     *
     * @test
     */
    public function a_product_taken_off_sale_cannot_be_ordered(): void
    {
        Product::query()->create([
            'name' => ['en' => 'Steam autoclave', 'ar' => 'أوتوكلاف بخاري'],
            'slug' => 'steam-autoclave',
            'new_price' => 750,
            'is_purchasable' => false,
        ]);

        $this->placeOrder()->assertUnprocessable();

        $this->assertSame(0, Order::query()->count());
    }

    /**
     * One unsellable line is not a dead basket: the rest of it is still a
     * sale, exactly as a withdrawn product already behaves.
     *
     * @test
     */
    public function an_unsellable_line_drops_out_and_the_rest_of_the_order_stands(): void
    {
        $this->markedDownProduct();

        Product::query()->create([
            'name' => ['en' => 'Gloves', 'ar' => 'قفازات'],
            'slug' => 'gloves',
            'new_price' => 50,
            'is_purchasable' => false,
        ]);

        $this->placeOrder([
            'items' => [
                ['slug' => 'steam-autoclave', 'quantity' => 2],
                ['slug' => 'gloves', 'quantity' => 4],
            ],
        ])->assertCreated();

        $order = Order::query()->with('products')->sole();

        $this->assertSame('2000.00', $order->total_amount);
        $this->assertSame(['steam-autoclave'], $order->products->pluck('slug')->all());
    }

    /**
     * Hidden from the shop window is not off sale. Somebody who already has it
     * in a basket must still be able to check out.
     *
     * @test
     */
    public function a_product_hidden_from_the_listing_can_still_be_ordered(): void
    {
        Product::query()->create([
            'name' => ['en' => 'Steam autoclave', 'ar' => 'أوتوكلاف بخاري'],
            'slug' => 'steam-autoclave',
            'new_price' => 750,
            'is_visible' => false,
        ]);

        $this->placeOrder()->assertCreated();

        $this->assertSame('1500.00', Order::query()->sole()->total_amount);
    }

    /**
     * Delivery is charged on top of the lines and archived on the order: what
     * it cost us, what the buyer paid, and the difference.
     *
     * @test
     */
    public function delivery_is_added_to_the_total_and_archived_on_the_order(): void
    {
        $this->markedDownProduct();

        $this->deliverySettings(35, 50);

        $this->placeOrder()->assertCreated();

        $order = Order::query()->sole();

        /* 2 x 1,000 at the full price, plus the 50 delivery. */
        $this->assertSame('2050.00', $order->total_amount);
        $this->assertSame('2050.00', $order->total_amount_before_discount);
        $this->assertSame('35.00', $order->delivery_cost);
        $this->assertSame('50.00', $order->delivery_price);
        $this->assertSame('15.00', $order->delivery_profit);
    }

    /**
     * Delivery is the shop's own arrangement, set in the settings here: a caller
     * that sends figures of its own — its price, its cost, a profit — is not
     * believed, and the order is written with the settings' `price - cost`.
     *
     * @test
     */
    public function a_callers_own_delivery_figures_are_ignored(): void
    {
        $this->markedDownProduct();
        $this->deliverySettings(35, 50);

        $this->placeOrder([
            'delivery_cost' => 1,
            'delivery_price' => 1,
            'delivery_profit' => 9999,
        ])->assertCreated();

        $order = Order::query()->sole();

        $this->assertSame('50.00', $order->delivery_price);
        $this->assertSame('15.00', $order->delivery_profit);
    }

    /**
     * Delivery goes on both totals, so the gap between them stays exactly the
     * discount the card earned rather than absorbing the delivery charge.
     *
     * @test
     */
    public function delivery_does_not_change_what_the_membership_discount_reads_as(): void
    {
        $this->markedDownProduct();

        $membership = Membership::factory()->active()->create(['is_visible' => true]);
        $this->deliverySettings(0, 50);

        $this->placeOrder([
            'membership_number' => $membership->membership_number,
        ])->assertCreated();

        $order = Order::query()->sole();

        $this->assertSame('1550.00', $order->total_amount);
        $this->assertSame('2050.00', $order->total_amount_before_discount);
        $this->assertSame(
            500.0,
            (float) $order->total_amount_before_discount - (float) $order->total_amount,
        );
    }

    /**
     * A shop whose delivery price is set to nothing charges none.
     *
     * @test
     */
    public function a_shop_with_no_delivery_price_charges_none(): void
    {
        $this->markedDownProduct();
        $this->deliverySettings(0, 0);

        $this->placeOrder()->assertCreated();

        $order = Order::query()->sole();

        $this->assertSame('2000.00', $order->total_amount);
        $this->assertSame('0.00', $order->delivery_price);
        $this->assertSame('0.00', $order->delivery_profit);
    }

    /**
     * The buyer is told what they were charged for delivery, and no more: the
     * cost and the profit are the shop's own figures.
     *
     * @test
     */
    public function the_partner_resource_carries_the_delivery_price_only(): void
    {
        $this->markedDownProduct();

        $this->deliverySettings(35, 50);

        $response = $this->placeOrder()->assertCreated();

        $response->assertJsonPath('order.delivery_price', 50);
        $response->assertJsonMissingPath('order.delivery_cost');
        $response->assertJsonMissingPath('order.delivery_profit');
    }
}
