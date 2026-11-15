<?php

namespace Tests\Feature\Admin;

use App\Enums\Order\DeliveryStatusEnum;
use App\Enums\Order\PaymentStatusEnum;
use App\Enums\Order\PaymentTypeEnum;
use App\Http\Resources\Admin\Order\List\AdminOrderListResource;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/** The admin orders list says how much was real money, how much wallet, how much points, and where the order came from. */
class OrderPaymentBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $code, ?string $source, array $extra = []): Order
    {
        return Order::create($extra + [
            'order_code' => $code, 'source' => $source,
            'total_paid' => 100.00, 'total_amount' => 100.00, 'delivery_price' => 0, 'delivery_cost' => 0, 'delivery_profit' => 0,
            'customer_full_name' => 'Sara Kamal', 'customer_phone' => '01000000001',
            'payment_status' => PaymentStatusEnum::ACCEPTED, 'delivery_status' => DeliveryStatusEnum::PENDING, 'payment_type' => PaymentTypeEnum::COD,
        ]);
    }

    public function test_real_money_is_what_is_left_after_the_wallet_and_the_points(): void
    {
        $row = (new AdminOrderListResource($this->order('DL-BRK00001', Order::SOURCE_MOBILE_APP, ['wallet_amount' => 30, 'points_used' => 1000, 'points_amount' => 10])))->toArray(Request::create('/'));

        $this->assertSame(60.0, $row['real_paid']);
        $this->assertSame(30.0, $row['wallet_amount']);
        $this->assertSame(10.0, $row['points_amount']);
        $this->assertSame(1000, $row['points_used']);
        $this->assertSame('app', $row['channel']);
    }

    public function test_the_channel_tells_the_app_from_the_deilar_website(): void
    {
        $this->assertSame('website', (new AdminOrderListResource($this->order('DL-BRK00002', Order::SOURCE_STOREFRONT)))->toArray(Request::create('/'))['channel']);
        $this->assertSame('app', (new AdminOrderListResource($this->order('DL-BRK00003', \App\Support\PhysicalCard::SOURCE)))->toArray(Request::create('/'))['channel']);
        $this->assertNull((new AdminOrderListResource($this->order('DL-BRK00004', null)))->toArray(Request::create('/'))['channel']);
    }
}
