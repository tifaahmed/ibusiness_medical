<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\City;
use App\Models\Governorate;
use App\Models\User;
use Database\Seeders\UnmarkedCitySeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The border editor on the governorate edit page (save a governorate's or a
 * city's border) and the "Unmarked City" that holds the ground no city covers.
 */
class BoundaryEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // This install's .env is production, which keeps CSRF checking switched
        // on under phpunit; these tests are about the borders, not the token.
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

    private function fixture(): array
    {
        $giza = Governorate::create(['name' => ['ar' => 'اختبار', 'en' => 'Test Governorate']]);
        $city = City::create(['governorate_id' => $giza->id, 'name' => ['ar' => 'مدينة', 'en' => 'Test City']]);

        return [$giza, $city];
    }

    public function test_a_city_border_is_saved_and_the_slug_is_left_alone(): void
    {
        [, $city] = $this->fixture();
        $slug = $city->slug;

        $this->actingAs($this->superAdmin())
            ->putJson(route('admin.city.boundary.update', $city->id), ['geometry' => $this->square()])
            ->assertOk()
            ->assertJsonPath('geometry.type', 'Polygon');

        $stored = json_decode(DB::table('cities')->where('id', $city->id)->value('boundary'), true);
        $this->assertSame('Polygon', $stored['type']);
        $this->assertSame($slug, City::find($city->id)->slug);
    }

    public function test_a_governorate_border_is_saved_and_can_be_cleared(): void
    {
        [$gov] = $this->fixture();
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->putJson(route('admin.governorate.boundary.update', $gov->id), ['geometry' => $this->square()])
            ->assertOk();
        $this->assertNotNull(DB::table('governorates')->where('id', $gov->id)->value('boundary'));

        $this->actingAs($admin)
            ->putJson(route('admin.governorate.boundary.update', $gov->id), ['geometry' => null])
            ->assertOk();
        $this->assertNull(DB::table('governorates')->where('id', $gov->id)->value('boundary'));
    }

    public function test_a_multipolygon_is_accepted(): void
    {
        [, $city] = $this->fixture();

        $geometry = ['type' => 'MultiPolygon', 'coordinates' => [$this->square()['coordinates'], $this->square(32.0, 29.0)['coordinates']]];

        $this->actingAs($this->superAdmin())
            ->putJson(route('admin.city.boundary.update', $city->id), ['geometry' => $geometry])
            ->assertOk();
    }

    public function test_bad_geometry_is_refused_and_nothing_is_written(): void
    {
        [, $city] = $this->fixture();
        $admin = $this->superAdmin();

        $unclosed = ['type' => 'Polygon', 'coordinates' => [[[31, 30], [31.1, 30], [31.1, 30.1], [31, 30.1]]]];
        $abroad = $this->square(2.0, 48.0);
        $point = ['type' => 'Point', 'coordinates' => [31, 30]];

        foreach ([$unclosed, $abroad, $point, 'nonsense'] as $bad) {
            $this->actingAs($admin)
                ->putJson(route('admin.city.boundary.update', $city->id), ['geometry' => $bad])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('geometry');
        }

        $this->assertNull(DB::table('cities')->where('id', $city->id)->value('boundary'));
    }

    public function test_the_geometry_field_is_required_even_to_clear(): void
    {
        [, $city] = $this->fixture();

        $this->actingAs($this->superAdmin())
            ->putJson(route('admin.city.boundary.update', $city->id), [])
            ->assertUnprocessable();
    }

    public function test_someone_without_the_permission_cannot_save_a_border(): void
    {
        [, $city] = $this->fixture();

        $this->actingAs(User::factory()->create())
            ->putJson(route('admin.city.boundary.update', $city->id), ['geometry' => $this->square()])
            ->assertForbidden();
    }

    public function test_the_city_feed_carries_the_governorate_border_and_the_unmarked_flag(): void
    {
        [$gov, $city] = $this->fixture();
        $unmarked = City::create(['governorate_id' => $gov->id, 'name' => City::UNMARKED_NAME]);
        DB::table('governorates')->where('id', $gov->id)->update(['boundary' => json_encode($this->square())]);

        $rows = $this->actingAs($this->superAdmin())
            ->getJson(route('admin.governorate.city-borders', $gov->id))
            ->assertOk()
            ->assertJsonPath('governorate_geometry.type', 'Polygon')
            ->json('cities');

        $byId = collect($rows)->keyBy('id');
        $this->assertFalse($byId[$city->id]['is_unmarked']);
        $this->assertTrue($byId[$unmarked->id]['is_unmarked']);
    }

    public function test_the_seeder_makes_one_unmarked_city_per_bordered_governorate_and_is_rerunnable(): void
    {
        $slugs = array_keys(json_decode(file_get_contents(database_path('data/egypt-unmarked-boundaries.json')), true));
        $slug = $slugs[0];
        $present = Governorate::whereIn('slug', $slugs)->count();
        $this->assertGreaterThan(0, $present, 'the migrations seed the real governorates');

        (new UnmarkedCitySeeder)->run();
        (new UnmarkedCitySeeder)->run();

        $this->assertSame($present, City::where('name->en', 'Unmarked City')->count());

        $city = City::where('governorate_id', Governorate::where('slug', $slug)->value('id'))
            ->where('name->en', 'Unmarked City')->firstOrFail();
        $this->assertTrue($city->isUnmarked());
        $this->assertSame('مدينة غير محددة', $city->getTranslation('name', 'ar'));
        $this->assertNotNull(DB::table('cities')->where('id', $city->id)->value('boundary'));
    }
}
