<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\Order;
use App\Models\Product;
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
}
