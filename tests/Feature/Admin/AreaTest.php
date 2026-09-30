<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\Area;
use App\Models\City;
use App\Models\Governorate;
use App\Models\User;
use Database\Seeders\AreaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Areas (admin level 3): the list page, the per-city border feed the governorate
 * map reads, and the seeder that files each COD-AB unit under its city.
 */
class AreaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The migrations seed the real governorates, cities and areas. These
        // tests count rows, so they start from no areas at all.
        DB::table('areas')->delete();
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web'));

        return $user;
    }

    private function makeArea(City $city, string $pcode, string $ar, bool $border = true): Area
    {
        $area = Area::create([
            'governorate_id' => $city->governorate_id,
            'city_id' => $city->id,
            'name' => ['ar' => $ar],
            'pcode' => $pcode,
            'slug' => strtolower($pcode),
        ]);

        if ($border) {
            // `boundary` is not fillable on purpose; seed it the way the seeder does.
            DB::table('areas')->where('id', $area->id)->update([
                'boundary' => json_encode(['type' => 'Polygon', 'coordinates' => [[[31, 30], [31.1, 30], [31.1, 30.1], [31, 30]]]]),
            ]);
        }

        return $area;
    }

    private function fixture(): array
    {
        $giza = Governorate::create(['name' => ['ar' => 'الجيزة', 'en' => 'Giza']]);
        $qena = Governorate::create(['name' => ['ar' => 'قنا', 'en' => 'Qena']]);
        $dokki = City::create(['governorate_id' => $giza->id, 'name' => ['ar' => 'الدقي', 'en' => 'El Dokki']]);
        $qenaCity = City::create(['governorate_id' => $qena->id, 'name' => ['ar' => 'قنا', 'en' => 'Qena']]);

        return [$giza, $qena, $dokki, $qenaCity];
    }

    public function test_the_list_shows_areas_with_their_city_governorate_and_border_flag(): void
    {
        [, , $dokki, $qenaCity] = $this->fixture();
        $this->makeArea($dokki, 'EG120001', 'حي الدقي');
        $this->makeArea($qenaCity, 'EG280001', 'حي قنا', border: false);

        $this->actingAs($this->superAdmin())
            ->get(route('admin.area.list'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Area/List')
                ->where('areas.total', 2)
                ->where('areas.data.0.city.name.en', 'El Dokki')
                ->where('areas.data.0.governorate.name.en', 'Giza')
                ->where('areas.data.0.has_border', true)
                ->where('areas.data.1.has_border', false)
                ->has('governorates', Governorate::count()));
    }

    public function test_the_list_never_ships_the_border_blob(): void
    {
        [, , $dokki] = $this->fixture();
        $this->makeArea($dokki, 'EG120001', 'حي الدقي');

        $this->actingAs($this->superAdmin())
            ->get(route('admin.area.list'))
            ->assertInertia(fn (Assert $page) => $page->missing('areas.data.0.boundary')->missing('areas.data.0.geometry'));
    }

    public function test_it_filters_by_governorate_city_and_search(): void
    {
        [$giza, , $dokki, $qenaCity] = $this->fixture();
        $this->makeArea($dokki, 'EG120001', 'حي الدقي');
        $this->makeArea($dokki, 'EG120002', 'حي المهندسين');
        $this->makeArea($qenaCity, 'EG280001', 'حي قنا');
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get(route('admin.area.list', ['governorate_id' => $giza->id]))
            ->assertInertia(fn (Assert $page) => $page->where('areas.total', 2)->has('cities', 1));

        $this->actingAs($admin)->get(route('admin.area.list', ['city_id' => $qenaCity->id]))
            ->assertInertia(fn (Assert $page) => $page->where('areas.total', 1));

        $this->actingAs($admin)->get(route('admin.area.list', ['search' => 'المهندسين']))
            ->assertInertia(fn (Assert $page) => $page->where('areas.total', 1));

        $this->actingAs($admin)->get(route('admin.area.list', ['search' => 'EG280001']))
            ->assertInertia(fn (Assert $page) => $page->where('areas.total', 1));

        $this->actingAs($admin)->get(route('admin.area.list', ['search' => 'nothing-matches']))
            ->assertInertia(fn (Assert $page) => $page->where('areas.total', 0));
    }

    public function test_the_border_feed_returns_one_citys_areas_with_geometry(): void
    {
        [, , $dokki, $qenaCity] = $this->fixture();
        $this->makeArea($dokki, 'EG120001', 'حي الدقي');
        $this->makeArea($dokki, 'EG120002', 'بلا حدود', border: false);
        $this->makeArea($qenaCity, 'EG280001', 'حي قنا');

        $response = $this->actingAs($this->superAdmin())
            ->getJson(route('admin.city.area-borders', $dokki->id))
            ->assertOk();

        $rows = collect($response->json('areas'))->keyBy('pcode');

        $this->assertCount(2, $rows, 'only the requested city\'s areas');
        $this->assertSame('Polygon', $rows['EG120001']['geometry']['type']);
        $this->assertSame('حي الدقي', $rows['EG120001']['name']['ar']);
        $this->assertNull($rows['EG120002']['geometry']);
    }

    public function test_a_user_without_geography_permission_is_refused(): void
    {
        [, , $dokki] = $this->fixture();

        $this->actingAs(User::factory()->create())->get(route('admin.area.list'))->assertForbidden();
        $this->actingAs(User::factory()->create())->getJson(route('admin.city.area-borders', $dokki->id))->assertForbidden();
    }

    public function test_deleting_a_city_removes_its_areas(): void
    {
        [, , $dokki, $qenaCity] = $this->fixture();
        $this->makeArea($dokki, 'EG120001', 'حي الدقي');
        $kept = $this->makeArea($qenaCity, 'EG280001', 'حي قنا');

        $dokki->delete();

        $this->assertSame([$kept->id], Area::pluck('id')->all());
    }

    public function test_the_seeder_files_each_area_under_its_city_and_is_safe_to_rerun(): void
    {
        (new AreaSeeder)->run();
        $first = Area::count();
        (new AreaSeeder)->run();

        $this->assertGreaterThan(0, $first);
        $this->assertSame($first, Area::count(), 're-running must upsert on pcode, not duplicate');
        $this->assertSame(
            0,
            DB::table('areas')->join('cities', 'cities.id', '=', 'areas.city_id')
                ->whereColumn('areas.governorate_id', '!=', 'cities.governorate_id')->count(),
            'an area always carries its city\'s governorate',
        );
        $this->assertSame(0, Area::whereNull('boundary')->count());
    }

    public function test_the_seeder_skips_areas_whose_city_this_site_lacks_instead_of_guessing(): void
    {
        // No cities at all: nothing can be filed, and nothing may be invented.
        DB::table('cities')->delete();

        (new AreaSeeder)->run();

        $this->assertSame(0, Area::count());
    }
}
