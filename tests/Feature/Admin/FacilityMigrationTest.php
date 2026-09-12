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

    /**
     * The other shape the same dataset takes: one .xlsx, no images, no JSON.
     */
    private function buildWorkbook(): string
    {
        return app(FacilityMigrationExporter::class)->buildSpreadsheet([
            'destination' => storage_path('app/facility-migration/test-package.xlsx'),
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
        $this->assertEquals([
            ['number' => '0100000000', 'type' => 'phone'],
            ['number' => '0111111111', 'type' => 'phone'],
        ], $manager->phones);

        $branch = $facility->branches->first();
        $this->assertSame('الفرع الرئيسي', $branch->getTranslation('name', 'ar'));
        // Stored as typed entries. The package carries flat numbers, so the
        // importer types them from their shape — anything beginning "01" is a
        // mobile, whether or not it has all eleven of its digits.
        $this->assertSame([
            ['number' => '0100000000', 'type' => 'phone'],
            ['number' => '0111111111', 'type' => 'phone'],
        ], $branch->phone);
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

    public function test_the_streamed_preview_reports_its_progress_then_the_same_result(): void
    {
        Storage::fake('public');
        $this->seedFacility();
        $package = $this->buildPackage();

        $response = $this->actingAs($this->admin())->post(
            route('admin.facility.migration.preview.stream'),
            ['server_path' => basename($package)],
            ['Accept' => 'application/x-ndjson'],
        );

        $response->assertOk();

        $rows = array_map(
            fn (string $line) => json_decode($line, true),
            array_filter(explode("\n", $response->streamedContent()), fn ($line) => trim($line) !== '')
        );

        $phases = array_values(array_unique(array_column(
            array_filter($rows, fn ($row) => ($row['type'] ?? '') === 'progress'),
            'phase'
        )));

        // The stages the bar names, in the order the work happens.
        $this->assertContains('extracting', $phases);
        $this->assertContains('reading', $phases);

        // Exactly one result, and it carries what the plain preview carries.
        $results = array_values(array_filter($rows, fn ($row) => ($row['type'] ?? '') === 'result'));
        $this->assertCount(1, $results);
        $this->assertSame($rows[array_key_last($rows)], $results[0]);

        $result = $results[0];
        $this->assertNotEmpty($result['token']);
        $this->assertSame(1, $result['total']);
        $this->assertCount(1, $result['facilities']);
        $this->assertSame('Sunrise Clinic', $result['facilities'][0]['name']['en']);
    }

    public function test_the_streamed_preview_refuses_a_package_that_is_not_there(): void
    {
        Storage::fake('public');

        // Resolving the package happens before the stream opens, so a name that
        // points at nothing is still a plain HTTP error.
        $this->actingAs($this->admin())->post(
            route('admin.facility.migration.preview.stream'),
            ['server_path' => 'no-such-package.zip'],
            ['Accept' => 'application/x-ndjson'],
        )->assertStatus(422);
    }

    public function test_the_streamed_preview_reports_an_unreadable_package_in_the_body(): void
    {
        Storage::fake('public');

        // A real file in the drop directory that is not a package: it resolves,
        // so the failure happens once the stream is already open — and by then
        // the status line has gone out and the error has to travel in the body.
        $dir = storage_path('app/facility-migration');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $broken = $dir.'/not-a-package.zip';
        file_put_contents($broken, 'this is not a zip');

        try {
            $response = $this->actingAs($this->admin())->post(
                route('admin.facility.migration.preview.stream'),
                ['server_path' => basename($broken)],
                ['Accept' => 'application/x-ndjson'],
            );

            $response->assertOk();

            $rows = array_map(
                fn (string $line) => json_decode($line, true),
                array_filter(explode("\n", $response->streamedContent()), fn ($line) => trim($line) !== '')
            );

            $last = $rows[array_key_last($rows)];
            $this->assertSame('error', $last['type']);
            $this->assertNotEmpty($last['message']);
        } finally {
            @unlink($broken);
        }
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
        $this->assertSame([['number' => '0100000000', 'type' => 'phone']], FacilityBranch::first()->phone);
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
     * The example is meant to be imported as it stands. Every lookup cell in it
     * therefore has to name something this site already holds — a made-up type
     * or city would have the preview flag the row as new and offer to create it,
     * for a file the screen itself handed the operator.
     */
    public function test_the_example_workbook_names_lookups_this_site_actually_has(): void
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
            $this->assertSame('Facility Type', $facilities->getCell('D1')->getValue());
            // This site has exactly one type; every example row names it rather
            // than inventing "Clinic", "Dental Clinic" and "Hospital".
            foreach (['D2', 'D3', 'D4'] as $cell) {
                $this->assertSame('Clinic', $facilities->getCell($cell)->getValue());
            }

            // Governorate and city travel as a pair, and the pair is a real one.
            $branches = $spreadsheet->getSheetByName('Branches');
            $this->assertSame('Governorate', $branches->getCell('G1')->getValue());
            $this->assertSame('City', $branches->getCell('H1')->getValue());
            foreach ([2, 3, 4] as $row) {
                $this->assertSame('Cairo', $branches->getCell("G{$row}")->getValue());
                $this->assertSame('Nasr City', $branches->getCell("H{$row}")->getValue());
            }
        } finally {
            @unlink($path);
        }
    }

    /**
     * An empty site can borrow nothing, so the example falls back to the names
     * it always carried — everything in it is new there anyway.
     */
    public function test_the_example_workbook_still_has_rows_on_a_site_with_no_lookups(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin())->get(route('admin.facility.migration.template.example'));
        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'template').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        try {
            $facilities = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getSheetByName('Facilities');
            $this->assertSame('Clinic', $facilities->getCell('D2')->getValue());
            $this->assertSame('Dental Clinic', $facilities->getCell('D3')->getValue());
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
        $this->assertEquals([
            ['number' => '0100000000', 'type' => 'phone'],
            ['number' => '0111111111', 'type' => 'phone'],
        ], $manager->phones);
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
        $this->assertEquals([
            ['number' => '0100000000', 'type' => 'phone'],
            ['number' => '0122222222', 'type' => 'phone'],
        ], $listed->phones);
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

    // ------------------------------------------------------------------ xlsx
    //
    // With no images to carry there is nothing an archive holds that a workbook
    // cannot, so a data-only export is a spreadsheet — one the operator can read
    // and correct, and the Import tab reads back as the site package it is.

    public function test_a_data_only_export_is_a_workbook_and_not_an_archive(): void
    {
        Storage::fake('public');
        $this->seedFacility();

        $path = $this->buildWorkbook();

        $this->assertStringEndsWith('.xlsx', $path);
        $this->assertStringEndsWith(
            '.xlsx',
            app(FacilityMigrationExporter::class)->filename(includeMediaFiles: false)
        );
        $this->assertStringEndsWith(
            '.zip',
            app(FacilityMigrationExporter::class)->filename(includeMediaFiles: true)
        );

        // A workbook, not a renamed package: the archive holds Excel's own
        // parts and none of the package's.
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $this->assertNotFalse($zip->locateName('xl/workbook.xml'));
        $this->assertFalse($zip->locateName('data/facilities.json'));
        $zip->close();
    }

    public function test_the_workbook_carries_every_column_back_onto_its_own_rows(): void
    {
        Storage::fake('public');
        $facility = $this->seedFacility();
        $branchId = $facility->branches()->first()->id;

        $importer = app(\App\Services\FacilityMigration\FacilityMigrationImporter::class);
        $importer->import($this->buildWorkbook(), ['mode' => 'merge']);

        // Merged onto what was already here rather than duplicated.
        $this->assertSame(1, Facility::count());
        $this->assertSame(1, FacilityBranch::count());
        $this->assertSame(1, FacilityManager::count());

        $restored = Facility::with(['branches', 'managers', 'tags', 'governorate', 'city', 'sales'])->first();
        $this->assertSame($facility->id, $restored->id);
        $this->assertSame('عيادة الشروق', $restored->getTranslation('name', 'ar'));
        $this->assertSame('<p>أفضل عيادة</p>', $restored->getTranslation('description', 'ar'));
        $this->assertSame('الشروق', $restored->getTranslation('meta_title', 'ar'));
        $this->assertSame('https://old.example.test/sunrise', $restored->canonical_url);
        $this->assertSame('15.50', (string) $restored->discount_percent);
        $this->assertSame('Cairo', $restored->governorate->getTranslation('name', 'en'));
        $this->assertSame('Nasr City', $restored->city->getTranslation('name', 'en'));
        // The rep the facility already points at, matched by name off the sheet
        // — a merge onto a site that already has the row leaves that row alone
        // rather than rewriting how its name is stored.
        $this->assertSame($facility->sales_id, $restored->sales_id);
        $this->assertSame(1, Sales::count());
        $this->assertSame(['Featured'], $restored->tags->pluck('name')->all());
        $this->assertSame(1, Tag::count());

        $branch = $restored->branches->first();
        $this->assertSame($branchId, $branch->id);
        $this->assertSame('الفرع الرئيسي', $branch->getTranslation('name', 'ar'));
        $this->assertSame('١٢ شارع تجريبي', $branch->getTranslation('address', 'ar'));
        $this->assertSame('Nasr City', $branch->city->getTranslation('name', 'en'));

        $manager = $restored->managers->first();
        $this->assertSame('أحمد سعيد', $manager->name);
        $this->assertEquals([
            ['number' => '0100000000', 'type' => 'phone'],
            ['number' => '0111111111', 'type' => 'phone'],
        ], $manager->phones);
    }

    public function test_a_data_only_import_leaves_the_images_the_site_already_holds(): void
    {
        Storage::fake('public');
        $facility = $this->seedFacility();
        $logoPath = $facility->getFirstMedia('logo')->getPath();

        $importer = app(\App\Services\FacilityMigration\FacilityMigrationImporter::class);
        $result = $importer->import($this->buildWorkbook(), ['mode' => 'merge']);

        // The package names no image, so no collection is emptied and there is
        // nothing to warn about. Clearing one it could not refill would destroy
        // the picture and put nothing in its place.
        $this->assertSame(4, $facility->fresh()->media()->count());
        $this->assertFileExists($logoPath);
        $this->assertSame([], $result['warnings']);
        $this->assertSame(0, $result['stats']['media_files_missing'] ?? 0);
    }

    public function test_two_branches_sharing_a_name_come_back_to_their_own_rows(): void
    {
        Storage::fake('public');
        $facility = $this->seedFacility();

        // The case a hand-typed sheet cannot express: same name, different row.
        $twin = FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'Main Branch', 'ar' => 'الفرع الرئيسي'],
            'address' => ['en' => '99 Other St', 'ar' => '٩٩ شارع آخر'],
            'phone' => ['0122222222'],
        ]);
        $ids = FacilityBranch::orderBy('id')->pluck('id')->all();

        app(\App\Services\FacilityMigration\FacilityMigrationImporter::class)
            ->import($this->buildWorkbook(), ['mode' => 'merge']);

        // Matched by the slug each row carries, so neither is duplicated and
        // neither swallows the other.
        $this->assertSame($ids, FacilityBranch::orderBy('id')->pluck('id')->all());
        $this->assertSame('99 Other St', $twin->fresh()->getTranslation('address', 'en'));
    }

    public function test_the_workbook_says_it_came_from_a_site_export(): void
    {
        Storage::fake('public');
        $this->seedFacility();

        $inspection = app(\App\Services\FacilityMigration\FacilityMigrationImporter::class)
            ->inspect($this->buildWorkbook());

        // The import screen reads this to know it may match rows by their slug
        // rather than asking somebody to tell same-named branches apart.
        $this->assertSame(FacilityMigrationExporter::ORIGIN_SITE_EXPORT, $inspection['origin']);
        $this->assertSame(0, $inspection['sample'][0]['media']);
    }

    public function test_an_image_whose_file_is_gone_is_never_named_in_the_package(): void
    {
        Storage::fake('public');
        $facility = $this->seedFacility();

        // The row survives a file that does not — a dev copy of a live database
        // is full of them. Naming it would have the importing site clear the
        // collection and then find nothing to put back.
        $logo = $facility->getFirstMedia('logo');
        unlink($logo->getPath());

        $package = $this->buildPackage();

        // The package is the workbook plus the media/ folder now — no sidecar
        // JSON or csv to eyeball instead, so what the Images sheet names is
        // the only record of what travelled.
        $zip = new \ZipArchive;
        $zip->open($package);
        $workbookBytes = $zip->getFromName(FacilityMigrationExporter::WORKBOOK_ENTRY);
        $zip->close();

        $workbookPath = tempnam(sys_get_temp_dir(), 'facility-workbook').'.xlsx';
        file_put_contents($workbookPath, $workbookBytes);

        try {
            $images = \PhpOffice\PhpSpreadsheet\IOFactory::load($workbookPath)->getSheetByName('Images');
            $collections = collect($images->toArray())
                ->slice(1) // drop the header row
                ->pluck(5) // the "Collection" column
                ->filter()
                ->values()
                ->all();

            $this->assertNotContains('logo', $collections);
            $this->assertContains('image', $collections);
            // Only the pictures actually bundled are named at all — there is
            // no separate audit trail for the one whose file went missing.
            $this->assertCount(3, $collections);
        } finally {
            @unlink($workbookPath);
        }

        $inspection = app(\App\Services\FacilityMigration\FacilityMigrationImporter::class)->inspect($package);
        $this->assertSame(3, $inspection['counts']['media']);
        $this->assertSame(3, $inspection['counts']['media_restorable']);
    }

    public function test_a_city_named_without_a_governorate_still_lands_in_the_right_one(): void
    {
        Storage::fake('public');
        $facility = $this->seedFacility();

        // The row that used to lose its city: a branch that names a city and no
        // governorate of its own. A site that does not have that city yet has
        // nothing to attach a new one to unless the package says where it
        // belongs — which is what the workbook's Lookups sheet is for.
        $orphan = FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'Placeless Branch', 'ar' => 'فرع بلا محافظة'],
            'city_id' => City::where('name->en', 'Nasr City')->value('id'),
        ]);
        $this->assertNull($orphan->governorate_id);

        $workbook = $this->buildWorkbook();
        $this->wipeFacilityData();
        $this->assertSame(0, City::count());

        $result = app(\App\Services\FacilityMigration\FacilityMigrationImporter::class)
            ->import($workbook, ['mode' => 'merge']);

        $this->assertSame([], $result['warnings']);

        $restored = FacilityBranch::where('slug', $orphan->slug)->first();
        $this->assertNotNull($restored->city_id);
        $this->assertSame('Nasr City', $restored->city->getTranslation('name', 'en'));
        // Created under the governorate it had on the source site, not guessed.
        $this->assertSame('Cairo', $restored->city->governorate->getTranslation('name', 'en'));
    }

    // ------------------------------------------------- reviewing with images
    //
    // A package's pictures have no model, no disk and no URL until the import
    // writes them, so the review screen reads them out of the open session's
    // own extraction — and the review itself can be saved back out as a package
    // rather than dying with the session.

    public function test_an_image_in_an_open_session_can_be_looked_at(): void
    {
        Storage::fake('public');
        $this->seedFacility();
        $importer = app(\App\Services\FacilityMigration\FacilityMigrationImporter::class);

        $session = $importer->beginSession($this->buildPackage(), ['mode' => 'merge', 'dry_run' => true]);
        $facility = json_decode(
            file_get_contents(storage_path("app/facility-migration/sessions/{$session['token']}/facilities/000000.json")),
            true
        );

        $logo = collect($facility['media'])->firstWhere('collection_name', 'logo');
        $path = $importer->sessionMediaPath($session['token'], $logo['package_path']);

        $this->assertNotNull($path);
        $this->assertFileExists($path);
        $this->assertSame('logo.png', basename($path));

        // Only ever a file inside this session's own extraction.
        foreach (['../../../../.env', '/etc/passwd', 'media/../../state.json'] as $escape) {
            $this->assertNull($importer->sessionMediaPath($session['token'], $escape));
        }

        $importer->endSession($session['token']);
    }

    /**
     * The review screen could only ever refile or drop the pictures a package
     * carried, which left the commonest case with nothing to offer: a workbook
     * carries no image bytes at all, so every facility arrived picture-less and
     * its logo could not be set here. An operator can now add one, and it has to
     * behave from that moment exactly like an image the package had brought.
     */
    public function test_an_image_added_during_the_review_is_imported(): void
    {
        Storage::fake('public');
        $this->seedFacility();
        // The workbook, deliberately: the session it opens has no media
        // directory whatsoever, so the upload is what creates one.
        $workbook = $this->buildWorkbook();

        $this->wipeFacilityData();

        $importer = app(\App\Services\FacilityMigration\FacilityMigrationImporter::class);
        $session = $importer->beginSession($workbook, ['mode' => 'merge', 'dry_run' => false]);
        $token = $session['token'];

        $file = storage_path("app/facility-migration/sessions/{$token}/facilities/000000.json");
        $facility = json_decode(file_get_contents($file), true);
        $this->assertSame([], $facility['media'] ?? [], 'a workbook carries no images');

        // Kept in a variable: the fake's temp file is deleted the moment the
        // UploadedFile is collected, and inlining it would take the file away
        // before storeSessionMedia ever sees it.
        $picked = UploadedFile::fake()->image('picked-logo.png', 24, 24);
        $row = $importer->storeSessionMedia($token, $picked->getRealPath(), 'شعار المنشأة.png', 'logo');

        // Indistinguishable from a bundled row from here on: the same two paths,
        // and the thumbnail endpoint finds it.
        $this->assertNotNull($importer->sessionMediaPath($token, $row['package_path']));
        $this->assertSame('logo', $row['collection_name']);
        $this->assertSame('image/png', $row['mime_type']);

        $facility['media'][] = $row;
        $importer->writeFacilityFile($token, 0, $facility);

        do {
            $progress = $importer->processChunk($token, 5);
        } while (! $progress['done']);
        $importer->endSession($token);

        $restored = Facility::first();
        $this->assertNotNull($restored);
        $logo = $restored->getFirstMedia('logo');
        $this->assertNotNull($logo, 'the picture chosen during the review is on the facility');
        $this->assertFileExists($logo->getPath());
    }

    /**
     * And the screen's other half of the same job: seeing what is already here
     * before a package paints over it.
     */
    public function test_the_preview_shows_the_images_the_site_already_holds(): void
    {
        Storage::fake('public');
        $this->seedFacility();
        $package = $this->buildPackage();

        $response = $this->actingAs($this->admin())->post(
            route('admin.facility.migration.preview'),
            ['package' => new UploadedFile($package, 'package.zip', 'application/zip', null, true)]
        );

        $response->assertOk();
        $existing = $response->json('facilities.0._existing.media');

        $this->assertNotEmpty($existing, 'the matched facility carries the pictures this site holds today');
        $this->assertEqualsCanonicalizing(
            ['logo', 'image', 'gallery', 'gallery'],
            array_column($existing, 'collection_name')
        );
        $this->assertNotNull($existing[0]['url']);
    }

    public function test_the_review_can_be_saved_back_out_as_a_package(): void
    {
        Storage::fake('public');
        $this->seedFacility();
        $importer = app(\App\Services\FacilityMigration\FacilityMigrationImporter::class);

        $session = $importer->beginSession($this->buildPackage(), ['mode' => 'merge', 'dry_run' => true]);
        $file = storage_path("app/facility-migration/sessions/{$session['token']}/facilities/000000.json");
        $facility = json_decode(file_get_contents($file), true);

        // The edits an operator makes on the review screen.
        $facility['name']['en'] = 'Sunrise Clinic (reviewed)';
        $facility['branches'][0]['name']['en'] = 'Main Branch renamed';
        $facility['media'] = array_values(array_filter(
            $facility['media'],
            fn (array $row) => $row['collection_name'] !== 'logo'
        ));
        // What the screen hangs on a row for its own use must not travel.
        $facility['_existing'] = ['should' => 'not travel'];
        $importer->writeFacilityFile($session['token'], 0, $facility);

        $package = $importer->exportSession($session['token'], [
            'format' => 'zip',
            'include_media' => true,
            'destination' => storage_path('app/facility-migration/reviewed-test.zip'),
        ]);
        $importer->endSession($session['token']);

        // The package is the workbook plus the media/ folder now — no sidecar
        // JSON to decode, so what it actually reimports as is checked by
        // opening it as a fresh session, exactly as a real re-import would.
        $zip = new \ZipArchive;
        $zip->open($package);
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[] = $zip->getNameIndex($i);
        }
        $zip->close();
        $this->assertCount(3, array_filter($entries, fn ($e) => str_starts_with($e, 'media/')));

        $reimport = $importer->beginSession($package, ['mode' => 'merge', 'dry_run' => true]);
        $this->assertSame(FacilityMigrationExporter::ORIGIN_SITE_EXPORT, $reimport['origin']);

        $saved = json_decode(
            file_get_contents(storage_path("app/facility-migration/sessions/{$reimport['token']}/facilities/000000.json")),
            true
        );
        $this->assertSame('Sunrise Clinic (reviewed)', $saved['name']['en']);
        $this->assertSame('Main Branch renamed', $saved['branches'][0]['name']['en']);
        $this->assertArrayNotHasKey('_existing', $saved);

        // The dropped image is gone from the data and from the archive alike —
        // a package must never name a picture it does not carry, nor carry one
        // nothing names.
        $this->assertNotContains('logo', array_column($saved['media'], 'collection_name'));
        $this->assertCount(3, $saved['media']);

        $importer->endSession($reimport['token']);
    }

    public function test_a_saved_review_imports_with_the_edits_that_were_made(): void
    {
        Storage::fake('public');
        $this->seedFacility();
        $importer = app(\App\Services\FacilityMigration\FacilityMigrationImporter::class);

        $session = $importer->beginSession($this->buildPackage(), ['mode' => 'merge', 'dry_run' => true]);
        $file = storage_path("app/facility-migration/sessions/{$session['token']}/facilities/000000.json");
        $facility = json_decode(file_get_contents($file), true);
        $facility['name']['en'] = 'Sunrise Clinic (reviewed)';
        $importer->writeFacilityFile($session['token'], 0, $facility);

        $package = $importer->exportSession($session['token'], [
            'format' => 'zip',
            'include_media' => true,
            'destination' => storage_path('app/facility-migration/reviewed-roundtrip.zip'),
        ]);
        $importer->endSession($session['token']);

        $this->wipeFacilityData();
        $result = app(\App\Services\FacilityMigration\FacilityMigrationImporter::class)
            ->import($package, ['mode' => 'merge']);

        $this->assertSame([], $result['warnings']);
        $restored = Facility::first();
        $this->assertSame('Sunrise Clinic (reviewed)', $restored->getTranslation('name', 'en'));
        // Everything else came through with it, images included.
        $this->assertSame(4, $restored->media()->count());
        $this->assertSame(1, $restored->branches()->count());
    }

    public function test_a_review_saved_without_images_is_a_workbook_naming_none(): void
    {
        Storage::fake('public');
        $this->seedFacility();
        $importer = app(\App\Services\FacilityMigration\FacilityMigrationImporter::class);

        $session = $importer->beginSession($this->buildPackage(), ['mode' => 'merge', 'dry_run' => true]);
        $path = $importer->exportSession($session['token'], [
            'format' => 'xlsx',
            'include_media' => false,
            'destination' => storage_path('app/facility-migration/reviewed-test.xlsx'),
        ]);
        $importer->endSession($session['token']);

        $this->assertStringEndsWith('.xlsx', $path);

        // Importing it leaves the pictures this site holds exactly as they are.
        $before = Facility::first()->media()->count();
        $result = app(\App\Services\FacilityMigration\FacilityMigrationImporter::class)
            ->import($path, ['mode' => 'merge']);

        $this->assertSame([], $result['warnings']);
        $this->assertSame($before, Facility::first()->media()->count());
    }
}
