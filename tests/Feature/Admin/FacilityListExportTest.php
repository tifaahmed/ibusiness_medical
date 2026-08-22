<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityManager;
use App\Models\FacilityType;
use App\Models\Sales;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The xlsx report the facility list screen hands out — the readable one, not
 * the migration package. It has to carry everything the list shows about a
 * facility: who sells it, what it discounts, and who to call there.
 */
class FacilityListExportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web');
        $role->givePermissionTo(Permission::findOrCreate('manage facilities', 'web'));
        $user->assignRole($role);

        return $user;
    }

    private function seedFacility(): Facility
    {
        $type = FacilityType::create(['name' => ['en' => 'Clinic', 'ar' => 'عيادة']]);
        $sales = Sales::create(['name' => ['en' => 'Rep One', 'ar' => 'مندوب واحد']]);

        $facility = Facility::create([
            'name' => ['en' => 'Sunrise Clinic', 'ar' => 'عيادة الشروق'],
            'facility_type_id' => $type->id,
            'sales_id' => $sales->id,
            'discount_percent' => 15.5,
        ]);

        FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'Main Branch', 'ar' => 'الفرع الرئيسي'],
            'phone' => ['0100000000'],
        ]);

        FacilityManager::create([
            'facility_id' => $facility->id,
            'name' => 'أحمد سعيد',
            'position' => 'General Manager',
            'phones' => ['0100000000', '0111111111'],
        ]);

        return $facility;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function sheetRows(string $xlsx, string $sheet): array
    {
        $spreadsheet = IOFactory::load($xlsx);
        $rows = $spreadsheet->getSheetByName($sheet)?->toArray() ?? [];
        $spreadsheet->disconnectWorksheets();

        return $rows;
    }

    private function download(array $params = []): string
    {
        $response = $this->actingAs($this->admin())->get(
            route('admin.facility.export', $params + ['include_branches' => 1, 'include_managers' => 1])
        );
        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'facility-export').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        return $path;
    }

    public function test_the_report_carries_the_sales_rep_the_discount_and_the_manager_count(): void
    {
        $this->seedFacility();
        $path = $this->download();

        try {
            $rows = $this->sheetRows($path, 'Facilities');

            $header = collect($rows)->first(fn ($row) => ($row[0] ?? null) === '#');
            $this->assertNotNull($header, 'The facilities table has no header row.');
            $this->assertSame(
                ['#', 'Name', 'Name (AR)', 'Slug', 'Facility type', 'Sales rep', 'Discount %', 'Branches', 'Managers', 'Created at', 'Updated at', 'Creator'],
                array_slice($header, 0, 12)
            );

            $data = collect($rows)->first(fn ($row) => ($row[1] ?? null) === 'Sunrise Clinic');
            $this->assertNotNull($data, 'The facility row is missing.');
            // The rep reads as a name, never as the {"en": …} blob the column holds.
            $this->assertSame('Rep One', $data[5]);
            // Cells come back from the reader as text, so the numbers are
            // compared by value rather than by type.
            $this->assertEquals(15.5, $data[6]);
            $this->assertEquals(1, $data[7]);
            $this->assertEquals(1, $data[8]);
        } finally {
            @unlink($path);
        }
    }

    public function test_the_report_has_a_managers_sheet(): void
    {
        $this->seedFacility();
        $path = $this->download();

        try {
            $rows = $this->sheetRows($path, 'Managers');

            $header = collect($rows)->first(fn ($row) => ($row[0] ?? null) === '#');
            $this->assertSame(
                ['#', 'Facility name', 'Facility slug', 'Manager name', 'Position', 'Phones'],
                array_slice($header, 0, 6)
            );

            $data = collect($rows)->first(fn ($row) => ($row[3] ?? null) === 'أحمد سعيد');
            $this->assertNotNull($data, 'The manager row is missing.');
            $this->assertSame('Sunrise Clinic', $data[1]);
            $this->assertSame('General Manager', $data[4]);
            $this->assertSame('0100000000, 0111111111', $data[5]);
        } finally {
            @unlink($path);
        }
    }

    public function test_a_facility_without_a_rep_or_a_discount_leaves_those_cells_empty(): void
    {
        $type = FacilityType::create(['name' => ['en' => 'Clinic', 'ar' => 'عيادة']]);
        Facility::create(['name' => ['en' => 'Plain Clinic', 'ar' => 'عيادة'], 'facility_type_id' => $type->id]);

        $path = $this->download();

        try {
            $data = collect($this->sheetRows($path, 'Facilities'))
                ->first(fn ($row) => ($row[1] ?? null) === 'Plain Clinic');

            $this->assertNotNull($data);
            // Not "0" and not the string "null" — a facility with no discount
            // reads as blank, which is not the same as discounting nothing.
            $this->assertNull($data[5]);
            $this->assertNull($data[6]);
            $this->assertEquals(0, $data[8]);
        } finally {
            @unlink($path);
        }
    }

    public function test_the_applied_sales_filter_is_named_in_the_report_header(): void
    {
        $facility = $this->seedFacility();
        $path = $this->download(['sales_id' => $facility->sales_id]);

        try {
            $rows = $this->sheetRows($path, 'Facilities');
            $filter = collect($rows)->first(fn ($row) => ($row[0] ?? null) === 'Sales rep');

            $this->assertNotNull($filter, 'The filter block does not mention the sales rep.');
            $this->assertSame('Rep One', $filter[1]);
        } finally {
            @unlink($path);
        }
    }
}
