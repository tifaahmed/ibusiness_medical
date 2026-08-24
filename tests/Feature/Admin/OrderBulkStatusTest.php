<?php

namespace Tests\Feature\Admin;

use App\Enums\Order\DeliveryStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Order\PaymentStatusEnum;
use App\Enums\Order\PaymentTypeEnum;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderBulkStatusTest extends TestCase
{
    use RefreshDatabase;

    private function adminWith(string $permission): User
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate($permission.'-role', 'web');
        $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        $user->assignRole($role);

        return $user;
    }

    private function makeOrder(string $code, ?OrderStatusEnum $status = null): Order
    {
        return Order::create([
            'order_code' => $code,
            'total_paid' => 100.00,
            'total_amount' => 100.00,
            'customer_full_name' => 'Ahmed Mahmoud',
            'customer_phone' => '01001234567',
            'payment_status' => PaymentStatusEnum::PENDING,
            'delivery_status' => DeliveryStatusEnum::PENDING,
            'order_status' => $status ?? OrderStatusEnum::PENDING,
            'payment_type' => PaymentTypeEnum::COD,
        ]);
    }

    public function test_a_new_order_starts_pending(): void
    {
        $order = Order::create([
            'order_code' => 'DL-AAAA1111',
            'total_paid' => 10.00,
            'total_amount' => 10.00,
            'customer_full_name' => 'Mona Abdelrahman',
            'customer_phone' => '01555667788',
            'payment_status' => PaymentStatusEnum::PENDING,
            'delivery_status' => DeliveryStatusEnum::PENDING,
            'payment_type' => PaymentTypeEnum::COD,
        ]);

        $this->assertSame(OrderStatusEnum::PENDING, $order->fresh()->order_status);
    }

    public function test_bulk_status_requires_a_manage_permission(): void
    {
        $order = $this->makeOrder('DL-BBBB2222');

        $this->actingAs($this->adminWith('view orders'))
            ->post(route('admin.order.bulk-status'), [
                'ids' => [$order->id],
                'order_status' => OrderStatusEnum::SUCCESS->value,
            ])
            ->assertForbidden();

        $this->assertSame(OrderStatusEnum::PENDING, $order->fresh()->order_status);
    }

    public function test_it_moves_every_selected_order_and_leaves_the_rest_alone(): void
    {
        $first = $this->makeOrder('DL-CCCC3333');
        $second = $this->makeOrder('DL-DDDD4444');
        $untouched = $this->makeOrder('DL-EEEE5555');

        $this->actingAs($this->adminWith('manage orders'))
            ->post(route('admin.order.bulk-status'), [
                'ids' => [$first->id, $second->id],
                'order_status' => OrderStatusEnum::SUCCESS->value,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(OrderStatusEnum::SUCCESS, $first->fresh()->order_status);
        $this->assertSame(OrderStatusEnum::SUCCESS, $second->fresh()->order_status);
        $this->assertSame(OrderStatusEnum::PENDING, $untouched->fresh()->order_status);
    }

    public function test_it_logs_one_attributed_row_per_order_that_actually_moved(): void
    {
        $admin = $this->adminWith('manage orders');
        $moving = $this->makeOrder('DL-FFFF6666');
        $already = $this->makeOrder('DL-GGGG7777', OrderStatusEnum::FAILED);

        $this->actingAs($admin)
            ->post(route('admin.order.bulk-status'), [
                'ids' => [$moving->id, $already->id],
                'order_status' => OrderStatusEnum::FAILED->value,
            ])
            ->assertRedirect();

        $logs = OrderLog::query()
            ->where('action', OrderLog::ACTION_ORDER_STATUS_CHANGED)
            ->get();

        $this->assertCount(1, $logs, 'An order already at the target status should not be logged.');
        $this->assertSame($moving->id, $logs->first()->order_id);
        $this->assertSame($admin->id, $logs->first()->admin_id);
        $this->assertSame(OrderStatusEnum::PENDING->value, $logs->first()->old_values['order_status']);
        $this->assertSame(OrderStatusEnum::FAILED->value, $logs->first()->new_values['order_status']);
    }

    public function test_it_refuses_an_unknown_status(): void
    {
        $order = $this->makeOrder('DL-HHHH8888');

        $this->actingAs($this->adminWith('manage orders'))
            ->post(route('admin.order.bulk-status'), [
                'ids' => [$order->id],
                'order_status' => 'shipped',
            ])
            ->assertSessionHasErrors('order_status');

        $this->assertSame(OrderStatusEnum::PENDING, $order->fresh()->order_status);
    }

    public function test_it_refuses_an_empty_selection(): void
    {
        $this->actingAs($this->adminWith('manage orders'))
            ->post(route('admin.order.bulk-status'), [
                'ids' => [],
                'order_status' => OrderStatusEnum::SUCCESS->value,
            ])
            ->assertSessionHasErrors('ids');
    }

    public function test_the_list_filters_by_order_status(): void
    {
        $failed = $this->makeOrder('DL-IIII9999', OrderStatusEnum::FAILED);
        $this->makeOrder('DL-JJJJ1010', OrderStatusEnum::SUCCESS);

        $response = $this->actingAs($this->adminWith('view orders'))
            ->get(route('admin.order.list', ['order_status' => OrderStatusEnum::FAILED->value]))
            ->assertOk();

        $orders = $response->viewData('page')['props']['orders']['data'];

        $this->assertCount(1, $orders);
        $this->assertSame($failed->order_code, $orders[0]['order_code']);
        $this->assertSame(OrderStatusEnum::FAILED->value, $orders[0]['order_status']['value']);
    }
}
