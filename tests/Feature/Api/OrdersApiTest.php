<?php

namespace Tests\Feature\Api;

use App\Enums\Order\PaymentStatusEnum;
use App\Enums\Order\PaymentTypeEnum;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Whole amounts are compared as integers, not floats: `json_encode` renders
 * 1650.00 as `1650`, so the decoded path holds an int and a strict comparison
 * against 1650.0 fails on a correct total.
 *
 * The key-gated order endpoints the Deilar storefront places orders through.
 *
 * The point of every test here is the same: what an order says it cost is what
 * the catalogue said when it was placed, and nothing the caller posts can
 * change that.
 */
class OrdersApiTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'partner-key-for-tests';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.partner_api.key' => self::KEY]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(string $name, array $attributes = []): Product
    {
        return Product::create([
            'name' => ['en' => $name, 'ar' => $name.' (ar)'],
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function place(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('X-Api-Key', self::KEY)
            ->postJson('/api/v1/partner/orders', $payload);
    }

    /**
     * No membership number, so the full price: `new_price` is the MEMBER price
     * and it is earned by a card, not by a basket — see
     * `PartnerOrderPricingTest` for the whole rule.
     */
    public function test_an_order_is_priced_from_the_catalogue_and_archived_on_its_lines(): void
    {
        $monitor = $this->product('Blood pressure monitor', [
            'old_price' => 1000,
            'new_price' => 750,
            'cost_price' => 500,
            'profit_price' => 250,
        ]);

        $gloves = $this->product('Gloves', ['old_price' => 50]);

        $response = $this->place([
            'customer_full_name' => 'Mona Hassan',
            'customer_phone' => '01000000000',
            'customer_address' => '12 Abbas El Akkad, Nasr City',
            'payment_type' => PaymentTypeEnum::COD->value,
            'ip_address' => '196.20.30.40',
            'items' => [
                ['slug' => $monitor->slug, 'quantity' => 2],
                ['slug' => $gloves->slug, 'quantity' => 3],
            ],
        ])->assertCreated();

        // 1000*2 + 50*3 = 2150, at the no-card price.
        $response->assertJsonPath('order.total_amount', 2150)
            ->assertJsonPath('order.payment_status.value', PaymentStatusEnum::PENDING->value)
            ->assertJsonPath('order.awaiting_receipt', false)
            ->assertJsonPath('order.items.0.quantity', 2)
            ->assertJsonPath('order.items.0.new_price', 1000)
            ->assertJsonPath('order.items.0.line_total', 2000)
            /* No markdown was given, so none is archived — on the discounted
               product because this buyer showed no card, and on the gloves
               because they were never discounted at all. */
            ->assertJsonPath('order.items.0.old_price', null)
            ->assertJsonPath('order.items.1.old_price', null)
            ->assertJsonPath('order.items.1.line_total', 150);

        $order = Order::query()->firstOrFail();

        $this->assertSame('196.20.30.40', $order->ip_address);
        $this->assertSame(Order::SOURCE_STOREFRONT, $order->source);
        $this->assertStringStartsWith('DL-', $order->order_code);

        $line = $order->products()->first();

        /* Cost and margin are archived on the line but never returned to the
           storefront, which shows this to the buyer. */
        $this->assertSame('500.00', $line->cost_price);
        $this->assertSame('250.00', $line->profit_price);
        $response->assertJsonMissingPath('order.items.0.cost_price');

        $this->assertDatabaseHas('order_logs', [
            'order_id' => $order->id,
            'action' => OrderLog::ACTION_CREATED,
        ]);
    }

    /**
     * The archive is the point: an order must keep saying what it cost after
     * the catalogue has moved on, and after the product is gone entirely.
     */
    public function test_a_line_survives_the_product_being_repriced_and_deleted(): void
    {
        $product = $this->product('Thermometer', ['new_price' => 200]);

        $this->place([
            'customer_full_name' => 'Ali Saeed',
            'customer_phone' => '01000000001',
            'customer_address' => 'Alexandria',
            'payment_type' => PaymentTypeEnum::COD->value,
            'items' => [['slug' => $product->slug, 'quantity' => 1]],
        ])->assertCreated();

        $order = Order::query()->firstOrFail();

        $product->update(['new_price' => 999]);
        $product->delete();

        $line = $order->products()->firstOrFail();

        $this->assertNull($line->product_id);
        $this->assertSame('200.00', $line->new_price);
        $this->assertSame('Thermometer', $line->getTranslation('name', 'en'));

        $this->withHeader('X-Api-Key', self::KEY)
            ->getJson('/api/v1/partner/orders/'.$order->order_code)
            ->assertOk()
            ->assertJsonPath('order.total_amount', 200)
            ->assertJsonPath('order.items.0.name', 'Thermometer');
    }

    public function test_a_wallet_order_waits_for_a_receipt_and_accepts_one_later(): void
    {
        Storage::fake('public');

        $product = $this->product('Nebuliser', ['new_price' => 1200]);

        $order = $this->place([
            'customer_full_name' => 'Sara Nabil',
            'customer_phone' => '01000000002',
            'customer_address' => 'Giza',
            'payment_type' => PaymentTypeEnum::TRANSFER_WALLET->value,
            'items' => [['slug' => $product->slug, 'quantity' => 1]],
        ])->assertCreated()->json('order');

        $this->assertTrue($order['awaiting_receipt']);

        $this->withHeader('X-Api-Key', self::KEY)
            ->post('/api/v1/partner/orders/'.$order['order_code'].'/receipt', [
                'receipt' => UploadedFile::fake()->image('transfer.jpg'),
            ])
            ->assertCreated()
            ->assertJsonPath('order.awaiting_receipt', false)
            ->assertJsonCount(1, 'order.receipts')
            /* A receipt is a claim, not a confirmation — an admin still has to
               check it against the wallet. */
            ->assertJsonPath('order.payment_status.value', PaymentStatusEnum::PENDING->value);
    }

    public function test_a_cash_on_delivery_order_refuses_a_receipt(): void
    {
        Storage::fake('public');

        $product = $this->product('Crutches', ['new_price' => 300]);

        $order = $this->place([
            'customer_full_name' => 'Omar Fathy',
            'customer_phone' => '01000000003',
            'customer_address' => 'Tanta',
            'payment_type' => PaymentTypeEnum::COD->value,
            'items' => [['slug' => $product->slug, 'quantity' => 1]],
        ])->assertCreated()->json('order');

        $this->withHeader('X-Api-Key', self::KEY)
            ->post('/api/v1/partner/orders/'.$order['order_code'].'/receipt', [
                'receipt' => UploadedFile::fake()->image('transfer.jpg'),
            ])
            ->assertStatus(422);
    }

    public function test_the_endpoints_are_closed_without_the_partner_key(): void
    {
        $product = $this->product('Stethoscope', ['new_price' => 400]);

        $this->postJson('/api/v1/partner/orders', [
            'customer_full_name' => 'Nobody',
            'customer_phone' => '01000000004',
            'customer_address' => 'Nowhere',
            'payment_type' => PaymentTypeEnum::COD->value,
            'items' => [['slug' => $product->slug, 'quantity' => 1]],
        ])->assertUnauthorized();

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_an_order_of_nothing_available_is_refused_rather_than_written_empty(): void
    {
        $this->place([
            'customer_full_name' => 'Ghost Buyer',
            'customer_phone' => '01000000005',
            'customer_address' => 'Cairo',
            'payment_type' => PaymentTypeEnum::COD->value,
            'items' => [['slug' => 'withdrawn-product', 'quantity' => 1]],
        ])->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_an_unknown_code_is_a_404_rather_than_someone_elses_order(): void
    {
        $this->withHeader('X-Api-Key', self::KEY)
            ->getJson('/api/v1/partner/orders/DL-NOTREAL')
            ->assertNotFound();
    }
}
