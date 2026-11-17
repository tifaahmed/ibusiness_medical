<?php

namespace Tests\Feature\Admin;

use App\Enums\Order\DeliveryStatusEnum;
use App\Enums\Order\PaymentStatusEnum;
use App\Enums\Order\PaymentTypeEnum;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderMarkTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $permission): User
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate($permission.'-role', 'web');
        $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        $user->assignRole($role);

        return $user;
    }

    private function order(): Order
    {
        return Order::create([
            'order_code' => 'DL-MARK0001', 'total_paid' => 0, 'total_amount' => 100, 'customer_full_name' => 'Sara', 'customer_phone' => '01000000001',
            'payment_status' => PaymentStatusEnum::PENDING, 'delivery_status' => DeliveryStatusEnum::PENDING, 'payment_type' => PaymentTypeEnum::COD,
        ]);
    }

    public function test_an_admin_marks_an_order_done_or_test_and_clears_it(): void
    {
        $order = $this->order();
        $admin = $this->admin('manage orders');

        $this->actingAs($admin)->post(route('admin.order.mark', $order->order_code), ['mark' => 'test'])->assertRedirect();
        $this->assertSame('test', $order->fresh()->admin_mark);

        $this->actingAs($admin)->post(route('admin.order.mark', $order->order_code), ['mark' => 'done']);
        $this->assertSame('done', $order->fresh()->admin_mark);

        $this->actingAs($admin)->post(route('admin.order.mark', $order->order_code), ['mark' => null]);
        $this->assertNull($order->fresh()->admin_mark);
    }

    public function test_a_viewer_cannot_mark_and_a_wrong_value_is_refused(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin('view orders'))->post(route('admin.order.mark', $order->order_code), ['mark' => 'done'])->assertForbidden();
        $this->actingAs($this->admin('manage orders'))->post(route('admin.order.mark', $order->order_code), ['mark' => 'x'])->assertSessionHasErrors('mark');
        $this->assertNull($order->fresh()->admin_mark);
    }
}
