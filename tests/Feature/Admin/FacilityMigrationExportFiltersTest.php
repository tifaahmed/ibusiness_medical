<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\City;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityType;
use App\Models\Governorate;
use App\Models\Sales;
use App\Models\User;
use App\Services\FacilityMigration\FacilityMigrationExporter;
use App\Services\FacilityMigration\FacilityMigrationImporter;
use App\Services\FacilityMigration\FacilityMigrationWorkbook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The export tab offers the facility list screen's filters, and the file it
 * builds says which of them were used and who used them.
 *
 * Two separate promises. The first is that narrowing the list and then
 * exporting hands back the rows that were on screen — same clauses, same
 * answers. The second is that months later, when the package turns up on
 * another site, the file itself can still say what it is a slice of; that
 * answer lives on a sheet of its own, so the import reads exactly the same
 * workbook whether the sheet is there, edited, or deleted.
 */
class FacilityMigrationExportFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['name' => 'Mona Fahmy']);
        $role = Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web');
        $role->givePermissionTo(Permission::findOrCreate('manage facilities', 'web'));
        $user->assignRole($role);

        return $user;
    }

    /**
     * Four facilities that differ in exactly the things the filters ask about.
     *
     * @return array<string, Facility>
     */
    private function seedSite(): array
    {
        $clinic = FacilityType::create(['name' => ['en' => 'Clinic', 'ar' => 'عيادة']]);
        $lab = FacilityType::create(['name' => ['en' => 'Lab', 'ar' => 'معمل']]);

        $cairo = Governorate::create(['name' => ['en' => 'Cairo', 'ar' => 'القاهرة']]);
        $giza = Governorate::create(['name' => ['en' => 'Giza', 'ar' => 'الجيزة']]);
        $nasrCity = City::create(['governorate_id' => $cairo->id, 'name' => ['en' => 'Nasr City', 'ar' => 'مدينة نصر']]);
        $maadi = City::create(['governorate_id' => $cairo->id, 'name' => ['en' => 'Maadi', 'ar' => 'المعادي']]);
        $dokki = City::create(['governorate_id' => $giza->id, 'name' => ['en' => 'Dokki', 'ar' => 'الدقي']]);

        $rep = Sales::create(['name' => ['en' => 'Rep One', 'ar' => 'مندوب واحد']]);

        $make = function (string $name, array $attributes, array $branch) use ($clinic) {
            $facility = Facility::create([
                'name' => ['en' => $name, 'ar' => $name],
                'facility_type_id' => $attributes['facility_type_id'] ?? $clinic->id,
                'sales_id' => $attributes['sales_id'] ?? null,
            ]);

            FacilityBranch::create([
                'facility_id' => $facility->id,
                'name' => ['en' => $name.' Branch', 'ar' => $name],
                'governorate_id' => $branch['governorate_id'] ?? null,
                'city_id' => $branch['city_id'] ?? null,
            ]);

            return $facility->refresh();
        };

        return [
            'nasr' => $make('Nasr Clinic', ['sales_id' => $rep->id], [
                'governorate_id' => $cairo->id, 'city_id' => $nasrCity->id,
            ]),
            'maadi' => $make('Maadi Clinic', [], [
                'governorate_id' => $cairo->id, 'city_id' => $maadi->id,
            ]),
            'dokki' => $make('Dokki Lab', ['facility_type_id' => $lab->id, 'sales_id' => $rep->id], [
                'governorate_id' => $giza->id, 'city_id' => $dokki->id,
            ]),
            // The row an operator exports in order to go and fix it: a branch
            // nobody can put on a map, invisible to the place filters.
            'nowhere' => $make('Nowhere Clinic', [], []),
            'rep' => $rep,
            'cairo' => $cairo,
            'nasr_city' => $nasrCity,
            'lab' => $lab,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function exportedNames(array $filters): array
    {
        $exporter = app(FacilityMigrationExporter::class);
        $path = $exporter->buildSpreadsheet([
            'filters' => $filters,
            'destination' => storage_path('app/facility-migration/filters-test.xlsx'),
        ]);

        $sheet = IOFactory::load($path)->getSheetByName('Facilities');
        $names = [];
        for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
            $name = trim((string) $sheet->getCell("D{$row}")->getValue());
            if ($name !== '') {
                $names[] = $name;
            }
        }
        sort($names);

        return $names;
    }

    public function test_every_filter_the_list_screen_offers_narrows_the_export(): void
    {
        $site = $this->seedSite();

        // Place is asked of the branches, exactly as the list asks it.
        $this->assertSame(
            ['Maadi Clinic', 'Nasr Clinic'],
            $this->exportedNames(['governorate_id' => $site['cairo']->id])
        );
        $this->assertSame(
            ['Nasr Clinic'],
            $this->exportedNames(['city_id' => $site['nasr_city']->id])
        );

        $this->assertSame(
            ['Dokki Lab', 'Nasr Clinic'],
            $this->exportedNames(['sales_presence' => 'with'])
        );
        $this->assertSame(
            ['Maadi Clinic', 'Nowhere Clinic'],
            $this->exportedNames(['sales_presence' => 'without'])
        );
        $this->assertSame(
            ['Dokki Lab', 'Nasr Clinic'],
            $this->exportedNames(['sales_id' => $site['rep']->id])
        );

        // The branch with no place on the map — the one the two filters above
        // can never name, because they can only name a place it does have.
        $this->assertSame(
            ['Nowhere Clinic'],
            $this->exportedNames(['branches_missing' => 'governorate'])
        );
        $this->assertSame(
            ['Nowhere Clinic'],
            $this->exportedNames(['branches_missing' => 'both'])
        );

        $this->assertSame(
            ['Dokki Lab'],
            $this->exportedNames(['facility_type_id' => $site['lab']->id])
        );

        // Two filters at once narrow, never widen.
        $this->assertSame(
            ['Nasr Clinic'],
            $this->exportedNames(['governorate_id' => $site['cairo']->id, 'sales_presence' => 'with'])
        );
    }

    public function test_the_export_endpoint_reads_the_same_filters_as_the_list(): void
    {
        $site = $this->seedSite();

        $this->actingAs($this->admin())
            ->getJson(route('admin.facility.migration.export.plan', [
                'governorate_id' => $site['cairo']->id,
                'sales_presence' => 'without',
                'per_part' => 10,
            ]))
            ->assertOk()
            ->assertJson([
                'total' => 1,
                'filters' => ['governorate_id' => (string) $site['cairo']->id, 'sales_presence' => 'without'],
            ]);
    }

    public function test_a_filter_the_query_string_makes_up_is_ignored_rather_than_obeyed(): void
    {
        $this->seedSite();

        // sales_presence and branches_missing never reach SQL as values, so a
        // hand-edited URL must leave the export as wide as it was, not empty.
        $this->actingAs($this->admin())
            ->getJson(route('admin.facility.migration.export.plan', [
                'sales_presence' => 'sometimes',
                'branches_missing' => 'planet',
            ]))
            ->assertOk()
            ->assertJson(['total' => 4, 'filters' => [], 'filters_described' => []]);
    }

    public function test_the_workbook_reports_the_filters_and_who_exported_it(): void
    {
        $site = $this->seedSite();
        $admin = $this->admin();

        $this->actingAs($admin);

        $path = app(FacilityMigrationExporter::class)->buildSpreadsheet([
            'filters' => [
                'governorate_id' => $site['cairo']->id,
                'sales_presence' => 'without',
                'search' => 'clinic',
            ],
            'destination' => storage_path('app/facility-migration/report-test.xlsx'),
        ]);

        $sheet = IOFactory::load($path)->getSheetByName(FacilityMigrationWorkbook::EXPORT_INFO_SHEET);
        $this->assertNotNull($sheet, 'the workbook has no Export Info sheet');

        $text = $sheet->toArray();
        $text = implode("\n", array_map(fn ($row) => implode(' | ', array_map('strval', $row)), $text));

        $this->assertStringContainsString('Mona Fahmy', $text);
        $this->assertStringContainsString($admin->email, $text);
        // Ids mean nothing on the other site; the name it had here does.
        $this->assertStringContainsString('Cairo', $text);
        $this->assertStringContainsString('Only facilities with no sales rep', $text);
        $this->assertStringContainsString('clinic', $text);
        $this->assertStringContainsString('Filters applied', $text);
    }

    public function test_an_unfiltered_workbook_says_so_rather_than_saying_nothing(): void
    {
        $this->seedSite();
        $this->actingAs($this->admin());

        $path = app(FacilityMigrationExporter::class)->buildSpreadsheet([
            'destination' => storage_path('app/facility-migration/report-all.xlsx'),
        ]);

        $sheet = IOFactory::load($path)->getSheetByName(FacilityMigrationWorkbook::EXPORT_INFO_SHEET);
        $text = implode("\n", array_map(fn ($row) => implode(' | ', array_map('strval', $row)), $sheet->toArray()));

        // A blank filter list is a statement of its own: this is the whole site,
        // not a slice somebody forgot to label.
        $this->assertStringContainsString('Every facility on the source site was exported.', $text);
    }

    public function test_the_report_sheet_does_not_change_what_the_import_reads(): void
    {
        $site = $this->seedSite();
        $this->actingAs($this->admin());

        $path = app(FacilityMigrationExporter::class)->buildSpreadsheet([
            'filters' => ['governorate_id' => $site['cairo']->id],
            'destination' => storage_path('app/facility-migration/report-import.xlsx'),
        ]);

        $inspection = app(FacilityMigrationImporter::class)->inspect($path);

        // Still a site export, still exactly the filtered rows — the extra sheet
        // is read by nobody on the way back in.
        $this->assertSame(FacilityMigrationExporter::ORIGIN_SITE_EXPORT, $inspection['origin']);
        $this->assertSame(2, $inspection['counts']['facilities']);
    }
}
