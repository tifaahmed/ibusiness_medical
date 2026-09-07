<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Free delivery over a basket total.
 *
 * The storefront sets the line — it is a figure an administrator types into
 * Deilar's own shop settings — and sends it with the order. This application
 * DECIDES, because this is where the basket is actually priced: the storefront
 * only quotes a subtotal, and a card honoured here can take a basket back under
 * the line after the checkout has already shown it crossed.
 *
 * When the charge is dropped the row says why, so a zero in `delivery_price` is
 * never again ambiguous between "this shop does not charge", "this order
 * predates delivery charges" and "this basket earned it".
 */
class PartnerFreeDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.partner_api.key' => 'partner-test-key']);
    }

    /** @test */
    public function an_order_under_the_line_is_charged_for_delivery(): void
    {
        $product = $this->product(newPrice: 400);

        $this->placeOrder($product, quantity: 2, threshold: 1000)
            ->assertCreated();

        $order = Order::query()->latest('id')->first();

        // 800 against a line of 1,000.
        $this->assertSame('50.00', $order->delivery_price);
        $this->assertNull($order->delivery_free_reason);
        // The line is archived even though it was not reached, so the order
        // still says what the offer was on the day it was placed.
        $this->assertSame('1000.00', $order->free_delivery_threshold);
    }

    /** @test */
    public function an_order_over_the_line_is_not_charged_and_says_why(): void
    {
        $product = $this->product(newPrice: 400);

        $this->placeOrder($product, quantity: 3, threshold: 1000)
            ->assertCreated();

        $order = Order::query()->latest('id')->first();

        $this->assertSame('0.00', $order->delivery_price);
        $this->assertSame(Order::DELIVERY_FREE_THRESHOLD_REACHED, $order->delivery_free_reason);
        $this->assertSame('1000.00', $order->free_delivery_threshold);

        /*
         * The profit goes negative by the whole delivery cost, and that is
         * right: the shop is paying a courier out of the margin on the goods.
         * Clamping it to zero would hide a real cost from the reporting.
         */
        $this->assertSame('-20.00', $order->delivery_profit);
    }

    /** @test */
    public function the_free_delivery_is_reflected_in_the_order_total(): void
    {
        $product = $this->product(newPrice: 400);

        $response = $this->placeOrder($product, quantity: 3, threshold: 1000);

        // 1,200 of goods and nothing for the courier.
        /*
         * Whole amounts asserted as ints, not floats: `json_encode` renders
         * 1200.00 as `1200`, so the JSON holds an int and a strict comparison
         * against 1200.0 fails on a correct total.
         */
        $response->assertJsonPath('order.total_amount', 1200)
            ->assertJsonPath('order.delivery_price', 0)
            ->assertJsonPath('order.delivery_free_reason', Order::DELIVERY_FREE_THRESHOLD_REACHED)
            ->assertJsonPath('order.free_delivery_threshold', 1000);
    }

    /** @test */
    public function an_order_with_no_threshold_behaves_exactly_as_it_always_did(): void
    {
        $product = $this->product(newPrice: 400);

        $this->placeOrder($product, quantity: 3, threshold: null)
            ->assertCreated();

        $order = Order::query()->latest('id')->first();

        $this->assertSame('50.00', $order->delivery_price);
        $this->assertNull($order->delivery_free_reason);
        $this->assertNull($order->free_delivery_threshold);
    }

    /** @test */
    public function a_threshold_of_zero_makes_every_order_free(): void
    {
        /*
         * Zero is a real answer and must not be read as "no offer". Anywhere a
         * falsy check crept in, this is the test that catches it.
         */
        $product = $this->product(newPrice: 10);

        $this->placeOrder($product, quantity: 1, threshold: 0)
            ->assertCreated();

        $order = Order::query()->latest('id')->first();

        $this->assertSame('0.00', $order->delivery_price);
        $this->assertSame(Order::DELIVERY_FREE_THRESHOLD_REACHED, $order->delivery_free_reason);
        $this->assertSame('0.00', $order->free_delivery_threshold);
    }

    /** @test */
    public function the_line_is_tested_against_the_price_the_order_is_written_at(): void
    {
        /*
         * The whole reason this decision is made HERE and not on the storefront.
         *
         * The basket is 1,000 at the full price and 800 with a card, against a
         * line of 900. A checkout quoting the full price would show free
         * delivery; the order is written at the member price, which does not
         * reach the line, so it is charged. The page and the order agree
         * because the page switches to the member figure the moment a card is
         * recognised — and if it did not, THIS is what would keep the receipt
         * honest.
         */
        $product = $this->product(newPrice: 400, oldPrice: 500);

        $membership = \App\Models\Membership::factory()->create([
            'is_active' => true,
            'is_visible' => true,
        ]);

        $this->placeOrder(
            $product,
            quantity: 2,
            threshold: 900,
            membershipNumber: $membership->membership_number,
        )->assertCreated();

        $order = Order::query()->latest('id')->first();

        /*
         * 800 of goods with the card honoured — under the 900 line — so
         * delivery is charged and the total is 850. At the FULL price the same
         * basket is 1,000 and would have cleared it, which is exactly the case
         * a storefront deciding for itself would get wrong.
         */
        $this->assertSame('850.00', $order->total_amount);
        $this->assertSame('50.00', $order->delivery_price);
        $this->assertNull($order->delivery_free_reason);
    }

    /**
     * A purchasable product. Built by hand rather than by a factory: `Product`
     * has none, and the order endpoint only needs a slug it can price.
     */
    private function product(float $newPrice, ?float $oldPrice = null): Product
    {
        return Product::query()->create([
            'name' => ['en' => 'Test product', 'ar' => 'منتج'],
            'slug' => 'test-product-'.Str::lower(Str::random(8)),
            'old_price' => $oldPrice ?? $newPrice,
            'new_price' => $newPrice,
            'is_purchasable' => true,
            'is_visible' => true,
        ]);
    }

    private function placeOrder(
        Product $product,
        int $quantity,
        ?float $threshold,
        ?string $membershipNumber = null,
    ): \Illuminate\Testing\TestResponse {
        return $this->withHeader('X-Api-Key', 'partner-test-key')
            ->postJson('/api/v1/partner/orders', array_filter([
                'items' => [['slug' => $product->slug, 'quantity' => $quantity]],
                'customer_full_name' => 'Test Buyer',
                'customer_phone' => '01062587475',
                'customer_address' => 'Somewhere',
                'payment_type' => 'cod',
                'delivery_cost' => 20,
                'delivery_price' => 50,
                'free_delivery_threshold' => $threshold,
                'membership_number' => $membershipNumber,
            ], fn ($value) => $value !== null));
    }
}
