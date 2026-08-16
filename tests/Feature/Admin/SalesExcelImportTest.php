<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\Sales;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Sales Excel export/import round trip (/admin/sales/export + /admin/sales/import).
 */
class SalesExcelImportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web');
        $role->givePermissionTo(Permission::findOrCreate('manage sales', 'web'));
        $user->assignRole($role);

        return $user;
    }

    private function makeSales(string $en, string $ar = '', ?int $createdBy = null): Sales
    {
        return Sales::create([
            'name' => ['en' => $en, 'ar' => $ar],
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Build an XLSX with the same header row the export emits, plus the given rows.
     */
    private function buildUploadFile(array $rows): UploadedFile
    {
        $path = sys_get_temp_dir() . '/sales_test_' . uniqid('', true) . '.xlsx';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = ['#', 'Name', 'Name (AR)', 'Image url', 'Created by', 'Created at', 'Updated at'];
        $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
        foreach ($headers as $i => $header) {
            $sheet->setCellValue("{$letters[$i]}1", $header);
        }
        foreach ($rows as $r => $row) {
            foreach ($headers as $i => $header) {
                $sheet->setCellValue("{$letters[$i]}" . ($r + 2), $row[$i] ?? '');
            }
        }
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

        return new UploadedFile($path, 'sales.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    /**
     * PhpSpreadsheet's IOFactory::load needs a real file path, so write the
     * streamed response body out to a temp file first.
     */
    private function loadStreamedContent(TestResponse $response): Spreadsheet
    {
        $path = sys_get_temp_dir() . '/sales_loaded_' . uniqid('', true) . '.xlsx';
        file_put_contents($path, $response->streamedContent());

        return IOFactory::load($path);
    }

    public function test_export_returns_xlsx_with_all_sales_columns_including_id(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->makeSales('Pharmacy Main', 'صيدلية الرئيسية', $admin->id);
        $this->makeSales('Medical Center', '', $admin->id);

        $response = $this->get(route('admin.sales.export'));
        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $spreadsheet = $this->loadStreamedContent($response);
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertSame('#', $sheet->getCell('A5')->getValue());
        $this->assertSame('Name', $sheet->getCell('B5')->getValue());
        $this->assertSame((string) Sales::where('name->en', 'Pharmacy Main')->value('id'), $sheet->getCell('A6')->getValue());
        $this->assertSame('Pharmacy Main', $sheet->getCell('B6')->getValue());
        $this->assertSame('صيدلية الرئيسية', $sheet->getCell('C6')->getValue());
    }

    public function test_round_trip_update_strategy_updates_existing_and_creates_new(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $existing = $this->makeSales('Pharmacy Main', 'صيدلية الرئيسية', $admin->id);

        $file = $this->buildUploadFile([
            [$existing->id, 'Pharmacy Main Renamed', 'صيدلية الرئيسية الجديدة', '', '', '', ''],
            ['', 'Brand New Sales', 'مبيعات جديدة', '', '', '', ''],
        ]);

        $preview = $this->post(route('admin.sales.import.preview'), ['file' => $file])->assertOk()->json();
        $this->assertCount(2, $preview['rows']);
        $this->assertSame('exists', $preview['rows'][0]['status']);
        $this->assertSame('new', $preview['rows'][1]['status']);

        $commit = $this->post(route('admin.sales.import.commit'), [
            'strategy' => 'update',
            'rows' => $preview['rows'],
        ])->assertOk()->json();

        $this->assertSame(1, $commit['created']);
        $this->assertSame(1, $commit['updated']);

        $this->assertSame('Pharmacy Main Renamed', $existing->refresh()->getTranslation('name', 'en'));
        $this->assertDatabaseCount('sales', 2);
    }

    public function test_create_strategy_inserts_brand_new_rows_with_new_ids(): void
    {
        $this->actingAs($this->admin());

        $existing = $this->makeSales('Pharmacy Main');

        $commit = $this->post(route('admin.sales.import.commit'), [
            'strategy' => 'create',
            'rows' => [
                ['id' => $existing->id, 'name_ar' => null, 'name_en' => 'Pharmacy Main', 'image_url' => null, 'created_by' => null],
                ['id' => null, 'name_ar' => null, 'name_en' => 'Another Sales', 'image_url' => null, 'created_by' => null],
            ],
        ])->assertOk()->json();

        $this->assertSame(2, $commit['created']);
        $this->assertSame(0, $commit['updated']);
        $this->assertSame(0, $commit['skipped']);
        $this->assertDatabaseCount('sales', 3);
    }

    public function test_add_only_skips_rows_that_already_exist(): void
    {
        $this->actingAs($this->admin());

        $existing = $this->makeSales('Pharmacy Main');

        $commit = $this->post(route('admin.sales.import.commit'), [
            'strategy' => 'add_only',
            'rows' => [
                ['id' => $existing->id, 'name_ar' => null, 'name_en' => 'Pharmacy Main', 'image_url' => null, 'created_by' => null],
                ['id' => null, 'name_ar' => null, 'name_en' => 'New Sales', 'image_url' => null, 'created_by' => null],
            ],
        ])->assertOk()->json();

        $this->assertSame(1, $commit['created']);
        $this->assertSame(0, $commit['updated']);
        $this->assertSame(1, $commit['skipped']);
        $this->assertDatabaseCount('sales', 2);
    }

    public function test_delete_all_then_add_replaces_everything_and_preserves_ids(): void
    {
        $this->actingAs($this->admin());

        $this->makeSales('Old One');
        $this->makeSales('Old Two');

        $commit = $this->post(route('admin.sales.import.commit'), [
            'strategy' => 'delete_all_then_add',
            'rows' => [
                ['id' => 999, 'name_ar' => 'استعادة', 'name_en' => 'Restored One', 'image_url' => null, 'created_by' => null],
                ['id' => null, 'name_ar' => null, 'name_en' => 'Restored Two', 'image_url' => null, 'created_by' => null],
            ],
        ])->assertOk()->json();

        $this->assertSame(2, $commit['cleared']);
        $this->assertSame(2, $commit['created']);
        $this->assertDatabaseHas('sales', ['id' => 999]);
        $this->assertDatabaseMissing('sales', ['name->en' => 'Old One']);
    }

    public function test_blank_names_are_skipped(): void
    {
        $this->actingAs($this->admin());

        $commit = $this->post(route('admin.sales.import.commit'), [
            'strategy' => 'add_only',
            'rows' => [
                ['id' => null, 'name_ar' => null, 'name_en' => null, 'image_url' => null, 'created_by' => null],
            ],
        ])->assertOk()->json();

        $this->assertSame(0, $commit['created']);
        $this->assertSame(1, $commit['skipped']);
    }

    public function test_template_and_example_download_work(): void
    {
        $this->actingAs($this->admin());

        $template = $this->get(route('admin.sales.import.template'))->assertOk();
        $spreadsheet = $this->loadStreamedContent($template);
        $this->assertSame('#', $spreadsheet->getActiveSheet()->getCell('A3')->getValue());
        $this->assertNotNull($spreadsheet->getSheetByName('Instructions'));

        $example = $this->get(route('admin.sales.import.template', ['example' => 1]))->assertOk();
        $sheet = $this->loadStreamedContent($example)->getActiveSheet();
        $this->assertSame('Pharmacy Main', $sheet->getCell('B4')->getValue());
    }
}
