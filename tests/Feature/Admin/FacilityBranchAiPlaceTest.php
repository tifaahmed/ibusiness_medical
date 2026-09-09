<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\City;
use App\Models\Governorate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * "Fill governorate & city with AI": the button reads the written address and
 * chooses the two rows it belongs to.
 *
 * The provider is always faked — these assert what the application does with an
 * answer, and above all which answers it refuses to hand back. The model is
 * given real ids to choose from, so anything outside that list, or a city that
 * does not sit in the governorate it named, is a hallucination to discard.
 */
class FacilityBranchAiPlaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'test-key']);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web');
        $role->givePermissionTo(Permission::findOrCreate('manage facility branches', 'web'));
        $user->assignRole($role);

        return $user;
    }

    /** @return array{cairo: Governorate, alex: Governorate, maadi: City, gaber: City} */
    private function places(): array
    {
        $cairo = Governorate::create(['name' => ['en' => 'Cairo', 'ar' => 'القاهرة']]);
        $alex = Governorate::create(['name' => ['en' => 'Alexandria', 'ar' => 'الإسكندرية']]);

        return [
            'cairo' => $cairo,
            'alex' => $alex,
            'maadi' => City::create(['governorate_id' => $cairo->id, 'name' => ['en' => 'Maadi', 'ar' => 'المعادي']]),
            'gaber' => City::create(['governorate_id' => $alex->id, 'name' => ['en' => 'Sidi Gaber', 'ar' => 'سيدي جابر']]),
        ];
    }

    private function fakeAi(array $payload): void
    {
        Http::fake([
            '*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode($payload)]]]]],
            ], 200),
        ]);
    }

    private function ask(array $body = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin())->postJson(route('admin.facility.branch.place'), [
            'address' => ['ar' => '12 شارع النصر، المعادي', 'en' => '12 Nasr Street, Maadi'],
            ...$body,
        ]);
    }

    public function test_the_button_answers_the_governorate_and_city_the_address_names(): void
    {
        $places = $this->places();
        $this->fakeAi([
            'governorate_id' => $places['cairo']->id,
            'city_id' => $places['maadi']->id,
            'confidence' => 'high',
            'matched_place' => 'Maadi, Cairo',
        ]);

        $this->ask()
            ->assertOk()
            ->assertJsonPath('place.governorate_id', $places['cairo']->id)
            ->assertJsonPath('place.city_id', $places['maadi']->id)
            ->assertJsonPath('place.confidence', 'high')
            ->assertJsonPath('place.city_name.en', 'Maadi');
    }

    public function test_a_city_that_does_not_exist_is_discarded_rather_than_returned(): void
    {
        $places = $this->places();
        $this->fakeAi([
            'governorate_id' => $places['cairo']->id,
            'city_id' => 99999,
            'confidence' => 'high',
        ]);

        $this->ask()
            ->assertOk()
            ->assertJsonPath('place.governorate_id', $places['cairo']->id)
            ->assertJsonPath('place.city_id', null);
    }

    public function test_a_city_from_another_governorate_corrects_the_governorate(): void
    {
        $places = $this->places();

        // The city is the more specific answer, so a self-contradicting pair is
        // resolved in its favour rather than thrown away whole.
        $this->fakeAi([
            'governorate_id' => $places['cairo']->id,
            'city_id' => $places['gaber']->id,
            'confidence' => 'medium',
        ]);

        $this->ask()
            ->assertOk()
            ->assertJsonPath('place.city_id', $places['gaber']->id)
            ->assertJsonPath('place.governorate_id', $places['alex']->id);
    }

    public function test_an_id_written_with_its_prefix_is_still_read(): void
    {
        $places = $this->places();
        $this->fakeAi([
            'governorate_id' => 'G'.$places['cairo']->id,
            'city_id' => 'C'.$places['maadi']->id,
            'confidence' => 'high',
        ]);

        $this->ask()
            ->assertOk()
            ->assertJsonPath('place.city_id', $places['maadi']->id);
    }

    public function test_an_answer_with_no_place_at_all_is_reported_not_returned(): void
    {
        $this->places();
        $this->fakeAi(['governorate_id' => null, 'city_id' => null, 'confidence' => 'low']);

        $this->ask()->assertStatus(422);
    }

    public function test_an_invented_governorate_is_not_handed_back(): void
    {
        $this->places();
        $this->fakeAi(['governorate_id' => 4242, 'city_id' => null, 'confidence' => 'high']);

        $this->ask()->assertStatus(422);
    }

    public function test_an_empty_address_is_refused_before_the_provider_is_called(): void
    {
        $this->places();
        Http::fake();

        $this->actingAs($this->admin())
            ->postJson(route('admin.facility.branch.place'), ['address' => ['ar' => '', 'en' => '']])
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_a_missing_key_is_reported_as_something_to_go_and_fix(): void
    {
        $this->places();
        config(['services.gemini.key' => null]);

        $this->ask()
            ->assertStatus(422)
            ->assertJsonPath('message', 'GEMINI_API_KEY is not set. Add it to your .env file to use AI generation.');
    }

    public function test_a_branch_manager_may_use_it_without_the_facility_permission(): void
    {
        $places = $this->places();
        $this->fakeAi([
            'governorate_id' => $places['cairo']->id,
            'city_id' => $places['maadi']->id,
            'confidence' => 'high',
        ]);

        // The button lives on the standalone branch form as well as the facility
        // one, so "manage facility branches" alone has to be enough to reach it.
        $this->ask()->assertOk();
    }
}
