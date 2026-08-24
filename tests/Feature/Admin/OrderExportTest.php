<?php

namespace Tests\Feature\Admin;

use App\Enums\Order\DeliveryStatusEnum;
use App\Enums\Order\PaymentStatusEnum;
use App\Enums\Order\PaymentTypeEnum;
use App\Enums\User\UserRoleEnum;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderExportTest extends TestCase
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

    private function seedOrders(): void
    {
        $old = Order::create([
            'order_code' => 'ORD-2026-000301',
            'total_paid' => 250.00,
            'total_amount' => 300.00,
            'total_amount_before_discount' => 375.00,
            'customer_full_name' => 'Ahmed Mahmoud',
            'customer_phone' => '01001234567',
            'membership_number' => 'M-100001',
            'payment_status' => PaymentStatusEnum::ACCEPTED,
            'delivery_status' => DeliveryStatusEnum::ON_DELIVERY,
            'payment_type' => PaymentTypeEnum::TRANSFER_WALLET,
        ]);
        $old->forceFill(['created_at' => '2026-01-10 09:00:00'])->save();

        $recent = Order::create([
            'order_code' => 'ORD-2026-000302',
            'total_paid' => 90.00,
            'total_amount' => 90.00,
            'customer_full_name' => 'Mona Abdelrahman',
            'customer_phone' => '01555667788',
            'payment_status' => PaymentStatusEnum::REJECTED,
            'delivery_status' => DeliveryStatusEnum::PENDING,
            'payment_type' => PaymentTypeEnum::COD,
        ]);
        $recent->forceFill(['created_at' => '2026-06-20 09:00:00'])->save();
    }

    /**
     * @return list<list<string|null>>
     */
    private function sheetRows(string $body): array
    {
        $path = tempnam(sys_get_temp_dir(), 'order_export_').'.xlsx';
        file_put_contents($path, $body);
        $rows = IOFactory::load($path)->getSheet(0)->toArray(null, true, false, false);
        @unlink($path);

        return $rows;
    }

    public function test_export_requires_a_manage_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.order.export'))
            ->assertForbidden();

        $this->actingAs($this->adminWith('view orders'))
            ->get(route('admin.order.export'))
            ->assertForbidden();
    }

    public function test_export_streams_an_xlsx_of_the_filtered_orders(): void
    {
        $this->seedOrders();

        $response = $this->actingAs($this->adminWith('manage orders'))
            ->get(route('admin.order.export'));

        $response->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type')
        );

        // The body is a zipped XLSX — read the cells back rather than the bytes.
        $codes = collect($this->sheetRows($response->streamedContent()))->pluck(1)->filter()->all();
        $this->assertContains('ORD-2026-000301', $codes);
        $this->assertContains('ORD-2026-000302', $codes);
    }

    public function test_date_range_narrows_the_export(): void
    {
        $this->seedOrders();

        $response = $this->actingAs($this->adminWith('manage orders'))
            ->get(route('admin.order.export', [
                'created_from' => '2026-06-01',
                'created_to' => '2026-06-30',
            ]));

        $rows = $this->sheetRows($response->streamedContent());
        $codes = collect($rows)->pluck(1)->filter()->all();

        $this->assertContains('ORD-2026-000302', $codes);
        $this->assertNotContains('ORD-2026-000301', $codes);
        // The range is printed in the sheet's filter block.
        $this->assertContains('2026-06-01', collect($rows)->pluck(1)->all());
    }

    public function test_a_tampered_date_drops_the_filter_instead_of_erroring(): void
    {
        $this->seedOrders();

        $response = $this->actingAs($this->adminWith('manage orders'))
            ->get(route('admin.order.export', ['created_from' => 'yesterday']));

        $response->assertOk();
        $codes = collect($this->sheetRows($response->streamedContent()))->pluck(1)->filter()->all();
        $this->assertContains('ORD-2026-000301', $codes);
        $this->assertContains('ORD-2026-000302', $codes);
    }

    public function test_column_selection_limits_the_sheet(): void
    {
        $this->seedOrders();

        $response = $this->actingAs($this->adminWith('manage orders'))
            ->get(route('admin.order.export', ['columns' => 'index,order_code,total_amount']));

        $rows = collect($this->sheetRows($response->streamedContent()));
        $header = $rows->first(fn ($row) => ($row[0] ?? null) === '#');

        $this->assertNotNull($header);
        $this->assertSame(3, count(array_filter($header, fn ($cell) => $cell !== null && $cell !== '')));
        $this->assertSame('Order code', $header[1]);
    }

    public function test_split_export_returns_a_zip_of_parts(): void
    {
        $this->seedOrders();

        $response = $this->actingAs($this->adminWith('manage orders'))
            ->get(route('admin.order.export', ['chunk_size' => 1]));

        $response->assertOk();
        $this->assertSame('application/zip', $response->headers->get('Content-Type'));

        $path = tempnam(sys_get_temp_dir(), 'order_export_').'.zip';
        file_put_contents($path, $response->streamedContent());
        $zip = new \ZipArchive();
        $zip->open($path);
        $this->assertSame(2, $zip->numFiles);
        $zip->close();
        @unlink($path);
    }

    public function test_list_page_carries_the_date_filters(): void
    {
        $this->seedOrders();

        $response = $this->actingAs($this->adminWith('manage orders'))
            ->get(route('admin.order.list', ['created_from' => '2026-06-01']));

        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $this->assertSame('2026-06-01', $props['filters']['created_from']);
        $this->assertSame(['ORD-2026-000302'], collect($props['orders']['data'])->pluck('order_code')->all());
    }
}
