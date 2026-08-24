<?php

namespace Tests\Feature\Admin;

use App\Enums\Order\DeliveryStatusEnum;
use App\Enums\Order\PaymentStatusEnum;
use App\Enums\Order\PaymentTypeEnum;
use App\Enums\User\UserRoleEnum;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\OrderProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderShowEditTest extends TestCase
{
    use RefreshDatabase;

    private function adminWith(string $permission): User
    {
        $user = User::factory()->create();
        /* A role per permission: `Role::findOrCreate` on one shared name would
           hand a "view orders" admin the permissions of every earlier test. */
        $role = Role::findOrCreate(UserRoleEnum::ADMIN.'-'.str_replace(' ', '-', $permission), 'web');
        $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        $user->assignRole($role);

        return $user;
    }

    private function seedOrder(): Order
    {
        $order = Order::create([
            'order_code' => 'DL-TESTCODE',
            'total_paid' => 0,
            'total_amount' => 300.00,
            'total_amount_before_discount' => 375.00,
            'customer_full_name' => 'أحمد محمود علي',
            'customer_phone' => '01001234567',
            'customer_address' => '12 Nile St, Cairo',
            'membership_number' => 'M-100001',
            'payment_status' => PaymentStatusEnum::PENDING,
            'delivery_status' => DeliveryStatusEnum::PENDING,
            'payment_type' => PaymentTypeEnum::TRANSFER_WALLET,
            'ip_address' => '41.33.10.7',
            'user_agent' => 'Mozilla/5.0 (iPhone)',
            'source' => Order::SOURCE_STOREFRONT,
        ]);

        $order->products()->create([
            'product_id' => null,
            'name' => ['ar' => 'جهاز قياس الضغط', 'en' => 'Blood pressure monitor'],
            'slug' => 'bp-monitor',
            'quantity' => 2,
            'old_price' => 200.00,
            'new_price' => 150.00,
            'line_total' => 300.00,
            'cost_price' => 100.00,
            'profit_price' => 50.00,
        ]);

        return $order->refresh();
    }

    public function test_show_requires_permission(): void
    {
        $order = $this->seedOrder();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.order.show', $order->order_code))
            ->assertForbidden();
    }

    public function test_show_renders_the_order_with_its_lines_and_logs(): void
    {
        $order = $this->seedOrder();

        $response = $this->actingAs($this->adminWith('view orders'))
            ->get(route('admin.order.show', $order->order_code));

        $response->assertOk();
        $props = $response->viewData('page')['props']['order'];

        $this->assertSame('DL-TESTCODE', $props['order_code']);
        $this->assertSame('pending', $props['payment_status']['value']);
        $this->assertSame(300.0, $props['total_amount']);
        $this->assertCount(1, $props['products']);
        $this->assertSame(2, $props['products'][0]['quantity']);
        // The archived name is the whole map, not the request's locale.
        $this->assertSame('Blood pressure monitor', $props['products'][0]['name']['en']);
        // A wallet order with no receipt is still waiting on the buyer.
        $this->assertTrue($props['awaiting_receipt']);
    }

    public function test_viewing_an_order_is_recorded_and_a_refresh_is_not_duplicated(): void
    {
        $order = $this->seedOrder();
        $admin = $this->adminWith('view orders');

        $this->actingAs($admin)->get(route('admin.order.show', $order->order_code))->assertOk();
        $this->actingAs($admin)->get(route('admin.order.show', $order->order_code))->assertOk();

        $views = OrderLog::where('order_id', $order->id)
            ->where('action', OrderLog::ACTION_VIEWED)
            ->get();

        $this->assertCount(1, $views, 'A refresh inside the throttle window must not add a second row.');
        $this->assertSame($admin->id, $views->first()->admin_id);
        $this->assertNotNull($views->first()->ip_address);
    }

    public function test_a_view_older_than_the_throttle_window_is_recorded_again(): void
    {
        $order = $this->seedOrder();
        $admin = $this->adminWith('view orders');

        $stale = OrderLog::create([
            'order_id' => $order->id,
            'admin_id' => $admin->id,
            'action' => OrderLog::ACTION_VIEWED,
        ]);

        /* Straight through the query builder: `created_at` is not fillable, so
           `create()` would stamp it with now() and the row would never be
           outside the window it is meant to test. */
        OrderLog::query()->whereKey($stale->id)->update([
            'created_at' => now()->subMinutes(OrderLog::VISIT_THROTTLE_MINUTES + 1),
        ]);

        $this->actingAs($admin)->get(route('admin.order.show', $order->order_code))->assertOk();

        $this->assertSame(2, OrderLog::where('order_id', $order->id)
            ->where('action', OrderLog::ACTION_VIEWED)->count());
    }

    public function test_edit_is_closed_to_a_view_only_admin(): void
    {
        $order = $this->seedOrder();

        $this->actingAs($this->adminWith('view orders'))
            ->get(route('admin.order.edit', $order->order_code))
            ->assertForbidden();
    }

    public function test_edit_renders_raw_values_and_logs_the_visit(): void
    {
        $order = $this->seedOrder();

        $response = $this->actingAs($this->adminWith('manage orders'))
            ->get(route('admin.order.edit', $order->order_code));

        $response->assertOk();
        $props = $response->viewData('page')['props'];

        // A select binds to the value; a {value,label} pair would post back an object.
        $this->assertSame('pending', $props['order']['payment_status']);
        $this->assertSame('transfer-wallet', $props['order']['payment_type']);
        $this->assertCount(4, $props['paymentStatuses']);
        $this->assertCount(2, $props['paymentTypes']);

        $this->assertSame(1, OrderLog::where('order_id', $order->id)
            ->where('action', OrderLog::ACTION_EDIT_VIEWED)->count());
    }

    public function test_update_saves_the_order_and_files_what_changed(): void
    {
        $order = $this->seedOrder();
        $line = $order->products()->first();
        $admin = $this->adminWith('manage orders');

        $response = $this->actingAs($admin)->put(route('admin.order.update', $order->order_code), [
            'customer_full_name' => 'أحمد محمود',
            'customer_phone' => '01009998877',
            'customer_address' => '12 Nile St, Cairo',
            'notes' => 'Call before delivery',
            'membership_number' => 'M-100001',
            'payment_status' => PaymentStatusEnum::ACCEPTED->value,
            'delivery_status' => DeliveryStatusEnum::ON_DELIVERY->value,
            'payment_type' => PaymentTypeEnum::TRANSFER_WALLET->value,
            'total_paid' => 300,
            'total_amount' => 450,
            'total_amount_before_discount' => 375,
            'source' => Order::SOURCE_STOREFRONT,
            'products' => [
                [
                    'id' => $line->id,
                    'product_id' => null,
                    'name' => ['ar' => 'جهاز قياس الضغط', 'en' => 'Blood pressure monitor'],
                    'slug' => 'bp-monitor',
                    'quantity' => 3,
                    'old_price' => 200,
                    'new_price' => 150,
                    'cost_price' => 100,
                    'profit_price' => 50,
                ],
                [
                    'id' => null,
                    'name' => ['ar' => 'ترمومتر', 'en' => 'Thermometer'],
                    'quantity' => 1,
                    'new_price' => 45.5,
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.order.show', $order->order_code));

        $order->refresh();
        $this->assertSame('accepted', $order->payment_status->value);
        $this->assertSame('on-delivery', $order->delivery_status->value);
        $this->assertSame('01009998877', $order->customer_phone);
        $this->assertSame('450.00', $order->total_amount);

        $lines = $order->products()->get();
        $this->assertCount(2, $lines);
        // Recomputed server-side, never taken from the form.
        $this->assertSame('450.00', $lines->firstWhere('id', $line->id)->line_total);
        $this->assertSame('45.50', $lines->last()->line_total);

        $actions = OrderLog::where('order_id', $order->id)->pluck('action')->all();
        $this->assertContains(OrderLog::ACTION_UPDATED, $actions);
        $this->assertContains(OrderLog::ACTION_PAYMENT_STATUS_CHANGED, $actions);
        $this->assertContains(OrderLog::ACTION_DELIVERY_STATUS_CHANGED, $actions);
        $this->assertContains(OrderLog::ACTION_PRODUCTS_CHANGED, $actions);

        $updated = OrderLog::where('order_id', $order->id)
            ->where('action', OrderLog::ACTION_UPDATED)->first();
        $this->assertSame($admin->id, $updated->admin_id);
        $this->assertContains('customer_phone', $updated->changed_fields);
        $this->assertSame('01001234567', $updated->old_values['customer_phone']);
        // Untouched fields stay out of the log — a diff that repeats every
        // column is one nobody reads.
        $this->assertArrayNotHasKey('membership_number', $updated->new_values);
    }

    public function test_a_line_left_out_of_the_payload_is_removed(): void
    {
        $order = $this->seedOrder();

        $this->actingAs($this->adminWith('manage orders'))
            ->put(route('admin.order.update', $order->order_code), $this->basePayload($order) + [
                'products' => [],
            ])
            ->assertRedirect();

        $this->assertSame(0, OrderProduct::where('order_id', $order->id)->count());
    }

    public function test_cancelling_needs_a_reason_and_clearing_the_cancellation_drops_it(): void
    {
        $order = $this->seedOrder();
        $admin = $this->adminWith('manage orders');

        $this->actingAs($admin)
            ->put(route('admin.order.update', $order->order_code), array_merge($this->basePayload($order), [
                'payment_status' => PaymentStatusEnum::CANCELED->value,
            ]))
            ->assertSessionHasErrors('cancel_reason');

        $this->actingAs($admin)
            ->put(route('admin.order.update', $order->order_code), array_merge($this->basePayload($order), [
                'payment_status' => PaymentStatusEnum::CANCELED->value,
                'cancel_reason' => 'Customer changed their mind',
            ]))
            ->assertRedirect();

        $order->refresh();
        $this->assertSame('canceled', $order->payment_status->value);
        $this->assertSame('Customer changed their mind', $order->cancel_reason);
        $this->assertSame(1, OrderLog::where('order_id', $order->id)
            ->where('action', OrderLog::ACTION_CANCELED)->count());

        // Reinstated: the reason goes with the cancellation it explained.
        $this->actingAs($admin)
            ->put(route('admin.order.update', $order->order_code), array_merge($this->basePayload($order), [
                'payment_status' => PaymentStatusEnum::ACCEPTED->value,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertNull($order->refresh()->cancel_reason);
    }

    public function test_the_order_code_and_provenance_cannot_be_rewritten(): void
    {
        $order = $this->seedOrder();

        $this->actingAs($this->adminWith('manage orders'))
            ->put(route('admin.order.update', $order->order_code), array_merge($this->basePayload($order), [
                'order_code' => 'DL-HIJACKED',
                'ip_address' => '10.0.0.1',
                'user_agent' => 'rewritten',
            ]))
            ->assertRedirect();

        $order->refresh();
        $this->assertSame('DL-TESTCODE', $order->order_code);
        $this->assertSame('41.33.10.7', $order->ip_address);
        $this->assertSame('Mozilla/5.0 (iPhone)', $order->user_agent);
    }

    /**
     * The order as it stands, as a valid payload — so each test only has to
     * state the one thing it is changing.
     *
     * @return array<string, mixed>
     */
    /**
     * An admin can correct what delivery cost and what it was charged at. The
     * profit is never posted — it is `price - cost`, worked out as the order is
     * written, so the three columns cannot be saved contradicting each other.
     */
    public function test_delivery_figures_are_editable_and_the_profit_is_derived(): void
    {
        $order = $this->seedOrder();

        $this->actingAs($this->adminWith('manage orders'))
            ->put(route('admin.order.update', $order->order_code), array_merge($this->basePayload($order), [
                'delivery_cost' => 40,
                'delivery_price' => 65,
                /* Ignored on purpose: a posted profit must not win. */
                'delivery_profit' => 9999,
            ]))
            ->assertRedirect(route('admin.order.show', $order->order_code));

        $order->refresh();

        $this->assertSame('40.00', $order->delivery_cost);
        $this->assertSame('65.00', $order->delivery_price);
        $this->assertSame('25.00', $order->delivery_profit);
    }

    /** An edit that never touched delivery leaves the arrangement alone. */
    public function test_an_edit_without_delivery_figures_keeps_the_ones_on_the_order(): void
    {
        $order = $this->seedOrder();
        $order->update(['delivery_cost' => 30, 'delivery_price' => 45, 'delivery_profit' => 15]);

        $this->actingAs($this->adminWith('manage orders'))
            ->put(route('admin.order.update', $order->order_code), $this->basePayload($order))
            ->assertRedirect(route('admin.order.show', $order->order_code));

        $order->refresh();

        $this->assertSame('30.00', $order->delivery_cost);
        $this->assertSame('45.00', $order->delivery_price);
        $this->assertSame('15.00', $order->delivery_profit);
    }

    /** All three figures reach the show screen — they are the shop's own. */
    public function test_the_show_screen_carries_all_three_delivery_figures(): void
    {
        $order = $this->seedOrder();
        $order->update(['delivery_cost' => 30, 'delivery_price' => 45, 'delivery_profit' => 15]);

        $response = $this->actingAs($this->adminWith('view orders'))
            ->get(route('admin.order.show', $order->order_code));

        $props = $response->viewData('page')['props']['order'];

        $this->assertSame(30.0, $props['delivery_cost']);
        $this->assertSame(45.0, $props['delivery_price']);
        $this->assertSame(15.0, $props['delivery_profit']);
    }

    /**
     * The admin form adds receipts and never takes one away, however many the
     * order already holds. An admin who does not believe a receipt moves the
     * payment status — which `order_logs` attributes and dates — rather than
     * deleting evidence, which records nothing.
     */
    public function test_the_admin_adds_receipts_and_cannot_remove_one(): void
    {
        Storage::fake('public');

        $order = $this->seedOrder();
        $admin = $this->adminWith('manage orders');

        $order->addMedia(UploadedFile::fake()->image('from-the-buyer.jpg'))
            ->toMediaCollection(Order::RECEIPT_COLLECTION);

        $existing = $order->refresh()->getMedia(Order::RECEIPT_COLLECTION)->first();

        /* Six more in one save, past the cap this collection used to carry. */
        $this->actingAs($admin)
            ->put(route('admin.order.update', $order->order_code), $this->basePayload($order) + [
                'receipts' => [
                    UploadedFile::fake()->image('a.jpg'),
                    UploadedFile::fake()->image('b.jpg'),
                    UploadedFile::fake()->image('c.jpg'),
                    UploadedFile::fake()->image('d.jpg'),
                    UploadedFile::fake()->image('e.jpg'),
                    UploadedFile::fake()->create('f.pdf', 20, 'application/pdf'),
                ],
            ])
            ->assertRedirect();

        $this->assertCount(7, $order->refresh()->getMedia(Order::RECEIPT_COLLECTION));

        /*
         * A removal list is not merely ignored by the action — the request no
         * longer has a rule for it, so it cannot reach the action at all. The
         * buyer's first receipt is still there afterwards.
         */
        $this->actingAs($admin)
            ->put(route('admin.order.update', $order->order_code), $this->basePayload($order) + [
                'remove_receipt_ids' => [$existing->id],
            ])
            ->assertRedirect();

        $order->refresh();

        $this->assertCount(7, $order->getMedia(Order::RECEIPT_COLLECTION));
        $this->assertNotNull(
            $order->getMedia(Order::RECEIPT_COLLECTION)->firstWhere('id', $existing->id),
            'A receipt already on the order must survive an edit that asks for its removal.',
        );
    }

    private function basePayload(Order $order): array
    {
        return [
            'customer_full_name' => $order->customer_full_name,
            'customer_phone' => $order->customer_phone,
            'customer_address' => $order->customer_address,
            'notes' => $order->notes,
            'membership_number' => $order->membership_number,
            'payment_status' => $order->payment_status->value,
            'delivery_status' => $order->delivery_status->value,
            'payment_type' => $order->payment_type->value,
            'total_paid' => (float) $order->total_paid,
            'total_amount' => (float) $order->total_amount,
            'total_amount_before_discount' => (float) $order->total_amount_before_discount,
            'source' => $order->source,
        ];
    }
}
