<?php

namespace Tests\Feature;

use App\Models\AdminNotification;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Throwaway DB only (name ends in _test). */
class AdminNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_order_files_a_bell_notification_pointing_at_the_order(): void
    {
        $order = Order::query()->create([
            'order_code' => 'DL-TESTBELL', 'total_amount' => 100, 'total_amount_before_discount' => 100, 'total_paid' => 0,
            'customer_full_name' => 'Buyer', 'customer_phone' => '01000000000', 'customer_address' => 'x',
            'payment_type' => 'cod', 'payment_status' => 'pending', 'delivery_status' => 'pending', 'source' => Order::SOURCE_STOREFRONT,
        ]);

        $n = AdminNotification::query()->where('type', 'order')->latest('id')->first();

        $this->assertNotNull($n);
        $this->assertStringContainsString('DL-TESTBELL', $n->title['en']);
        $this->assertSame('/admin/order/DL-TESTBELL', $n->url);
        $this->assertSame($order->id, $n->data['order_id']);
    }
}
