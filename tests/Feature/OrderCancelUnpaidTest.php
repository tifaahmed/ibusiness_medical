<?php

namespace Tests\Feature;

use App\Enums\Order\PaymentStatusEnum;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Support\ShopDelivery;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/** A buyer may drop an order that was never paid (cancelled, not deleted). Run on a throwaway database (see AGENTS.md). */
class OrderCancelUnpaidTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->showAllPaymentMethods();
        config(['services.partner_api.key' => 'partner-test-key']);
        SiteSettings::put(ShopDelivery::COST, 20, Setting::TYPE_NUMBER);
        SiteSettings::put(ShopDelivery::PRICE, 50, Setting::TYPE_NUMBER);
        SiteSettings::put(ShopDelivery::WALLET, '01000000000');
    }

    private function placeCod(): Order
    {
        Http::fake();
        $product = Product::query()->create([
            'name' => ['en' => 'Coffee', 'ar' => 'قهوة'], 'slug' => 'coffee-'.Str::lower(Str::random(6)),
            'old_price' => 100, 'new_price' => 100, 'is_purchasable' => true, 'is_visible' => true,
        ]);
        $this->withHeader('X-Api-Key', 'partner-test-key')->postJson('/api/v1/partner/orders', [
            'customer_full_name' => 'Ahmed Ali', 'customer_phone' => '01012345678', 'customer_address' => 'Cairo, street 1',
            'payment_type' => 'cod', 'items' => [['slug' => $product->slug, 'quantity' => 1]],
        ])->assertCreated();

        return Order::query()->latest('id')->firstOrFail();
    }

    public function test_an_unpaid_guest_order_is_cancelled_not_deleted(): void
    {
        $order = $this->placeCod();

        $this->postJson('/api/v1/mobile/orders/'.$order->order_code.'/cancel')->assertOk();

        $this->assertSame(PaymentStatusEnum::CANCELED, $order->fresh()->payment_status);
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_a_paid_order_cannot_be_cancelled_here(): void
    {
        $order = $this->placeCod();
        $order->forceFill(['payment_status' => PaymentStatusEnum::ACCEPTED, 'total_paid' => $order->total_amount])->save();

        $this->postJson('/api/v1/mobile/orders/'.$order->order_code.'/cancel')->assertStatus(422);
        $this->assertSame(PaymentStatusEnum::ACCEPTED, $order->fresh()->payment_status);
    }
}
