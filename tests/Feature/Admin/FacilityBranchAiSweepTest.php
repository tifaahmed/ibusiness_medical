<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserPermissionEnum;
use App\Enums\User\UserRoleEnum;
use App\Models\City;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityBranchLog;
use App\Models\FacilityType;
use App\Models\Governorate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The two AI sweeps on the branch list: fill the governorate and city an
 * address names, and fill the coordinates it points at.
 *
 * The provider is always faked. What matters here is which branches each sweep
 * queues, what it refuses to overwrite, and that a spent quota pauses the run
 * instead of failing the rest of the list.
 */
class FacilityBranchAiSweepTest extends TestCase
{
    use RefreshDatabase;

    private Governorate $governorate;

    private City $city;

    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'test-key']);

        $this->governorate = Governorate::create(['name' => ['en' => 'Cairo', 'ar' => 'القاهرة']]);
        $this->city = City::create([
            'governorate_id' => $this->governorate->id,
            'name' => ['en' => 'Maadi', 'ar' => 'المعادي'],
        ]);

        $type = FacilityType::create(['name' => ['en' => 'Clinic', 'ar' => 'عيادة']]);
        $this->facility = Facility::create([
            'name' => ['en' => 'Mytra Labs', 'ar' => 'معامل ميترا'],
            'facility_type_id' => $type->id,
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web');
        $role->givePermissionTo(Permission::findOrCreate(UserPermissionEnum::MANAGE_FACILITY_BRANCHES, 'web'));
        $user->assignRole($role);

        return $user;
    }

    private function branch(array $attributes = []): FacilityBranch
    {
        static $n = 0;
        $n++;

        return FacilityBranch::create([
            'facility_id' => $this->facility->id,
            'name' => ['en' => "Branch {$n}", 'ar' => "فرع {$n}"],
            'address' => ['en' => "{$n} Nasr Street, Maadi", 'ar' => "{$n} شارع النصر، المعادي"],
            ...$attributes,
        ]);
    }

    private function fakeAi(array $payload): void
    {
        Http::fake([
            '*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode($payload)]]]]],
            ], 200),
        ]);
    }

    private function fakePlace(): void
    {
        $this->fakeAi([
            'governorate_id' => $this->governorate->id,
            'city_id' => $this->city->id,
            'confidence' => 'high',
            'matched_place' => 'Maadi, Cairo',
        ]);
    }

    private function fakeLocation(): void
    {
        $this->fakeAi([
            'latitude' => 29.9602,
            'longitude' => 31.2569,
            'confidence' => 'high',
            'matched_place' => 'Maadi, Cairo',
        ]);
    }

    /* ---- the place sweep ------------------------------------------------- */

    public function test_the_place_sweep_queues_only_branches_with_an_address_and_a_missing_field(): void
    {
        $missingBoth = $this->branch();
        $missingCity = $this->branch(['governorate_id' => $this->governorate->id]);
        $this->branch(['governorate_id' => $this->governorate->id, 'city_id' => $this->city->id]);
        $this->branch(['address' => null]);

        $response = $this->actingAs($this->admin())
            ->postJson(route('admin.facility-branch.place.bulk.begin'))
            ->assertOk();

        $this->assertSame(
            [$missingBoth->id, $missingCity->id],
            collect($response->json('branches'))->pluck('id')->all()
        );
        // The one with no address is counted, not silently dropped.
        $this->assertSame(1, $response->json('skipped_no_address'));
    }

    public function test_the_place_sweep_fills_the_empty_fields_and_logs_the_source(): void
    {
        $branch = $this->branch();
        $this->fakePlace();

        $this->actingAs($this->admin())
            ->postJson(route('admin.facility-branch.place.bulk.step'), ['ids' => [$branch->id]])
            ->assertOk()
            ->assertJsonPath('results.0.state', 'ok')
            ->assertJsonPath('results.0.detail', 'Cairo — Maadi');

        $branch->refresh();
        $this->assertSame($this->governorate->id, $branch->governorate_id);
        $this->assertSame($this->city->id, $branch->city_id);

        $log = FacilityBranchLog::where('facility_branch_id', $branch->id)->latest('id')->first();
        $this->assertSame('ai_place', $log->new_values['source']);
    }

    public function test_the_place_sweep_does_not_overwrite_a_place_set_by_hand(): void
    {
        $other = Governorate::create(['name' => ['en' => 'Giza', 'ar' => 'الجيزة']]);
        $branch = $this->branch(['governorate_id' => $other->id]);
        $this->fakePlace();

        $this->actingAs($this->admin())
            ->postJson(route('admin.facility-branch.place.bulk.step'), ['ids' => [$branch->id]])
            ->assertOk()
            ->assertJsonPath('results.0.state', 'ok');

        $branch->refresh();
        // The governorate somebody chose stands; only the empty city is filled.
        $this->assertSame($other->id, $branch->governorate_id);
        $this->assertSame($this->city->id, $branch->city_id);
    }

    public function test_redo_mode_replaces_a_place_that_is_already_set(): void
    {
        $other = Governorate::create(['name' => ['en' => 'Giza', 'ar' => 'الجيزة']]);
        $branch = $this->branch(['governorate_id' => $other->id]);
        $this->fakePlace();

        $this->actingAs($this->admin())
            ->postJson(route('admin.facility-branch.place.bulk.step'), ['ids' => [$branch->id], 'mode' => 'all'])
            ->assertOk();

        $this->assertSame($this->governorate->id, $branch->refresh()->governorate_id);
    }

    public function test_a_slug_is_not_regenerated_by_a_sweep(): void
    {
        $branch = $this->branch();
        $slug = $branch->slug;
        $this->fakePlace();

        $this->actingAs($this->admin())
            ->postJson(route('admin.facility-branch.place.bulk.step'), ['ids' => [$branch->id]])
            ->assertOk();

        // Filling in a place has no business renaming a branch — a changed slug
        // would break every link already pointing at it.
        $this->assertSame($slug, $branch->refresh()->slug);
    }

    /* ---- the GPS sweep --------------------------------------------------- */

    public function test_the_gps_sweep_queues_branches_missing_coordinates_or_a_link(): void
    {
        $noCoordinates = $this->branch();
        $noLink = $this->branch(['latitude' => 30.1, 'longitude' => 31.1]);
        $this->branch([
            'latitude' => 30.2,
            'longitude' => 31.2,
            'google_location_url' => 'https://maps.app.goo.gl/abc',
        ]);

        $response = $this->actingAs($this->admin())
            ->postJson(route('admin.facility-branch.location.bulk.begin'))
            ->assertOk();

        $this->assertSame(
            [$noCoordinates->id, $noLink->id],
            collect($response->json('branches'))->pluck('id')->all()
        );
    }

    public function test_the_gps_sweep_writes_coordinates_and_builds_the_map_link(): void
    {
        $branch = $this->branch();
        $this->fakeLocation();

        $this->actingAs($this->admin())
            ->postJson(route('admin.facility-branch.location.bulk.step'), ['ids' => [$branch->id]])
            ->assertOk()
            ->assertJsonPath('results.0.state', 'ok')
            ->assertJsonPath('results.0.confidence', 'high');

        $branch->refresh();
        $this->assertSame('29.9602000', $branch->latitude);
        $this->assertSame(
            'https://www.google.com/maps/search/?api=1&query=29.960200,31.256900',
            $branch->google_location_url
        );
    }

    public function test_the_gps_sweep_keeps_coordinates_that_were_set_by_hand(): void
    {
        $branch = $this->branch(['latitude' => 30.5, 'longitude' => 31.5]);
        $this->fakeLocation();

        $this->actingAs($this->admin())
            ->postJson(route('admin.facility-branch.location.bulk.step'), ['ids' => [$branch->id]])
            ->assertOk();

        $branch->refresh();
        // Only the missing link is built; a pin somebody checked is not moved.
        $this->assertSame('30.5000000', $branch->latitude);
        $this->assertSame(
            'https://www.google.com/maps/search/?api=1&query=30.500000,31.500000',
            $branch->google_location_url
        );
    }

    /* ---- shared behaviour ------------------------------------------------ */

    public function test_a_spent_quota_reports_the_slice_as_unprocessed(): void
    {
        $branch = $this->branch();
        Http::fake(['*' => Http::response(['error' => ['message' => 'quota']], 429)]);

        $response = $this->actingAs($this->admin())
            ->postJson(route('admin.facility-branch.place.bulk.step'), ['ids' => [$branch->id]])
            ->assertOk();

        // Nothing written and no result rows: the browser waits out the quota
        // and sends the same slice again, so no branch is skipped.
        $this->assertTrue($response->json('rate_limited'));
        $this->assertSame([], $response->json('results'));
        $this->assertNull($branch->refresh()->governorate_id);
    }

    public function test_an_address_the_model_cannot_place_is_reported_not_written(): void
    {
        $branch = $this->branch();
        $this->fakeAi(['governorate_id' => null, 'city_id' => null, 'confidence' => 'low']);

        $this->actingAs($this->admin())
            ->postJson(route('admin.facility-branch.place.bulk.step'), ['ids' => [$branch->id]])
            ->assertOk()
            ->assertJsonPath('results.0.state', 'not_found');

        $this->assertNull($branch->refresh()->governorate_id);
    }

    public function test_a_step_is_capped_so_one_request_cannot_run_the_whole_list(): void
    {
        $ids = collect(range(1, 5))->map(fn () => $this->branch()->id)->all();

        $this->actingAs($this->admin())
            ->postJson(route('admin.facility-branch.place.bulk.step'), ['ids' => $ids])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ids');
    }

    public function test_an_admin_scoped_to_their_own_branches_sweeps_only_those(): void
    {
        $mine = $this->admin();
        $theirs = User::factory()->create();

        $ownRole = Role::findOrCreate('branch-owner', 'web');
        $ownRole->givePermissionTo(Permission::findOrCreate(UserPermissionEnum::MANAGE_OWN_FACILITY_BRANCHES, 'web'));
        $scoped = User::factory()->create();
        $scoped->assignRole($ownRole);

        $ownBranch = $this->branch(['created_by' => $scoped->id]);
        $this->branch(['created_by' => $theirs->id]);

        $response = $this->actingAs($scoped)
            ->postJson(route('admin.facility-branch.place.bulk.begin'))
            ->assertOk();

        $this->assertSame([$ownBranch->id], collect($response->json('branches'))->pluck('id')->all());
        $this->assertNotNull($mine);
    }
}
