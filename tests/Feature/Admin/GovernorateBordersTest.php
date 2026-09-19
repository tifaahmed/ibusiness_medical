<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\City;
use App\Models\Governorate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The governorate list's map view reads every border from one JSON endpoint.
 */
class GovernorateBordersTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web'));

        return $user;
    }

    public function test_it_lists_every_governorate_with_its_border_or_null(): void
    {
        $withBorder = Governorate::create(['name' => ['ar' => 'الإسكندرية', 'en' => 'Alexandria']]);
        $without = Governorate::create(['name' => ['ar' => 'حلوان', 'en' => 'Helwan']]);

        // `boundary` is not fillable on purpose; seed it the way the seeder does.
        DB::table('governorates')->where('id', $withBorder->id)->update([
            'boundary' => json_encode(['type' => 'Polygon', 'coordinates' => [[[29, 30], [30, 30], [30, 31], [29, 30]]]]),
        ]);

        $response = $this->actingAs($this->superAdmin())
            ->getJson(route('admin.governorate.borders'))
            ->assertOk();

        $rows = collect($response->json('governorates'))->keyBy('id');

        $this->assertSame('Polygon', $rows[$withBorder->id]['geometry']['type']);
        $this->assertSame('Alexandria', $rows[$withBorder->id]['name']['en']);
        $this->assertSame('الإسكندرية', $rows[$withBorder->id]['name']['ar']);
        $this->assertNull($rows[$without->id]['geometry']);
    }

    public function test_the_route_is_not_swallowed_by_the_show_route(): void
    {
        // `/admin/governorate/borders` must resolve to the borders feed, not to
        // `{governorate}` = "borders" (which would 404 on the model lookup).
        $this->actingAs($this->superAdmin())
            ->getJson('/admin/governorate/borders')
            ->assertOk()
            ->assertJsonStructure(['governorates']);
    }

    public function test_guests_and_users_without_a_governorate_permission_are_refused(): void
    {
        $this->getJson(route('admin.governorate.borders'))->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.governorate.borders'))
            ->assertForbidden();
    }

    public function test_it_lists_the_cities_of_one_governorate_with_their_borders(): void
    {
        $matrouh = Governorate::create(['name' => ['ar' => 'مطروح', 'en' => 'Marsa Matrouh']]);
        $other = Governorate::create(['name' => ['ar' => 'أسوان', 'en' => 'Aswan']]);

        $siwa = City::create(['governorate_id' => $matrouh->id, 'name' => ['ar' => 'سيوة', 'en' => 'Siwa']]);
        $noBorder = City::create(['governorate_id' => $matrouh->id, 'name' => ['ar' => 'الحمام', 'en' => 'El Hamam']]);
        City::create(['governorate_id' => $other->id, 'name' => ['ar' => 'أسوان', 'en' => 'Aswan City']]);

        DB::table('cities')->where('id', $siwa->id)->update([
            'boundary' => json_encode(['type' => 'Polygon', 'coordinates' => [[[25, 29], [26, 29], [26, 30], [25, 29]]]]),
        ]);

        $response = $this->actingAs($this->superAdmin())
            ->getJson(route('admin.governorate.city-borders', $matrouh->id))
            ->assertOk()
            ->assertJsonPath('governorate_id', $matrouh->id);

        $rows = collect($response->json('cities'))->keyBy('id');

        // Only this governorate's cities, none of Aswan's.
        $this->assertCount(2, $rows);
        $this->assertSame('Polygon', $rows[$siwa->id]['geometry']['type']);
        $this->assertSame('Siwa', $rows[$siwa->id]['name']['en']);
        $this->assertSame('سيوة', $rows[$siwa->id]['name']['ar']);
        $this->assertNull($rows[$noBorder->id]['geometry']);
    }

    public function test_a_governorate_with_no_cities_answers_an_empty_list(): void
    {
        $governorate = Governorate::create(['name' => ['ar' => 'حلوان', 'en' => 'Helwan']]);

        $this->actingAs($this->superAdmin())
            ->getJson(route('admin.governorate.city-borders', $governorate->id))
            ->assertOk()
            ->assertExactJson(['governorate_id' => $governorate->id, 'cities' => []]);
    }

    public function test_the_city_borders_feed_is_refused_without_a_governorate_permission(): void
    {
        $governorate = Governorate::create(['name' => ['ar' => 'أسوان', 'en' => 'Aswan']]);

        $this->getJson(route('admin.governorate.city-borders', $governorate->id))->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.governorate.city-borders', $governorate->id))
            ->assertForbidden();
    }
}
