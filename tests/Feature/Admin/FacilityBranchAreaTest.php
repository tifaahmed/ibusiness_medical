<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\Area;
use App\Models\City;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityType;
use App\Models\Governorate;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A branch's OPTIONAL area: saved from both branch forms, only ever one of the
 * chosen city's areas, dropped when the city changes, shown in the list and
 * kept from being deleted while a branch names it.
 */
class FacilityBranchAreaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // This install's cached config keeps CSRF on under phpunit; these tests
        // are about areas, not the token.
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web');
        // A fresh schema has no permission rows; the controllers look these up by name.
        foreach (['manage facilities', 'manage facility branches', 'manage own facility branches', 'view facility branches'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $role->givePermissionTo('manage facilities', 'manage facility branches');
        $user->assignRole($role);

        return $user;
    }

    /** @return array{gov: Governorate, city: City, other: City, area: Area, foreign: Area, facility: Facility} */
    private function world(): array
    {
        $gov = Governorate::create(['name' => ['en' => 'Alexandria', 'ar' => 'الإسكندرية']]);
        $city = City::create(['governorate_id' => $gov->id, 'name' => ['en' => 'El Raml', 'ar' => 'الرمل']]);
        $other = City::create(['governorate_id' => $gov->id, 'name' => ['en' => 'Karmouz', 'ar' => 'كرموز']]);
        $area = Area::create(['governorate_id' => $gov->id, 'city_id' => $city->id, 'name' => ['ar' => 'حي الرمل'], 'pcode' => 'EG990001', 'slug' => 'eg990001']);
        $foreign = Area::create(['governorate_id' => $gov->id, 'city_id' => $other->id, 'name' => ['ar' => 'حي كرموز'], 'pcode' => 'EG990002', 'slug' => 'eg990002']);
        $type = FacilityType::create(['name' => ['en' => 'Clinic', 'ar' => 'عيادة']]);
        $facility = Facility::create(['name' => ['en' => 'Mytra Labs', 'ar' => 'معامل ميترا'], 'facility_type_id' => $type->id]);

        return compact('gov', 'city', 'other', 'area', 'foreign', 'facility');
    }

    private function payload(array $w, array $extra = []): array
    {
        return [
            'facility_id' => $w['facility']->id,
            'governorate_id' => $w['gov']->id,
            'city_id' => $w['city']->id,
            'name' => ['en' => 'Raml branch', 'ar' => 'فرع الرمل'],
            'address' => ['en' => 'Corniche road', 'ar' => 'طريق الكورنيش'],
            ...$extra,
        ];
    }

    public function test_the_standalone_form_saves_a_branch_with_an_area_of_its_city(): void
    {
        $w = $this->world();

        $this->actingAs($this->admin())
            ->post(route('admin.facility-branch.store'), $this->payload($w, ['area_id' => $w['area']->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($w['area']->id, FacilityBranch::firstOrFail()->area_id);
    }

    public function test_the_area_is_optional(): void
    {
        $w = $this->world();

        $this->actingAs($this->admin())
            ->post(route('admin.facility-branch.store'), $this->payload($w))
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin())
            ->post(route('admin.facility-branch.store'), $this->payload($w, ['area_id' => '', 'name' => ['en' => 'Second', 'ar' => 'الثاني'], 'address' => ['en' => 'Other road', 'ar' => 'طريق آخر']]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, FacilityBranch::whereNull('area_id')->count());
    }

    public function test_an_area_of_another_city_is_refused(): void
    {
        $w = $this->world();

        $this->actingAs($this->admin())
            ->post(route('admin.facility-branch.store'), $this->payload($w, ['area_id' => $w['foreign']->id]))
            ->assertSessionHasErrors('area_id');
        $this->actingAs($this->admin())
            ->post(route('admin.facility-branch.store'), $this->payload($w, ['area_id' => 999999]))
            ->assertSessionHasErrors('area_id');

        $this->assertSame(0, FacilityBranch::count());
    }

    public function test_the_inline_save_from_the_facility_form_takes_an_area_and_returns_it(): void
    {
        $w = $this->world();
        $payload = collect($this->payload($w, ['area_id' => $w['area']->id]))->except('facility_id')->all();

        $this->actingAs($this->admin())
            ->postJson(route('admin.facility.branch.save', $w['facility']->slug), $payload)
            ->assertOk()
            ->assertJsonPath('branch.area_id', $w['area']->id);

        $this->actingAs($this->admin())
            ->postJson(route('admin.facility.branch.save', $w['facility']->slug), [...$payload, 'area_id' => $w['foreign']->id, 'name' => ['en' => 'Other', 'ar' => 'آخر'], 'address' => ['en' => 'x road', 'ar' => 'شارع س']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('area_id');
    }

    public function test_editing_can_change_and_clear_the_area(): void
    {
        $w = $this->world();
        $second = Area::create(['governorate_id' => $w['gov']->id, 'city_id' => $w['city']->id, 'name' => ['ar' => 'حي ثان'], 'pcode' => 'EG990003', 'slug' => 'eg990003']);
        $branch = FacilityBranch::create($this->payload($w, ['area_id' => $w['area']->id]));
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.facility-branch.update', $branch->slug), $this->payload($w, ['area_id' => $second->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame($second->id, $branch->fresh()->area_id);

        $this->actingAs($admin)->put(route('admin.facility-branch.update', $branch->slug), $this->payload($w, ['area_id' => null]))
            ->assertSessionHasNoErrors();
        $this->assertNull($branch->fresh()->area_id);
    }

    public function test_an_update_that_does_not_mention_the_area_keeps_it(): void
    {
        $w = $this->world();
        $branch = FacilityBranch::create($this->payload($w, ['area_id' => $w['area']->id]));

        $this->actingAs($this->admin())->put(route('admin.facility-branch.update', $branch->slug), $this->payload($w))
            ->assertSessionHasNoErrors();

        $this->assertSame($w['area']->id, $branch->fresh()->area_id);
    }

    public function test_moving_a_branch_to_another_city_drops_the_old_citys_area(): void
    {
        $w = $this->world();
        $branch = FacilityBranch::create($this->payload($w, ['area_id' => $w['area']->id]));

        // Any path — the form, an import, the AI place sweep — goes through the model.
        $branch->update(['city_id' => $w['other']->id]);

        $this->assertNull($branch->fresh()->area_id);
    }

    public function test_the_city_areas_feed_lists_names_only(): void
    {
        $w = $this->world();

        $rows = $this->actingAs($this->admin())
            ->getJson(route('admin.facility.branch.city-areas', $w['city']->id))
            ->assertOk()
            ->json('areas');

        $this->assertCount(1, $rows);
        $this->assertSame('حي الرمل', $rows[0]['name']['ar']);
        $this->assertArrayNotHasKey('geometry', $rows[0]);
        $this->assertArrayNotHasKey('boundary', $rows[0]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.facility.branch.city-areas', $w['city']->id))
            ->assertForbidden();
    }

    public function test_the_list_row_and_the_show_page_carry_the_area_when_there_is_one(): void
    {
        $w = $this->world();
        $with = FacilityBranch::create($this->payload($w, ['area_id' => $w['area']->id]));
        $without = FacilityBranch::create($this->payload($w, ['name' => ['en' => 'B', 'ar' => 'ب'], 'address' => ['en' => 'b road', 'ar' => 'شارع ب']]));

        // The list page's own queries are MySQL-only (JSON_UNQUOTE), so its row
        // resource is checked directly, loaded the way the list controller loads it.
        $rows = FacilityBranch::with(['facility.facilityType', 'governorate', 'city', 'area'])->get()->keyBy('id')
            ->map(fn ($branch) => (new \App\Http\Resources\Admin\FacilityBranch\List\AdminFacilityBranchListResource($branch))->toArray(request()));
        $this->assertSame('حي الرمل', $rows[$with->id]['area']['name']['ar']);
        $this->assertNull($rows[$without->id]['area']);

        $this->actingAs($this->admin())->get(route('admin.facility-branch.show', $with->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('facilityBranch.area.id', $w['area']->id));
    }

    public function test_an_area_a_branch_names_cannot_be_deleted(): void
    {
        $w = $this->world();
        FacilityBranch::create($this->payload($w, ['area_id' => $w['area']->id]));
        $admin = $this->admin();

        $this->actingAs($admin)->deleteJson(route('admin.area.destroy', $w['area']->id))->assertUnprocessable();
        $this->assertNotNull(Area::find($w['area']->id));

        // One nobody names goes as before.
        $this->actingAs($admin)->deleteJson(route('admin.area.destroy', $w['foreign']->id))->assertOk();
    }
}
