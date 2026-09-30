<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\Area;
use App\Models\City;
use App\Models\Governorate;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The cities page and, inside a city, adding / renaming / deleting its areas and
 * saving the borders of both.
 */
class CityAreaManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // This install's .env is production, which keeps CSRF checking switched
        // on under phpunit; these tests are about the pages, not the token.
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web'));

        return $user;
    }

    private function square(float $lng = 31.0, float $lat = 30.0): array
    {
        return ['type' => 'Polygon', 'coordinates' => [[[$lng, $lat], [$lng + 0.1, $lat], [$lng + 0.1, $lat + 0.1], [$lng, $lat + 0.1], [$lng, $lat]]]];
    }

    /** A governorate with two cities, one carrying two areas (one bordered). */
    private function fixture(): array
    {
        $gov = Governorate::create(['name' => ['ar' => 'اختبار', 'en' => 'Test Governorate']]);
        $city = City::create(['governorate_id' => $gov->id, 'name' => ['ar' => 'مدينة الاختبار', 'en' => 'Test City']]);
        $other = City::create(['governorate_id' => $gov->id, 'name' => ['ar' => 'مدينة أخرى', 'en' => 'Other City']]);
        $bordered = Area::create(['governorate_id' => $gov->id, 'city_id' => $city->id, 'name' => ['ar' => 'حي أ'], 'pcode' => 'EG990001', 'slug' => 'eg990001']);
        Area::create(['governorate_id' => $gov->id, 'city_id' => $city->id, 'name' => ['ar' => 'حي ب'], 'pcode' => 'EG990002', 'slug' => 'eg990002']);
        DB::table('areas')->where('id', $bordered->id)->update(['boundary' => json_encode($this->square())]);
        DB::table('cities')->where('id', $city->id)->update(['boundary' => json_encode($this->square())]);

        return [$gov, $city, $other, $bordered];
    }

    public function test_the_list_shows_cities_with_counts_and_border_flags_but_never_the_blob(): void
    {
        [$gov, $city] = $this->fixture();

        $this->actingAs($this->superAdmin())
            ->get(route('admin.city.list', ['governorate_id' => $gov->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/City/List')
                ->where('cities.total', 2)
                ->where('canManage', true)
                ->where('cities.data.0.id', $city->id)
                ->where('cities.data.0.areas_count', 2)
                ->where('cities.data.0.has_border', true)
                ->where('cities.data.1.areas_count', 0)
                ->where('cities.data.1.has_border', false)
                ->missing('cities.data.0.boundary')
                ->missing('cities.data.0.geometry'));
    }

    public function test_the_list_filters_by_name(): void
    {
        $this->fixture();

        $this->actingAs($this->superAdmin())
            ->get(route('admin.city.list', ['search' => 'Other']))
            ->assertInertia(fn (Assert $page) => $page->where('cities.total', 1));
    }

    public function test_the_city_page_carries_its_areas_and_border_flags(): void
    {
        [, $city] = $this->fixture();

        $this->actingAs($this->superAdmin())
            ->get(route('admin.city.show', $city->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/City/Show')
                ->where('city.name.en', 'Test City')
                ->where('city.governorate.name.en', 'Test Governorate')
                ->has('areas', 2)
                ->where('areas.0.has_border', true)
                ->where('areas.1.has_border', false)
                ->where('canManage', true));
    }

    public function test_a_city_can_be_added_renamed_and_deleted(): void
    {
        [$gov] = $this->fixture();
        $admin = $this->superAdmin();

        $id = $this->actingAs($admin)
            ->postJson(route('admin.city.store'), ['governorate_id' => $gov->id, 'name' => ['ar' => 'جديدة', 'en' => 'Brand New']])
            ->assertCreated()
            ->json('id');

        $this->actingAs($admin)
            ->putJson(route('admin.city.update', $id), ['name' => ['ar' => 'جديدة جداً', 'en' => 'Brand New Two']])
            ->assertOk()
            ->assertJsonPath('name.en', 'Brand New Two');

        $this->actingAs($admin)->deleteJson(route('admin.city.destroy', $id))->assertOk();
        $this->assertNull(City::find($id));
    }

    public function test_a_city_needs_both_names(): void
    {
        [$gov] = $this->fixture();

        $this->actingAs($this->superAdmin())
            ->postJson(route('admin.city.store'), ['governorate_id' => $gov->id, 'name' => ['ar' => 'بلا إنجليزي']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name.en');
    }

    public function test_deleting_a_city_removes_its_areas(): void
    {
        [, $city] = $this->fixture();

        $this->actingAs($this->superAdmin())->deleteJson(route('admin.city.destroy', $city->id))->assertOk();

        $this->assertSame(0, Area::where('city_id', $city->id)->count());
    }

    public function test_a_city_still_used_by_a_member_address_is_not_deleted(): void
    {
        [, $city] = $this->fixture();
        \App\Models\Address::factory()->create(['city_id' => $city->id]);

        $this->actingAs($this->superAdmin())
            ->deleteJson(route('admin.city.destroy', $city->id))
            ->assertUnprocessable();

        $this->assertNotNull(City::find($city->id));
    }

    public function test_an_area_can_be_added_with_a_generated_code_renamed_and_deleted(): void
    {
        [, $city] = $this->fixture();
        $admin = $this->superAdmin();

        $row = $this->actingAs($admin)
            ->postJson(route('admin.area.store', $city->id), ['name' => ['ar' => 'حي جديد', 'en' => 'New Quarter']])
            ->assertCreated()
            ->assertJsonPath('has_border', false)
            ->json();

        $this->assertStringStartsWith('M', $row['pcode']);
        $this->assertDatabaseHas('areas', ['id' => $row['id'], 'city_id' => $city->id, 'slug' => strtolower($row['pcode'])]);

        $this->actingAs($admin)
            ->putJson(route('admin.area.update', $row['id']), ['name' => ['ar' => 'حي أحدث']])
            ->assertOk()
            ->assertJsonPath('name.ar', 'حي أحدث');

        $this->actingAs($admin)->deleteJson(route('admin.area.destroy', $row['id']))->assertOk();
        $this->assertNull(Area::find($row['id']));
    }

    public function test_an_area_code_must_be_unique_and_plain(): void
    {
        [, $city] = $this->fixture();
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->postJson(route('admin.area.store', $city->id), ['name' => ['ar' => 'مكرر'], 'pcode' => 'EG990001'])
            ->assertUnprocessable()->assertJsonValidationErrors('pcode');

        $this->actingAs($admin)
            ->postJson(route('admin.area.store', $city->id), ['name' => ['ar' => 'غريب'], 'pcode' => 'no spaces!'])
            ->assertUnprocessable()->assertJsonValidationErrors('pcode');
    }

    public function test_an_area_border_is_saved_and_cleared(): void
    {
        [, , , $area] = $this->fixture();
        $admin = $this->superAdmin();
        $moved = $this->square(31.5, 30.5);

        $this->actingAs($admin)
            ->putJson(route('admin.area.boundary.update', $area->id), ['geometry' => $moved])
            ->assertOk();
        $this->assertSame(31.5, json_decode(DB::table('areas')->where('id', $area->id)->value('boundary'), true)['coordinates'][0][0][0]);

        $this->actingAs($admin)
            ->putJson(route('admin.area.boundary.update', $area->id), ['geometry' => null])
            ->assertOk();
        $this->assertNull(DB::table('areas')->where('id', $area->id)->value('boundary'));
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    public function test_a_city_viewer_reads_cities_but_not_areas_and_changes_nothing(): void
    {
        [, $city, , $area] = $this->fixture();
        $viewer = $this->userWith('view cities');

        $this->actingAs($viewer)->get(route('admin.city.list'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('canManage', false));
        // The city page opens, but its areas are behind their own permission.
        $this->actingAs($viewer)->get(route('admin.city.show', $city->id))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('canManage', false)->where('canViewAreas', false)->where('canManageAreas', false)
                ->has('areas', 0)->where('city.areas_count', 2));
        $this->actingAs($viewer)->get(route('admin.area.list'))->assertForbidden();
        $this->actingAs($viewer)->getJson(route('admin.city.area-borders', $city->id))->assertForbidden();
        $this->actingAs($viewer)->getJson(route('admin.governorate.city-borders', $city->governorate_id))->assertOk();

        $this->actingAs($viewer)->postJson(route('admin.city.store'), ['governorate_id' => $city->governorate_id, 'name' => ['ar' => 'س', 'en' => 'S']])->assertForbidden();
        $this->actingAs($viewer)->putJson(route('admin.city.update', $city->id), ['name' => ['ar' => 'س', 'en' => 'S']])->assertForbidden();
        $this->actingAs($viewer)->putJson(route('admin.city.boundary.update', $city->id), ['geometry' => null])->assertForbidden();
        $this->actingAs($viewer)->deleteJson(route('admin.city.destroy', $city->id))->assertForbidden();
        $this->actingAs($viewer)->postJson(route('admin.area.store', $city->id), ['name' => ['ar' => 'x']])->assertForbidden();
        $this->actingAs($viewer)->deleteJson(route('admin.area.destroy', $area->id))->assertForbidden();
    }

    public function test_an_area_manager_runs_areas_but_not_cities(): void
    {
        [, $city, , $area] = $this->fixture();
        $user = $this->userWith('manage areas', 'view cities');

        $this->actingAs($user)->get(route('admin.city.show', $city->id))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('canManage', false)->where('canViewAreas', true)->where('canManageAreas', true)->has('areas', 2));
        $this->actingAs($user)->get(route('admin.area.list'))->assertOk();

        $this->actingAs($user)->postJson(route('admin.area.store', $city->id), ['name' => ['ar' => 'حي جديد']])->assertCreated();
        $this->actingAs($user)->putJson(route('admin.area.boundary.update', $area->id), ['geometry' => $this->square(31.2, 30.2)])->assertOk();

        $this->actingAs($user)->putJson(route('admin.city.boundary.update', $city->id), ['geometry' => null])->assertForbidden();
        $this->actingAs($user)->deleteJson(route('admin.city.destroy', $city->id))->assertForbidden();
    }

    public function test_a_city_manager_runs_cities_but_not_areas(): void
    {
        [, $city, , $area] = $this->fixture();
        $user = $this->userWith('manage cities');

        $this->actingAs($user)->putJson(route('admin.city.boundary.update', $city->id), ['geometry' => $this->square(31.3, 30.3)])->assertOk();
        $this->actingAs($user)->putJson(route('admin.city.update', $city->id), ['name' => ['ar' => 'مدينة', 'en' => 'Renamed']])->assertOk();

        $this->actingAs($user)->postJson(route('admin.area.store', $city->id), ['name' => ['ar' => 'x']])->assertForbidden();
        $this->actingAs($user)->putJson(route('admin.area.boundary.update', $area->id), ['geometry' => null])->assertForbidden();
        $this->actingAs($user)->get(route('admin.area.list'))->assertForbidden();
    }

    public function test_the_governorate_permissions_no_longer_open_cities_or_areas(): void
    {
        [$gov, $city, , $area] = $this->fixture();
        $user = $this->userWith('manage governorates', 'view governorates');

        $this->actingAs($user)->get(route('admin.city.list'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.city.show', $city->id))->assertForbidden();
        $this->actingAs($user)->get(route('admin.area.list'))->assertForbidden();
        $this->actingAs($user)->putJson(route('admin.city.boundary.update', $city->id), ['geometry' => null])->assertForbidden();
        $this->actingAs($user)->putJson(route('admin.area.boundary.update', $area->id), ['geometry' => null])->assertForbidden();

        // …while the governorate's own border and the shared map feeds still work.
        $this->actingAs($user)->putJson(route('admin.governorate.boundary.update', $gov->id), ['geometry' => $this->square()])->assertOk();
        $this->actingAs($user)->getJson(route('admin.governorate.city-borders', $gov->id))->assertOk();
        $this->actingAs($user)->getJson(route('admin.city.area-borders', $city->id))->assertOk();
    }

    public function test_the_migration_hands_existing_governorate_holders_the_new_permissions(): void
    {
        $manager = Role::findOrCreate('geo-manager', 'web');
        $manager->givePermissionTo(Permission::findOrCreate('manage governorates', 'web'));
        $viewerRole = Role::findOrCreate('geo-viewer', 'web');
        $viewerRole->givePermissionTo(Permission::findOrCreate('view governorates', 'web'));
        $direct = $this->userWith('manage own governorates');

        (require database_path('migrations/2026_09_20_160000_split_city_and_area_permissions.php'))->up();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertEqualsCanonicalizing(
            ['manage governorates', 'manage cities', 'view cities', 'manage areas', 'view areas'],
            $manager->fresh()->permissions->pluck('name')->all(),
        );
        $this->assertEqualsCanonicalizing(['view governorates', 'view cities', 'view areas'], $viewerRole->fresh()->permissions->pluck('name')->all());
        // "Own" carries over as read access only — there is no "own" city to manage.
        $this->assertEqualsCanonicalizing(['manage own governorates', 'view cities', 'view areas'], $direct->fresh()->getDirectPermissions()->pluck('name')->all());
    }
}
