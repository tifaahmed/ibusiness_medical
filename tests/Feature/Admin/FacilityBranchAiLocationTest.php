<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityBranchLog;
use App\Models\FacilityType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * AI geocoding: the "Find on map with AI" button in the branch modal and the
 * "Fill locations with AI" sweep on the facility list.
 *
 * The provider is always faked — these assert what the application does with an
 * answer, including the answers it must refuse to write.
 */
class FacilityBranchAiLocationTest extends TestCase
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
        $role->givePermissionTo(Permission::findOrCreate('manage facilities', 'web'));
        $user->assignRole($role);

        return $user;
    }

    private function facility(): Facility
    {
        $type = FacilityType::create(['name' => ['en' => 'Clinic', 'ar' => 'عيادة']]);

        return Facility::create([
            'name' => ['en' => 'Mytra Labs', 'ar' => 'معامل ميترا'],
            'facility_type_id' => $type->id,
        ]);
    }

    /**
     * Fake one Gemini answer, in the envelope GeminiClient reads.
     */
    private function fakeAi(array $payload): void
    {
        Http::fake([
            '*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode($payload)]]]]],
            ], 200),
        ]);
    }

    public function test_the_branch_modal_button_returns_coordinates_and_a_maps_link(): void
    {
        $this->fakeAi([
            'latitude' => 30.0444196,
            'longitude' => 31.2357116,
            'confidence' => 'high',
            'matched_place' => 'Tahrir Square, Cairo',
        ]);

        $response = $this->actingAs($this->admin())->postJson(route('admin.facility.branch.locate'), [
            'address' => ['ar' => 'ميدان التحرير، وسط البلد', 'en' => 'Tahrir Square, Downtown'],
            'city' => 'Cairo',
        ]);

        $response->assertOk()
            ->assertJsonPath('location.latitude', 30.04442)
            ->assertJsonPath('location.confidence', 'high')
            ->assertJsonPath(
                'location.google_location_url',
                'https://www.google.com/maps/search/?api=1&query=30.044420,31.235712'
            );
    }

    public function test_an_address_the_model_cannot_place_is_reported_rather_than_stored(): void
    {
        $this->fakeAi(['latitude' => null, 'longitude' => null, 'confidence' => 'low']);

        $this->actingAs($this->admin())
            ->postJson(route('admin.facility.branch.locate'), ['address' => ['en' => 'somewhere']])
            ->assertStatus(422);
    }

    public function test_the_button_refuses_an_empty_address(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('admin.facility.branch.locate'), ['address' => ['ar' => '  ', 'en' => '']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('address');
    }

    public function test_the_sweep_lists_only_branches_missing_a_location(): void
    {
        $admin = $this->admin();
        $facility = $this->facility();

        $missing = FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'Downtown', 'ar' => 'وسط البلد'],
            'address' => ['en' => 'Tahrir Square', 'ar' => 'ميدان التحرير'],
        ]);

        FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'Maadi', 'ar' => 'المعادي'],
            'address' => ['en' => 'Road 9', 'ar' => 'شارع 9'],
            'latitude' => 29.9601,
            'longitude' => 31.2569,
            'google_location_url' => 'https://maps.app.goo.gl/set-by-hand',
        ]);

        // No address, city or governorate: an AI call could only guess.
        FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'Nameless', 'ar' => 'بدون'],
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.facility.location.bulk.begin'), ['mode' => 'missing']);

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('branches.0.id', $missing->id)
            ->assertJsonPath('skipped_no_address', 1);
    }

    public function test_a_step_writes_the_location_and_logs_it(): void
    {
        $admin = $this->admin();
        $facility = $this->facility();

        $branch = FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'Downtown', 'ar' => 'وسط البلد'],
            'address' => ['en' => 'Tahrir Square', 'ar' => 'ميدان التحرير'],
        ]);

        $this->fakeAi([
            'latitude' => 30.0444,
            'longitude' => 31.2357,
            'confidence' => 'medium',
            'matched_place' => 'Downtown Cairo',
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.facility.location.bulk.step'), ['ids' => [$branch->id], 'mode' => 'missing'])
            ->assertOk()
            ->assertJsonPath('results.0.state', 'ok')
            ->assertJsonPath('results.0.confidence', 'medium');

        $branch->refresh();

        $this->assertSame('30.0444000', $branch->latitude);
        $this->assertSame('31.2357000', $branch->longitude);
        $this->assertSame(
            'https://www.google.com/maps/search/?api=1&query=30.044400,31.235700',
            $branch->google_location_url
        );
        $this->assertDatabaseHas('facility_branch_logs', [
            'facility_branch_id' => $branch->id,
            'action' => FacilityBranchLog::ACTION_UPDATED,
        ]);
    }

    public function test_missing_mode_never_overwrites_coordinates_set_by_hand(): void
    {
        $admin = $this->admin();
        $facility = $this->facility();

        $branch = FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'Maadi', 'ar' => 'المعادي'],
            'address' => ['en' => 'Road 9', 'ar' => 'شارع 9'],
            'latitude' => 29.9601,
            'longitude' => 31.2569,
        ]);

        $this->fakeAi(['latitude' => 30.0444, 'longitude' => 31.2357, 'confidence' => 'high']);

        $this->actingAs($admin)
            ->postJson(route('admin.facility.location.bulk.step'), ['ids' => [$branch->id], 'mode' => 'missing'])
            ->assertOk();

        $branch->refresh();

        // The hand-set pin stands; only the missing link was filled, and from
        // the coordinates the branch actually has.
        $this->assertSame('29.9601000', $branch->latitude);
        $this->assertSame(
            'https://www.google.com/maps/search/?api=1&query=29.960100,31.256900',
            $branch->google_location_url
        );
    }

    public function test_a_nonsense_answer_is_not_written_to_the_branch(): void
    {
        $admin = $this->admin();
        $facility = $this->facility();

        $branch = FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'Downtown', 'ar' => 'وسط البلد'],
            'address' => ['en' => 'Tahrir Square', 'ar' => 'ميدان التحرير'],
        ]);

        // Null Island, and a latitude that is not a latitude.
        $this->fakeAi(['latitude' => 0, 'longitude' => 0, 'confidence' => 'high']);

        $this->actingAs($admin)
            ->postJson(route('admin.facility.location.bulk.step'), ['ids' => [$branch->id], 'mode' => 'missing'])
            ->assertOk()
            ->assertJsonPath('results.0.state', 'not_found');

        $branch->refresh();

        $this->assertNull($branch->latitude);
        $this->assertNull($branch->google_location_url);
    }
}
