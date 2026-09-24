<?php

namespace Tests\Feature\Api;

use App\Models\City;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityType;
use App\Models\Governorate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `/api/v1/facilities/branches/map`: every branch's pin, merged by proximity.
 *
 * Read by the Deilar storefront's "view all branches" map — see
 * `App\Actions\Facilities\ClusterFacilityBranches`. The tests here are mostly
 * about the one thing that endpoint exists for: two branches close together
 * merge into one pin carrying a count, and two branches far apart do not.
 */
class GuestFacilityBranchMapTest extends TestCase
{
    use RefreshDatabase;

    private Governorate $cairo;

    private City $nasrCity;

    private FacilityType $clinic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cairo = Governorate::create(['name' => ['en' => 'Cairo', 'ar' => 'القاهرة']]);
        $this->nasrCity = City::create(['governorate_id' => $this->cairo->id, 'name' => ['en' => 'Nasr City', 'ar' => 'مدينة نصر']]);
        $this->clinic = FacilityType::create(['name' => ['en' => 'Clinic', 'ar' => 'عيادة']]);
    }

    public function test_branches_at_almost_the_same_point_merge_into_one_pin(): void
    {
        $facility = $this->facility('Nile Clinic');
        $this->branch($facility, 30.0500001, 31.2000001);
        $this->branch($facility, 30.0500002, 31.2000002);

        $clusters = $this->map(['zoom' => 14]);

        $this->assertCount(1, $clusters);
        $this->assertSame(2, $clusters[0]['count']);
    }

    public function test_branches_far_apart_stay_as_separate_pins(): void
    {
        $facility = $this->facility('Nile Clinic');
        $this->branch($facility, 30.05, 31.20);
        $this->branch($facility, 31.20, 29.92);

        $clusters = $this->map(['zoom' => 14]);

        $this->assertCount(2, $clusters);
        $this->assertSame([1, 1], array_column($clusters, 'count'));
    }

    public function test_zooming_out_merges_branches_that_zooming_in_kept_apart(): void
    {
        $facility = $this->facility('Nile Clinic');
        // About 300m apart — merges at a city-wide zoom, not at street level.
        $this->branch($facility, 30.0500, 31.2000);
        $this->branch($facility, 30.0530, 31.2000);

        $streetLevel = $this->map(['zoom' => 16]);
        $this->assertCount(2, $streetLevel);

        $cityLevel = $this->map(['zoom' => 8]);
        $this->assertCount(1, $cityLevel);
        $this->assertSame(2, $cityLevel[0]['count']);
    }

    public function test_a_merged_pin_still_names_one_branch_for_its_popup(): void
    {
        $facility = $this->facility('Nile Clinic');
        $this->branch($facility, 30.0500001, 31.2000001);
        $this->branch($facility, 30.0500002, 31.2000002);

        $clusters = $this->map(['zoom' => 14]);

        $this->assertSame('Nile Clinic', $clusters[0]['branch']['facility']['name']);
        $this->assertSame($facility->slug, $clusters[0]['branch']['facility']['slug']);
    }

    public function test_a_branch_with_no_coordinates_is_left_off_the_map(): void
    {
        $facility = $this->facility('Nile Clinic');
        FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'HQ', 'ar' => 'المقر'],
            'phone' => ['0100000000'],
        ]);

        $this->assertSame([], $this->map(['zoom' => 14]));
    }

    public function test_the_governorate_filter_narrows_the_map(): void
    {
        $other = Governorate::create(['name' => ['en' => 'Giza', 'ar' => 'الجيزة']]);

        $facility = $this->facility('Nile Clinic');
        $this->branch($facility, 30.05, 31.20);
        $this->branch($facility, 30.01, 31.21, $other->id);

        $clusters = $this->map(['zoom' => 14, 'governorate_id' => $this->cairo->id]);

        $this->assertCount(1, $clusters);
    }

    public function test_zoom_cannot_be_pushed_past_the_ceiling(): void
    {
        $this->getJson('/api/v1/facilities/branches/map?zoom=20')->assertStatus(422);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function map(array $query): array
    {
        return $this->getJson('/api/v1/facilities/branches/map?'.http_build_query($query))
            ->assertOk()
            ->json('clusters');
    }

    private function facility(string $name): Facility
    {
        return Facility::create([
            'name' => ['en' => $name, 'ar' => $name],
            'facility_type_id' => $this->clinic->id,
        ]);
    }

    private function branch(Facility $facility, float $lat, float $lng, ?int $governorateId = null): FacilityBranch
    {
        return FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'Main', 'ar' => 'الرئيسي'],
            'phone' => ['0100000000'],
            'governorate_id' => $governorateId ?? $this->cairo->id,
            'city_id' => $this->nasrCity->id,
            'latitude' => $lat,
            'longitude' => $lng,
        ]);
    }
}
