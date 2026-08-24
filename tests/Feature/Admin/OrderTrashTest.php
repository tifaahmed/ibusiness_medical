<?php

namespace Tests\Feature\Admin;

use App\Enums\Order\DeliveryStatusEnum;
use App\Enums\Order\PaymentStatusEnum;
use App\Enums\Order\PaymentTypeEnum;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\OrderProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderTrashTest extends TestCase
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

    private function makeOrder(string $code = 'DL-TRASH001'): Order
    {
        return Order::create([
            'order_code' => $code,
            'total_paid' => 50.00,
            'total_amount' => 20.00,
            'delivery_price' => 10.00,
            'delivery_cost' => 8.00,
            'delivery_profit' => 2.00,
            'customer_full_name' => 'Sara Kamal',
            'customer_phone' => '01000000001',
            'payment_status' => PaymentStatusEnum::ACCEPTED,
            'delivery_status' => DeliveryStatusEnum::PENDING,
            'payment_type' => PaymentTypeEnum::COD,
        ]);
    }

    public function test_delete_moves_the_order_to_the_trash_rather_than_erasing_it(): void
    {
        $admin = $this->adminWith('manage orders');
        $order = $this->makeOrder();

        $this->actingAs($admin)
            ->delete(route('admin.order.destroy', $order->order_code))
            ->assertRedirect(route('admin.order.list'));

        $this->assertSoftDeleted('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('order_logs', [
            'order_id' => $order->id,
            'admin_id' => $admin->id,
            'action' => OrderLog::ACTION_DELETED,
        ]);
    }

    public function test_a_trashed_order_leaves_the_list_and_appears_in_the_trash(): void
    {
        $admin = $this->adminWith('manage orders');
        $live = $this->makeOrder('DL-LIVE0001');
        $trashed = $this->makeOrder('DL-GONE0001');
        $trashed->delete();

        $this->actingAs($admin)->get(route('admin.order.list'))
            ->assertOk()
            ->assertSee('DL-LIVE0001')
            ->assertDontSee('DL-GONE0001');

        $this->actingAs($admin)->get(route('admin.order.trash'))
            ->assertOk()
            ->assertSee('DL-GONE0001')
            ->assertDontSee('DL-LIVE0001');
    }

    public function test_the_trash_route_is_not_swallowed_by_the_order_code_wildcard(): void
    {
        $admin = $this->adminWith('manage orders');

        /* `trash` matches the show route's `[A-Za-z0-9-]+` binding, so this
           asserts the ordering in routes/web.php, not just the controller. */
        $response = $this->actingAs($admin)->get('/admin/order/trash');

        $response->assertOk();
        $this->assertSame(
            'Admin/Order/Trash',
            $response->viewData('page')['component'],
        );
    }

    public function test_a_trashed_order_can_still_be_opened_but_not_edited(): void
    {
        $admin = $this->adminWith('manage orders');
        $order = $this->makeOrder();
        $order->delete();

        $this->actingAs($admin)
            ->get(route('admin.order.show', $order->order_code))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.order.edit', $order->order_code))
            ->assertNotFound();
    }

    public function test_restore_puts_the_order_back_on_the_list(): void
    {
        $admin = $this->adminWith('manage orders');
        $order = $this->makeOrder();
        $order->delete();

        $this->actingAs($admin)
            ->post(route('admin.order.restore', $order->id))
            ->assertRedirect(route('admin.order.trash'));

        $this->assertNotSoftDeleted('orders', ['id' => $order->id]);
    }

    public function test_force_delete_erases_the_order_and_its_lines_but_keeps_the_audit_trail(): void
    {
        $admin = $this->adminWith('manage orders');
        $order = $this->makeOrder();
        OrderProduct::create([
            'order_id' => $order->id,
            'name' => ['en' => 'Widget', 'ar' => 'منتج'],
            'quantity' => 1,
            'new_price' => 10.00,
            'line_total' => 10.00,
        ]);
        $order->delete();

        $this->actingAs($admin)
            ->delete(route('admin.order.force-delete', $order->id))
            ->assertRedirect(route('admin.order.trash'));

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('order_products', ['order_id' => $order->id]);
        /* `order_logs.order_id` is nulled rather than cascaded: who deleted
           what outlives the row it was done to. */
        $this->assertDatabaseHas('order_logs', [
            'admin_id' => $admin->id,
            'action' => OrderLog::ACTION_DELETED,
            'order_id' => null,
        ]);
    }

    public function test_force_delete_refuses_an_order_that_is_not_in_the_trash(): void
    {
        $admin = $this->adminWith('manage orders');
        $order = $this->makeOrder();

        $this->actingAs($admin)
            ->delete(route('admin.order.force-delete', $order->id))
            ->assertNotFound();

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_a_read_only_admin_may_see_the_trash_but_not_act_on_it(): void
    {
        $viewer = $this->adminWith('view orders');
        $order = $this->makeOrder();
        $order->delete();

        $this->actingAs($viewer)->get(route('admin.order.trash'))->assertOk();

        $this->actingAs($viewer)
            ->post(route('admin.order.restore', $order->id))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->delete(route('admin.order.force-delete', $order->id))
            ->assertForbidden();
    }
}
