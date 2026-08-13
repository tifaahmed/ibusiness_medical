<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\City;
use App\Models\Facility;
use App\Models\FacilityBranch;
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
        // though the model calls it translatable, and the migration must carry
        // the raw string across rather than wrapping it in {"en": …}.
        $salesId = Sales::query()->insertGetId([
            'name' => 'Rep One',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $tag = Tag::create(['name' => 'Featured', 'color' => '#F59E0B']);

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

        $facility = Facility::with(['branches', 'tags', 'facilityType', 'governorate', 'city', 'sales'])->first();

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
        $this->assertSame('Rep One', $facility->sales->getRawOriginal('name'));
        $this->assertSame(['Featured'], $facility->tags->pluck('name')->all());
        $this->assertSame('30.0444444', (string) $facility->latitude);

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
        $this->assertSame(4, Facility::first()->media()->count());
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
}
