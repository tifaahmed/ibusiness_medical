<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Company Excel export/import round trip (/admin/company/export + /admin/company/import).
 */
class CompanyExcelImportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web');
        $role->givePermissionTo(Permission::findOrCreate('manage companies', 'web'));
        $user->assignRole($role);

        return $user;
    }

    private function makeCompany(string $en, string $ar = '', ?string $slug = null): Company
    {
        return Company::create([
            'name' => ['en' => $en, 'ar' => $ar],
            'slug' => $slug,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Build an XLSX with the same header row the export emits, plus the given rows.
     */
    private function buildUploadFile(array $rows): UploadedFile
    {
        $path = sys_get_temp_dir() . '/companies_test_' . uniqid('', true) . '.xlsx';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = ['ID', 'Name (English)', 'Name (Arabic)', 'Slug', 'Created By (Email)', 'Created At', 'Updated At'];
        foreach ($headers as $i => $header) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . '1', $header);
        }
        foreach ($rows as $r => $row) {
            foreach ($headers as $i => $header) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . ($r + 2), $row[$i] ?? '');
            }
        }
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

        return new UploadedFile($path, 'companies.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function spreadsheetFromResponse($response): Spreadsheet
    {
        $path = sys_get_temp_dir() . '/companies_out_' . uniqid('', true) . '.xlsx';
        file_put_contents($path, $response->streamedContent());
        $spreadsheet = IOFactory::load($path);
        unlink($path);

        return $spreadsheet;
    }

    public function test_export_returns_xlsx_with_all_company_columns(): void
    {
        $this->actingAs($this->admin());

        $this->makeCompany('Sunrise Clinic', 'عيادة الشروق', 'sunrise-clinic');
        $this->makeCompany('Green Valley Hospital', 'مستشفى الوادي الأخضر');

        $response = $this->get(route('admin.company.export'));
        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $spreadsheet = $this->spreadsheetFromResponse($response);
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertSame('ID', $sheet->getCell('A1')->getValue());
        $this->assertSame('Name (English)', $sheet->getCell('B1')->getValue());
        $this->assertSame('Sunrise Clinic', $sheet->getCell('B2')->getValue());
        $this->assertSame('عيادة الشروق', $sheet->getCell('C2')->getValue());
        $this->assertSame('sunrise-clinic', $sheet->getCell('D2')->getValue());
    }

    public function test_round_trip_upsert_updates_existing_and_creates_new(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $existing = $this->makeCompany('Sunrise Clinic', 'عيادة الشروق', 'sunrise-clinic');

        $file = $this->buildUploadFile([
            [$existing->id, 'Sunrise Clinic Renamed', 'عيادة الشروق الجديدة', '', '', '', ''],
            ['', 'Brand New Clinic', 'عيادة جديدة', '', '', '', ''],
        ]);

        $preview = $this->post(route('admin.company.import.preview'), ['file' => $file])->assertOk()->json();
        $this->assertCount(2, $preview['rows']);
        $this->assertSame('update', $preview['rows'][0]['status']);
        $this->assertSame('new', $preview['rows'][1]['status']);

        $rows = array_map(fn ($r) => $r['parsed'], $preview['rows']);
        $commit = $this->post(route('admin.company.import.run'), [
            'mode' => 'upsert',
            'rows' => [
                ['id' => (int) $rows[0]['id'], 'name_en' => $rows[0]['name_en'], 'name_ar' => $rows[0]['name_ar']],
                ['id' => null, 'name_en' => $rows[1]['name_en'], 'name_ar' => $rows[1]['name_ar']],
            ],
        ])->assertOk()->json();

        $this->assertSame(1, $commit['created']);
        $this->assertSame(1, $commit['updated']);

        $this->assertSame('Sunrise Clinic Renamed', $existing->refresh()->getTranslation('name', 'en'));
        $this->assertSame('Brand New Clinic', Company::where('slug', 'brand-new-clinic')->value('name->en'));
    }

    public function test_create_only_skips_existing_rows(): void
    {
        $this->actingAs($this->admin());

        $existing = $this->makeCompany('Sunrise Clinic', '', 'sunrise-clinic');

        $commit = $this->post(route('admin.company.import.run'), [
            'mode' => 'create_only',
            'rows' => [
                ['id' => $existing->id, 'name_en' => 'Sunrise Clinic', 'name_ar' => null],
                ['id' => null, 'name_en' => 'Another Clinic', 'name_ar' => null],
            ],
        ])->assertOk()->json();

        $this->assertSame(1, $commit['created']);
        $this->assertSame(0, $commit['updated']);
        $this->assertSame(1, $commit['skipped']);

        $this->assertDatabaseCount('companies', 2);
        $this->assertDatabaseHas('companies', ['id' => $existing->id]);
    }

    public function test_clear_mode_deletes_existing_companies_then_adds(): void
    {
        $this->actingAs($this->admin());

        $this->makeCompany('Old Company One', '', 'old-one');
        $this->makeCompany('Old Company Two', '', 'old-two');

        $commit = $this->post(route('admin.company.import.run'), [
            'mode' => 'clear',
            'rows' => [
                ['id' => null, 'name_en' => 'Replacement One', 'name_ar' => null],
                ['id' => null, 'name_en' => 'Replacement Two', 'name_ar' => null],
            ],
        ])->assertOk()->json();

        $this->assertSame(2, $commit['deleted']);
        $this->assertSame(2, $commit['created']);

        $this->assertDatabaseMissing('companies', ['slug' => 'old-one']);
        $this->assertDatabaseHas('companies', ['slug' => 'replacement-one']);
    }

    public function test_import_restores_the_id_from_the_file(): void
    {
        $this->actingAs($this->admin());

        $commit = $this->post(route('admin.company.import.run'), [
            'mode' => 'create_only',
            'rows' => [
                ['id' => 999, 'name_en' => 'Pinned ID Company', 'name_ar' => null],
            ],
        ])->assertOk()->json();

        $this->assertSame(1, $commit['created']);
        $this->assertDatabaseHas('companies', ['id' => 999, 'slug' => 'pinned-id-company']);
    }

    public function test_template_and_example_download_work(): void
    {
        $this->actingAs($this->admin());

        $template = $this->get(route('admin.company.import.template', ['type' => 'template']))->assertOk();
        $spreadsheet = $this->spreadsheetFromResponse($template);
        $this->assertSame('ID', $spreadsheet->getActiveSheet()->getCell('A1')->getValue());
        $this->assertNotNull($spreadsheet->getSheetByName('Instructions'));

        $example = $this->get(route('admin.company.import.template', ['type' => 'example']))->assertOk();
        $sheet = $this->spreadsheetFromResponse($example)->getActiveSheet();
        $this->assertSame('Acme Medical Center', $sheet->getCell('B2')->getValue());
    }
}
