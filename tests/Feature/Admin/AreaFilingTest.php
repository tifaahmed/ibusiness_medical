<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\Area;
use App\Models\City;
use App\Models\User;
use App\Support\GeoJson;
use Database\Seeders\AreaSeeder;
use Database\Seeders\UnmarkedCitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Every imported area sits under the smallest city border holding its centre
 * (or its governorate's Unmarked City), and a city with no areas of its own says
 * which area it sits inside.
 */
class AreaFilingTest extends TestCase
{
    use RefreshDatabase;

    private function cityKey(City $city): string
    {
        return $city->governorate->slug.'/'.($city->getTranslation('name', 'en', false) ?: $city->getTranslation('name', 'ar', false));
    }

    public function test_after_the_migration_steps_every_area_is_under_the_city_the_file_names(): void
    {
        // The two steps of migrations 150000 and 170000, run here so the test
        // does not depend on which of them this database has already seen.
        (new UnmarkedCitySeeder)->run();
        (new AreaSeeder)->refile();

        $file = json_decode(file_get_contents(database_path('data/egypt-area-boundaries.json')), true);

        $keys = [];
        foreach (City::with('governorate:id,slug')->get() as $city) {
            $keys[$city->id] = $this->cityKey($city);
        }
        $filed = DB::table('areas')->pluck('city_id', 'pcode')->map(fn ($id) => $keys[$id]);

        $this->assertCount(count($file), $filed, 'no area lost, none duplicated');

        $wrong = [];
        foreach ($file as $entry) {
            if (($filed[$entry['pcode']] ?? null) !== $entry['city']) {
                $wrong[] = $entry['pcode'].': file '.$entry['city'].' / db '.($filed[$entry['pcode']] ?? 'none');
            }
        }
        $this->assertSame([], array_slice($wrong, 0, 5));
        $this->assertSame(0, count($wrong));

        $this->assertGreaterThan(0, $filed->filter(fn ($key) => str_ends_with($key, '/Unmarked City'))->count(), 'areas outside every city border go to the Unmarked City');
    }

    public function test_refile_puts_a_moved_area_back_but_keeps_what_an_admin_edited(): void
    {
        $area = Area::query()->whereNull('boundary')->first() ?? Area::query()->firstOrFail();
        $home = $area->city_id;
        $elsewhere = City::where('governorate_id', $area->governorate_id)->where('id', '!=', $home)->firstOrFail();

        $area->update(['name' => ['ar' => 'اسم معدل']]);
        DB::table('areas')->where('id', $area->id)->update(['city_id' => $elsewhere->id]);
        $handMade = Area::create(['governorate_id' => $area->governorate_id, 'city_id' => $elsewhere->id, 'name' => ['ar' => 'يدوي'], 'pcode' => 'MHAND0001', 'slug' => 'mhand0001']);

        $result = (new AreaSeeder)->refile();

        $this->assertGreaterThanOrEqual(1, $result['moved']);
        $this->assertSame($home, $area->fresh()->city_id);
        $this->assertSame('اسم معدل', $area->fresh()->getTranslation('name', 'ar'), 'the edited name survives');
        $this->assertSame($elsewhere->id, $handMade->fresh()->city_id, 'a hand-made area is not in the file and is left alone');
        $this->assertSame(0, (new AreaSeeder)->refile()['moved'], 'a second run has nothing to do');
    }

    public function test_a_city_with_no_areas_names_the_area_it_sits_inside(): void
    {
        $miami = City::where('name->en', 'Miami')->whereHas('governorate', fn ($q) => $q->where('slug', 'alexandria'))->firstOrFail();
        $this->assertSame(0, $miami->areas()->count());

        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web'));

        $this->actingAs($admin)->get(route('admin.city.show', $miami->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('areas', 0)
                ->where('insideArea.name.ar', 'الناصريه')
                ->has('insideArea.city.name'));
    }

    public function test_a_city_that_owns_areas_names_no_containing_area(): void
    {
        $city = City::has('areas')->firstOrFail();
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web'));

        $this->actingAs($admin)->get(route('admin.city.show', $city->id))
            ->assertInertia(fn (Assert $page) => $page->where('insideArea', null));
    }

    public function test_geojson_centroid_and_containment(): void
    {
        $square = ['type' => 'Polygon', 'coordinates' => [[[30, 30], [32, 30], [32, 32], [30, 32], [30, 30]]]];
        $this->assertEqualsWithDelta([31, 31], GeoJson::centroid($square), 1e-9);
        $this->assertTrue(GeoJson::contains($square, 31, 31));
        $this->assertFalse(GeoJson::contains($square, 33, 31));

        $holed = ['type' => 'Polygon', 'coordinates' => [$square['coordinates'][0], [[30.5, 30.5], [31.5, 30.5], [31.5, 31.5], [30.5, 31.5], [30.5, 30.5]]]];
        $this->assertFalse(GeoJson::contains($holed, 31, 31), 'the hole is outside');
        $this->assertTrue(GeoJson::contains($holed, 30.2, 30.2));

        $multi = ['type' => 'MultiPolygon', 'coordinates' => [$square['coordinates'], [[[35, 25], [35.1, 25], [35.1, 25.1], [35, 25.1], [35, 25]]]]];
        $this->assertTrue(GeoJson::contains($multi, 35.05, 25.05));
        $this->assertEqualsWithDelta([31, 31], GeoJson::centroid($multi), 1e-9, 'the largest part');
        $this->assertNull(GeoJson::centroid(null));
        $this->assertFalse(GeoJson::contains(null, 1, 1));
    }
}
