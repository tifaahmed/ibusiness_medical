<?php

namespace Tests\Feature\Admin;

use App\Enums\Order\DeliveryStatusEnum;
use App\Enums\Order\PaymentStatusEnum;
use App\Enums\Order\PaymentTypeEnum;
use App\Enums\User\UserRoleEnum;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderListTest extends TestCase
{
    use RefreshDatabase;

    private function adminWith(string $permission): User
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web');
        $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        $user->assignRole($role);

        return $user;
    }

    private function seedOrders(): void
    {
        Order::create([
            'order_code' => 'ORD-2026-000201',
            'total_paid' => 250.00,
            'total_amount' => 300.00,
            'total_amount_before_discount' => 375.00,
            'customer_full_name' => 'أحمد محمود علي',
            'customer_phone' => '01001234567',
            'membership_number' => 'M-100001',
            'payment_status' => PaymentStatusEnum::ACCEPTED,
            'delivery_status' => DeliveryStatusEnum::ON_DELIVERY,
            'payment_type' => PaymentTypeEnum::TRANSFER_WALLET,
        ]);
        Order::create([
            'order_code' => 'ORD-2026-000202',
            'total_paid' => 90.00,
            'total_amount' => 90.00,
            'total_amount_before_discount' => null,
            'customer_full_name' => 'منى عبد الرحمن',
            'customer_phone' => '01555667788',
            'membership_number' => null,
            'payment_status' => PaymentStatusEnum::REJECTED,
            'delivery_status' => DeliveryStatusEnum::PENDING,
            'payment_type' => PaymentTypeEnum::COD,
            'cancel_reason' => 'Invalid receipt',
        ]);
    }

    public function test_list_requires_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('admin.order.list'))->assertForbidden();
    }

    public function test_list_renders_orders_with_enum_values(): void
    {
        $this->seedOrders();

        $response = $this->actingAs($this->adminWith('manage orders'))->get(route('admin.order.list'));
        $response->assertOk();

        $rows = collect($response->viewData('page')['props']['orders']['data'] ?? []);
        $this->assertCount(2, $rows);

        $first = $rows->firstWhere('order_code', 'ORD-2026-000201');
        $this->assertSame('accepted', $first['payment_status']['value']);
        $this->assertSame('on-delivery', $first['delivery_status']['value']);
        $this->assertSame('transfer-wallet', $first['payment_type']['value']);
        $this->assertSame(300.0, $first['total_amount']);
        $this->assertSame(375.0, $first['total_amount_before_discount']);
        $this->assertSame('M-100001', $first['membership_number']);

        $second = $rows->firstWhere('order_code', 'ORD-2026-000202');
        $this->assertSame('rejected', $second['payment_status']['value']);
        $this->assertNull($second['membership_number']);
        $this->assertSame('Invalid receipt', $second['cancel_reason']);
    }

    public function test_own_permission_grants_access(): void
    {
        $response = $this->actingAs($this->adminWith('manage own orders'))->get(route('admin.order.list'));
        $response->assertOk();
    }

    public function test_filters_narrow_results(): void
    {
        $this->seedOrders();

        $admin = $this->adminWith('manage orders');

        $byStatus = collect(
            $this->actingAs($admin)
                ->get(route('admin.order.list', ['payment_status' => 'rejected']))
                ->viewData('page')['props']['orders']['data']
        );
        $this->assertSame(['ORD-2026-000202'], $byStatus->pluck('order_code')->all());

        $bySearch = collect(
            $this->actingAs($admin)
                ->get(route('admin.order.list', ['search' => '01001234567']))
                ->viewData('page')['props']['orders']['data']
        );
        $this->assertSame(['ORD-2026-000201'], $bySearch->pluck('order_code')->all());

        // Tampered filter values fall back to "no filter", not an error page.
        $this->actingAs($admin)
            ->get(route('admin.order.list', ['delivery_status' => 'flying']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('orders.data', 2));
    }
}
