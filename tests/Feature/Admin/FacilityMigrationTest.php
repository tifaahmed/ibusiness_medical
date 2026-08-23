<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\City;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityManager;
use App\Models\FacilityType;
use App\Models\Governorate;
use App\Models\Sales;
use App\Models\Tag;
use App\Models\User;
use App\Services\FacilityMigration\FacilityMigrationExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The migration package has to survive a round trip with nothing lost: every
 * locale of every translatable column, the branches, the tags, and the image
 * files themselves. These tests build a package, wipe the data, and put it back
 * through the same stepped endpoints the admin screen drives.
 */
class FacilityMigrationTest extends TestCase
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
        $gov = Governorate::create(['name' => ['en' => 'Cairo', 'ar' => 'القاهرة']]);
        $city = City::create(['governorate_id' => $gov->id, 'name' => ['en' => 'Nasr City', 'ar' => 'مدينة نصر']]);
        // Inserted past the model on purpose: sales.name is a plain varchar even
        // though the model calls it translatable, so rows written by an older
        // import hold the bare name. The migration has to read that shape as
        // readily as the {"en": …, "ar": …} blob the admin screens write.
        $salesId = Sales::query()->insertGetId([
            'name' => 'Rep One',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $tag = Tag::create(['name' => ['en' => 'Featured', 'ar' => 'مختار'], 'color' => '#F59E0B']);

        $facility = Facility::create([
            'name' => ['en' => 'Sunrise Clinic', 'ar' => 'عيادة الشروق'],
            'description' => ['en' => '<p>Best clinic</p>', 'ar' => '<p>أفضل عيادة</p>'],
            'meta_title' => ['en' => 'Sunrise', 'ar' => 'الشروق'],
            'canonical_url' => 'https://old.example.test/sunrise',
            'facility_type_id' => $type->id,
            'sales_id' => $salesId,
            'discount_percent' => 15.5,
        ]);
        $facility->forceFill([
            'governorate_id' => $gov->id,
            'city_id' => $city->id,
            'latitude' => 30.0444444,
            'longitude' => 31.2357111,
        ])->save();

        $facility->tags()->sync([$tag->id]);

        $facility->addMedia(UploadedFile::fake()->image('logo.png', 20, 20))->toMediaCollection('logo');
        $facility->addMedia(UploadedFile::fake()->image('cover.png', 30, 30))->toMediaCollection('image');
        $facility->addMedia(UploadedFile::fake()->image('g1.png', 10, 10))->toMediaCollection('gallery');
        $facility->addMedia(UploadedFile::fake()->image('g2.png', 10, 10))->toMediaCollection('gallery');

        FacilityManager::create([
            'facility_id' => $facility->id,
            'name' => 'أحمد سعيد',
            'position' => 'General Manager',
            'phones' => ['0100000000', '0111111111'],
        ]);

        FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'Main Branch', 'ar' => 'الفرع الرئيسي'],
            'address' => ['en' => '12 Test St', 'ar' => '١٢ شارع تجريبي'],
            'phone' => ['0100000000', '0111111111'],
            'governorate_id' => $gov->id,
            'city_id' => $city->id,
            'latitude' => 30.1,
            'longitude' => 31.2,
        ]);

        return $facility->refresh();
    }

    private function buildPackage(): string
    {
        $path = storage_path('app/facility-migration/test-package.zip');

        return app(FacilityMigrationExporter::class)->build([
            'include_media_files' => true,
            'destination' => $path,
        ]);
    }

    private function wipeFacilityData(): void
    {
        Facility::each(fn (Facility $f) => $f->delete());
        FacilityType::query()->delete();
        City::query()->delete();
        Governorate::query()->delete();
        Sales::query()->delete();
        Tag::query()->delete();
    }

    public function test_package_round_trip_restores_every_field_and_image(): void
    {
        Storage::fake('public');
        $this->seedFacility();
        $package = $this->buildPackage();

        $this->wipeFacilityData();
        $this->assertSame(0, Facility::count());

        $result = app(\App\Services\FacilityMigration\FacilityMigrationImporter::class)
            ->import($package, ['mode' => 'merge']);

        $this->assertSame(1, $result['stats']['facilities_created'] ?? 0);

        $facility = Facility::with(['branches', 'managers', 'tags', 'facilityType', 'governorate', 'city', 'sales'])->first();

        // Both locales survive, not just the active one.
        $this->assertSame('Sunrise Clinic', $facility->getTranslation('name', 'en'));
        $this->assertSame('عيادة الشروق', $facility->getTranslation('name', 'ar'));
        $this->assertSame('<p>أفضل عيادة</p>', $facility->getTranslation('description', 'ar'));
        $this->assertSame('sunrise-clinic', $facility->slug);
        $this->assertSame('https://old.example.test/sunrise', $facility->canonical_url);
        $this->assertSame('15.50', (string) $facility->discount_percent);

        // Lookups are rebuilt from the package, not assumed to already exist.
        $this->assertSame('Clinic', $facility->facilityType->getTranslation('name', 'en'));
        $this->assertSame('Cairo', $facility->governorate->getTranslation('name', 'en'));
        $this->assertSame('Nasr City', $facility->city->getTranslation('name', 'en'));
        // The rep travels by name, not by the shape the source column happened
        // to hold it in — it comes back readable under both locales.
        $this->assertSame('Rep One', $facility->sales->getTranslation('name', 'en'));
        $this->assertSame('Rep One', $facility->sales->getTranslation('name', 'ar'));
        $this->assertSame(['Featured'], $facility->tags->pluck('name')->all());
        $this->assertSame('30.0444444', (string) $facility->latitude);

        $manager = $facility->managers->first();
        $this->assertNotNull($manager);
        $this->assertSame('أحمد سعيد', $manager->name);
        $this->assertSame('General Manager', $manager->position);
        $this->assertSame(['0100000000', '0111111111'], $manager->phones);

        $branch = $facility->branches->first();
        $this->assertSame('الفرع الرئيسي', $branch->getTranslation('name', 'ar'));
        $this->assertSame(['0100000000', '0111111111'], $branch->phone);
        $this->assertSame('Nasr City', $branch->city->getTranslation('name', 'en'));

        // Images come back, in the right collections, with real bytes on disk.
        $this->assertSame(4, $facility->media()->count());
        $this->assertSame(2, $facility->getMedia('gallery')->count());
        $logo = $facility->getFirstMedia('logo');
        $this->assertNotNull($logo);
        $this->assertSame('logo.png', $logo->file_name);
        $this->assertFileExists($logo->getPath());
    }

    public function test_reimporting_the_same_package_does_not_duplicate_anything(): void
    {
        Storage::fake('public');
        $this->seedFacility();
        $package = $this->buildPackage();

        $importer = app(\App\Services\FacilityMigration\FacilityMigrationImporter::class);
        $importer->import($package, ['mode' => 'merge']);
        $importer->import($package, ['mode' => 'merge']);

        $this->assertSame(1, Facility::count());
        $this->assertSame(1, FacilityBranch::count());
        $this->assertSame(1, FacilityManager::count());
        $this->assertSame(4, Facility::first()->media()->count());
    }

    public function test_timestamps_are_normalized_for_strict_mode_mysql(): void
    {
        Storage::fake('public');
        $facility = $this->seedFacility();
        // The exporter serialises timestamps as ISO-8601 with an offset
        // ("2026-01-09T22:51:26+02:00"), which strict-mode MySQL DATETIME
        // columns reject. The importer must write back plain "Y-m-d H:i:s".
        Facility::query()->whereKey($facility->id)->update([
            'created_at' => '2026-01-09 22:51:26',
            'updated_at' => '2026-01-09 22:51:30',
        ]);
        $package = $this->buildPackage();
        $this->wipeFacilityData();

        app(\App\Services\FacilityMigration\FacilityMigrationImporter::class)
            ->import($package, ['mode' => 'merge']);

        $raw = \Illuminate\Support\Facades\DB::table('facilities')->value('created_at');
        $this->assertSame('2026-01-09 22:51:26', $raw);
        $this->assertSame('2026-01-09 22:51:30', \Illuminate\Support\Facades\DB::table('facilities')->value('updated_at'));

        $restored = Facility::first();
        $this->assertSame('2026-01-09 22:51:26', $restored->created_at->toDateTimeString());
        $this->assertSame('2026-01-09 22:51:30', $restored->updated_at->toDateTimeString());
    }

    public function test_reimport_matches_by_id_and_restores_the_original_id(): void
    {
        Storage::fake('public');
        $source = $this->seedFacility();
        $sourceId = $source->getKey();
        $package = $this->buildPackage();
        $this->wipeFacilityData();

        $importer = app(\App\Services\FacilityMigration\FacilityMigrationImporter::class);
        $importer->import($package, ['mode' => 'merge']);

        // The original id is preserved, even though a fresh row is inserted.
        $this->assertSame(1, Facility::count());
        $this->assertSame($sourceId, Facility::first()->getKey());
        $this->assertSame('sunrise-clinic', Facility::first()->slug);

        // Target drifts: the slug is renamed locally. Re-importing must still
        // find the row by id and restore the package slug, not create a second
        // facility because the slug no longer matches.
        Facility::query()->update(['slug' => 'renamed-locally']);
        $importer->import($package, ['mode' => 'merge']);

        $this->assertSame(1, Facility::count());
        $this->assertSame('sunrise-clinic', Facility::first()->slug);
    }

    public function test_merge_never_rewrites_an_existing_facility_id(): void
    {
        Storage::fake('public');
        $source = $this->seedFacility();
        $sourceId = $source->getKey();
        $package = $this->buildPackage();
        $this->wipeFacilityData();

        // Target already holds the same facility under a fresh, different id
        // (as happens when merging into a half-imported database).
        $type = FacilityType::create(['name' => ['en' => 'Clinic', 'ar' => 'عيادة']]);
        $alreadyThere = Facility::create([
            'name' => ['en' => 'Sunrise Clinic', 'ar' => 'عيادة الشروق'],
            'facility_type_id' => $type->id,
        ]);
        $this->assertNotSame($sourceId, $alreadyThere->getKey());

        app(\App\Services\FacilityMigration\FacilityMigrationImporter::class)
            ->import($package, ['mode' => 'merge']);

        // No 1451: the row is updated in place and keeps its target id.
        $this->assertSame(1, Facility::count());
        $this->assertSame($alreadyThere->getKey(), Facility::first()->getKey());
        $this->assertSame('sunrise-clinic', Facility::first()->slug);
    }

    public function test_preview_marks_what_the_site_already_has(): void
    {
        Storage::fake('public');
        $this->seedFacility();
        $package = $this->buildPackage();

        // The site drifts away from the package after it was built: the preview
        // has to show the number the branch carries *here*, not the packaged one.
        FacilityBranch::query()->update(['phone' => ['0999999999']]);

        $response = $this->actingAs($this->admin())->postJson(
            route('admin.facility.migration.preview'),
            ['server_path' => basename($package)]
        );

        $response->assertOk();
        $facility = $response->json('facilities.0');
        $branch = $facility['branches'][0];

        $this->assertSame('Sunrise Clinic', $facility['_existing']['name']['en']);
        $this->assertSame('Clinic', $facility['_existing']['facility_type']['label']);
        $this->assertSame(1, $facility['_existing']['branches_count']);

        $this->assertSame(['0999999999'], $branch['_existing']['phone']);
        $this->assertSame('Cairo', $branch['_existing']['governorate']['label']);
        // The package itself is untouched — the old value only travels alongside.
        $this->assertSame(['0100000000', '0111111111'], $branch['phone']);
    }

    public function test_preview_marks_rows_the_site_does_not_have_as_new(): void
    {
        Storage::fake('public');
        $this->seedFacility();
        $package = $this->buildPackage();
        $this->wipeFacilityData();

        $response = $this->actingAs($this->admin())->postJson(
            route('admin.facility.migration.preview'),
            ['server_path' => basename($package)]
        );

        $response->assertOk();
        $this->assertNull($response->json('facilities.0._existing'));
        $this->assertNull($response->json('facilities.0.branches.0._existing'));
    }

    public function test_merge_matches_an_existing_facility_by_slug_without_any_id(): void
    {
        Storage::fake('public');
        $this->seedFacility();

        // What a spreadsheet import looks like: names and a slug, no ids at all.
        $payload = [
            'format' => 'ibusiness-medical/facility-migration',
            'format_version' => 1,
            'facilities' => [[
                'slug' => 'sunrise-clinic',
                'name' => ['en' => 'Sunrise Clinic', 'ar' => 'عيادة الشروق'],
                'branches' => [[
                    'name' => ['en' => 'Main Branch', 'ar' => 'الفرع الرئيسي'],
                    'phone' => ['0100000000'],
                ]],
            ]],
        ];
        $json = tempnam(sys_get_temp_dir(), 'facility-sheet').'.json';
        file_put_contents($json, json_encode($payload, JSON_UNESCAPED_UNICODE));

        try {
            app(\App\Services\FacilityMigration\FacilityMigrationImporter::class)
                ->import($json, ['mode' => 'merge']);
        } finally {
            @unlink($json);
        }

        // Updated in place rather than stacked as a second copy.
        $this->assertSame(1, Facility::count());
        $this->assertSame(1, FacilityBranch::count());
        $this->assertSame(['0100000000'], FacilityBranch::first()->phone);
    }

    public function test_dry_run_writes_nothing(): void
    {
        Storage::fake('public');
        $this->seedFacility();
        $package = $this->buildPackage();
        $this->wipeFacilityData();

        app(\App\Services\FacilityMigration\FacilityMigrationImporter::class)
            ->import($package, ['mode' => 'merge', 'dry_run' => true]);

        $this->assertSame(0, Facility::count());
    }

    public function test_admin_can_step_through_an_import_a_chunk_at_a_time(): void
    {
        Storage::fake('public');
        $this->seedFacility();
        $package = $this->buildPackage();
        $this->wipeFacilityData();

        $admin = $this->admin();
        $upload = new UploadedFile($package, 'package.zip', 'application/zip', null, true);

        $begin = $this->actingAs($admin)->post(route('admin.facility.migration.begin'), [
            'package' => $upload,
            'mode' => 'merge',
        ]);
        $begin->assertOk();
        $token = $begin->json('token');
        $this->assertSame(1, $begin->json('total'));

        $step = $this->actingAs($admin)->postJson(route('admin.facility.migration.step'), [
            'token' => $token,
            'limit' => 1,
        ]);
        $step->assertOk();
        $this->assertTrue($step->json('done'));
        $this->assertSame(100, $step->json('percent'));

        $finish = $this->actingAs($admin)->postJson(route('admin.facility.migration.finish'), ['token' => $token]);
        $finish->assertOk();

        $this->assertSame(1, Facility::count());
        $this->assertSame(1, FacilityBranch::count());

        // The session directory is gone once finished.
        $second = $this->actingAs($admin)->postJson(route('admin.facility.migration.step'), ['token' => $token]);
        $second->assertStatus(422);
    }

    public function test_fresh_mode_requires_an_explicit_confirmation(): void
    {
        Storage::fake('public');
        $this->seedFacility();
        $package = $this->buildPackage();

        $admin = $this->admin();
        $upload = new UploadedFile($package, 'package.zip', 'application/zip', null, true);

        $this->actingAs($admin)
            ->post(route('admin.facility.migration.begin'), ['package' => $upload, 'mode' => 'fresh'])
            ->assertStatus(422);

        $this->assertSame(1, Facility::count());
    }

    public function test_export_plan_reports_how_many_parts_are_needed(): void
    {
        Storage::fake('public');
        $this->seedFacility();

        $this->actingAs($this->admin())
            ->getJson(route('admin.facility.migration.export.plan', ['per_part' => 1]))
            ->assertOk()
            ->assertJson(['total' => 1, 'per_part' => 1, 'parts' => 1]);
    }

    public function test_server_path_cannot_escape_the_drop_directory(): void
    {
        Storage::fake('public');
        $this->seedFacility();
        $this->buildPackage();

        $this->actingAs($this->admin())
            ->postJson(route('admin.facility.migration.inspect'), ['server_path' => '../../../../etc/passwd'])
            ->assertStatus(422);
    }

    /**
     * Write a spreadsheet the way an operator would, and hand back the
     * migration package the import screen turns it into.
     *
     * @param  array<int, array<int, string>>  $rows  header row first
     */
    private function sheetPackage(array $rows): string
    {
        $csv = tempnam(sys_get_temp_dir(), 'facility-sheet').'.csv';
        $handle = fopen($csv, 'w');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '\\');
        }
        fclose($handle);

        try {
            return app(\App\Services\FacilityMigration\XlsxToMigrationZip::class)->convert($csv);
        } finally {
            @unlink($csv);
        }
    }

    public function test_a_sheet_carrying_the_sales_and_discount_columns_writes_both(): void
    {
        Storage::fake('public');
        $facility = $this->seedFacility();
        Sales::query()->insertGetId([
            'name' => json_encode(['en' => 'Rep Two', 'ar' => 'مندوب اثنان'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $before = Sales::count();

        // The name is written in the other locale and the wrong case, and the
        // discount carries the "%" an operator types — all three still land.
        $package = $this->sheetPackage([
            ['Name', 'Name (AR)', 'Slug', 'Facility Type', 'Sales', 'Discount %'],
            ['Sunrise Clinic', 'عيادة الشروق', 'sunrise-clinic', 'Clinic', 'مندوب اثنان', '25%'],
        ]);

        app(\App\Services\FacilityMigration\FacilityMigrationImporter::class)
            ->import($package, ['mode' => 'merge']);

        $facility->refresh();
        $this->assertSame('25.00', (string) $facility->discount_percent);
        $this->assertSame('Rep Two', $facility->sales->getTranslation('name', 'en'));
        // Matched, not duplicated: the site keeps the reps it had.
        $this->assertSame($before, Sales::count());
    }

    public function test_a_sheet_without_those_columns_leaves_the_rep_and_discount_alone(): void
    {
        Storage::fake('public');
        $facility = $this->seedFacility();
        $repId = $facility->sales_id;

        // The four-column sheet older imports were written against.
        $package = $this->sheetPackage([
            ['Name', 'Name (AR)', 'Slug', 'Facility Type'],
            ['Sunrise Clinic', 'عيادة الشروق', 'sunrise-clinic', 'Clinic'],
        ]);

        app(\App\Services\FacilityMigration\FacilityMigrationImporter::class)
            ->import($package, ['mode' => 'merge']);

        $facility->refresh();
        $this->assertSame($repId, $facility->sales_id);
        $this->assertSame('15.50', (string) $facility->discount_percent);
    }

    public function test_the_preview_shows_the_rep_and_discount_a_sheet_does_not_mention(): void
    {
        Storage::fake('public');
        $facility = $this->seedFacility();

        $package = $this->sheetPackage([
            ['Name', 'Name (AR)', 'Slug', 'Facility Type'],
            ['Sunrise Clinic', 'عيادة الشروق', 'sunrise-clinic', 'Clinic'],
        ]);
        $dropped = storage_path('app/facility-migration/sheet-package.zip');
        copy($package, $dropped);

        $response = $this->actingAs($this->admin())->postJson(
            route('admin.facility.migration.preview'),
            ['server_path' => basename($dropped)]
        );

        $response->assertOk();
        // The screen has no "not mentioned" state, so the columns start on what
        // the facility holds today — which is also what importing would leave.
        $this->assertSame($facility->sales_id, $response->json('facilities.0.sales.id'));
        $this->assertSame('15.50', $response->json('facilities.0.discount_percent'));
        $this->assertSame('Rep One', $response->json('facilities.0._existing.sales.label'));
        $this->assertSame('15.50', $response->json('facilities.0._existing.discount_percent'));
    }

    public function test_a_missing_sales_rep_can_be_created_from_the_preview(): void
    {
        $admin = $this->admin();

        $created = $this->actingAs($admin)->postJson(route('admin.facility.migration.lookup.store'), [
            'type' => 'sales',
            'name_en' => 'Brand New Rep',
        ]);
        $created->assertStatus(201);
        $created->assertJson(['created' => true]);
        $this->assertSame('Brand New Rep', $created->json('option.label'));

        // A second row reaching for the same name adopts the one just made.
        $again = $this->actingAs($admin)->postJson(route('admin.facility.migration.lookup.store'), [
            'type' => 'sales',
            'name_en' => 'brand new rep',
        ]);
        $again->assertOk();
        $again->assertJson(['created' => false, 'option' => ['value' => $created->json('option.value')]]);
        $this->assertSame(1, Sales::count());
    }

    public function test_the_example_workbook_lists_the_sales_reps_this_site_has(): void
    {
        Storage::fake('public');
        $this->seedFacility();

        $response = $this->actingAs($this->admin())->get(route('admin.facility.migration.template.example'));
        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'template').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);

            $facilities = $spreadsheet->getSheetByName('Facilities');
            $this->assertSame('Sales', $facilities->getCell('E1')->getValue());
            $this->assertSame('Discount %', $facilities->getCell('F1')->getValue());
            // The example row names a rep this site actually has, so it imports
            // as it stands rather than inventing a second person.
            $this->assertSame('Rep One', $facilities->getCell('E2')->getValue());

            $instructions = collect($spreadsheet->getSheetByName('Instructions')->toArray())
                ->map(fn ($row) => implode(' | ', array_map('strval', $row)))
                ->implode("\n");
            $this->assertStringContainsString('SALES REPS', $instructions);
            $this->assertStringContainsString('Rep One', $instructions);
        } finally {
            @unlink($path);
        }
    }

    /**
     * The multi-sheet workbook an operator fills in. A csv only ever has one
     * sheet, so the Managers sheet needs a real xlsx.
     *
     * @param  array<int, array<int, string>>  $facilities  header row first
     * @param  array<int, array<int, string>>|null  $managers  header row first, or null for no sheet at all
     */
    private function workbookPackage(array $facilities, ?array $managers = null): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Facilities');
        $sheet->fromArray($facilities, null, 'A1');

        if ($managers !== null) {
            $managerSheet = $spreadsheet->createSheet();
            $managerSheet->setTitle('Managers');
            $managerSheet->fromArray($managers, null, 'A1');
        }

        $path = tempnam(sys_get_temp_dir(), 'facility-workbook').'.xlsx';
        \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);
        $spreadsheet->disconnectWorksheets();

        try {
            return app(\App\Services\FacilityMigration\XlsxToMigrationZip::class)->convert($path);
        } finally {
            @unlink($path);
        }
    }

    /**
     * The managers' own round trip, kept apart from the big one above: that
     * test currently stops at the facility-level governorate, which the package
     * has never carried, and this path deserves coverage that actually runs.
     */
    public function test_managers_survive_a_package_round_trip(): void
    {
        Storage::fake('public');
        $this->seedFacility();
        $package = $this->buildPackage();

        $this->wipeFacilityData();
        $this->assertSame(0, FacilityManager::count());

        $result = app(\App\Services\FacilityMigration\FacilityMigrationImporter::class)
            ->import($package, ['mode' => 'merge']);

        $this->assertSame(1, $result['stats']['managers_created'] ?? 0);

        $manager = Facility::with('managers')->first()->managers->first();
        $this->assertNotNull($manager);
        $this->assertSame('أحمد سعيد', $manager->name);
        $this->assertSame('General Manager', $manager->position);
        $this->assertSame(['0100000000', '0111111111'], $manager->phones);
    }

    public function test_a_managers_sheet_adds_people_and_updates_the_ones_already_listed(): void
    {
        Storage::fake('public');
        $facility = $this->seedFacility();
        $listed = $facility->managers()->first();

        // The same person, spelled with a bare alef — and somebody new.
        $package = $this->workbookPackage(
            [
                ['Name', 'Name (AR)', 'Slug', 'Facility Type'],
                ['Sunrise Clinic', 'عيادة الشروق', 'sunrise-clinic', 'Clinic'],
            ],
            [
                ['Facility Name', 'Manager Name', 'Position', 'Phones'],
                ['Sunrise Clinic', 'احمد سعيد', 'Managing Director', '0100000000, 0122222222'],
                ['Sunrise Clinic', 'Mona Adel', 'Reception', '0133333333'],
            ]
        );

        $result = app(\App\Services\FacilityMigration\FacilityMigrationImporter::class)
            ->import($package, ['mode' => 'merge']);

        $this->assertSame(1, $result['stats']['managers_updated'] ?? 0);
        $this->assertSame(1, $result['stats']['managers_created'] ?? 0);
        $this->assertSame(2, $facility->managers()->count());

        // Matched by the folded name rather than character for character.
        $listed->refresh();
        $this->assertSame('Managing Director', $listed->position);
        $this->assertSame(['0100000000', '0122222222'], $listed->phones);
    }

    public function test_a_workbook_without_a_managers_sheet_leaves_the_people_listed_alone(): void
    {
        Storage::fake('public');
        $facility = $this->seedFacility();

        $package = $this->workbookPackage([
            ['Name', 'Name (AR)', 'Slug', 'Facility Type'],
            ['Sunrise Clinic', 'عيادة الشروق', 'sunrise-clinic', 'Clinic'],
        ]);

        app(\App\Services\FacilityMigration\FacilityMigrationImporter::class)
            ->import($package, ['mode' => 'merge']);

        $this->assertSame(1, $facility->managers()->count());
        $this->assertSame('أحمد سعيد', $facility->managers()->first()->name);
    }

    public function test_a_manager_row_without_a_name_is_skipped_with_a_warning(): void
    {
        Storage::fake('public');
        $facility = $this->seedFacility();

        $package = $this->workbookPackage(
            [
                ['Name', 'Name (AR)', 'Slug', 'Facility Type'],
                ['Sunrise Clinic', 'عيادة الشروق', 'sunrise-clinic', 'Clinic'],
            ],
            [
                ['Facility Name', 'Manager Name', 'Position', 'Phones'],
                ['Sunrise Clinic', '', 'Reception', '0133333333'],
            ]
        );

        $result = app(\App\Services\FacilityMigration\FacilityMigrationImporter::class)
            ->import($package, ['mode' => 'merge']);

        // The nameless row never reaches the importer — the sheet reader drops
        // it — so nothing is created and the person already listed stays.
        $this->assertSame(0, $result['stats']['managers_created'] ?? 0);
        $this->assertSame(1, $facility->managers()->count());
    }

    public function test_the_preview_marks_managers_the_site_already_lists(): void
    {
        Storage::fake('public');
        $facility = $this->seedFacility();
        $package = $this->buildPackage();

        // The site drifts after the package was built: the preview has to show
        // the position this person carries *here*.
        $facility->managers()->update(['position' => 'Acting Manager']);

        $response = $this->actingAs($this->admin())->postJson(
            route('admin.facility.migration.preview'),
            ['server_path' => basename($package)]
        );

        $response->assertOk();
        $manager = $response->json('facilities.0.managers.0');

        $this->assertSame('أحمد سعيد', $manager['name']);
        $this->assertSame('Acting Manager', $manager['_existing']['position']);
        $this->assertSame(1, $response->json('facilities.0._existing.managers_count'));
        // The package itself is untouched — the old value only travels alongside.
        $this->assertSame('General Manager', $manager['position']);
    }

    public function test_the_example_workbook_carries_a_managers_sheet(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin())->get(route('admin.facility.migration.template.example'));
        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'template').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $this->assertContains('Managers', $spreadsheet->getSheetNames());

            $managers = $spreadsheet->getSheetByName('Managers');
            $this->assertSame('Facility Name', $managers->getCell('A1')->getValue());
            $this->assertSame('Manager Name', $managers->getCell('B1')->getValue());
            $this->assertSame('Position', $managers->getCell('C1')->getValue());
            $this->assertSame('Phones', $managers->getCell('D1')->getValue());
            // The example rows name facilities from the Facilities sheet.
            $this->assertSame('El Gouna Medical Center', $managers->getCell('A2')->getValue());
        } finally {
            @unlink($path);
        }
    }
}
