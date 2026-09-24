<?php

namespace Tests\Feature\Api;

use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `/api/v1/facilities/branches/locations`: every geocoded branch's raw point,
 * paginated — read once by the Deilar storefront's "view all branches" map
 * and clustered in the browser afterwards, rather than asked again per pan or
 * zoom. See `App\Http\Controllers\Api\V1\Guest\FacilityBranchLocationsController`.
 */
class GuestFacilityBranchLocationsTest extends TestCase
{
    use RefreshDatabase;

    private FacilityType $clinic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = FacilityType::create(['name' => ['en' => 'Clinic', 'ar' => 'عيادة']]);
    }

    public function test_every_geocoded_branch_is_listed(): void
    {
        $facility = $this->facility('Nile Clinic');
        $this->branch($facility, 30.05, 31.20);
        $this->branch($facility, 31.20, 29.92);

        $response = $this->getJson('/api/v1/facilities/branches/locations')->assertOk();

        $this->assertCount(2, $response->json('data'));
        $this->assertSame(2, $response->json('meta.total'));
    }

    public function test_a_branch_with_no_coordinates_is_left_out(): void
    {
        $facility = $this->facility('Nile Clinic');
        FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'HQ', 'ar' => 'المقر'],
            'phone' => ['0100000000'],
        ]);

        $this->assertSame([], $this->getJson('/api/v1/facilities/branches/locations')->json('data'));
    }

    public function test_results_are_ordered_nearest_first_when_a_point_is_given(): void
    {
        $facility = $this->facility('Nile Clinic');
        $far = $this->branch($facility, 31.20, 29.92, 'Far');
        $near = $this->branch($facility, 30.06, 31.21, 'Near');

        $ids = collect(
            $this->getJson('/api/v1/facilities/branches/locations?lat=30.05&lng=31.20')
                ->assertOk()
                ->json('data')
        )->pluck('id')->all();

        $this->assertSame([$near->id, $far->id], $ids);
    }

    public function test_results_are_paginated(): void
    {
        $facility = $this->facility('Nile Clinic');

        foreach (range(1, 3) as $i) {
            $this->branch($facility, 30.0 + $i * 0.01, 31.2);
        }

        $response = $this->getJson('/api/v1/facilities/branches/locations?per_page=2')->assertOk();

        $this->assertCount(2, $response->json('data'));
        $this->assertSame(3, $response->json('meta.total'));
        $this->assertSame(2, $response->json('meta.last_page'));
    }

    public function test_per_page_cannot_be_pushed_past_the_ceiling(): void
    {
        $this->getJson('/api/v1/facilities/branches/locations?per_page=501')->assertStatus(422);
    }

    public function test_radius_narrows_the_points_the_same_way_the_directory_does(): void
    {
        $facility = $this->facility('Nile Clinic');
        $near = $this->branch($facility, 30.06, 31.21, 'Near');
        $far = $this->branch($facility, 31.20, 29.92, 'Far');

        $ids = collect(
            $this->getJson('/api/v1/facilities/branches/locations?lat=30.05&lng=31.20&radius_km=10')
                ->assertOk()
                ->json('data')
        )->pluck('id')->all();

        $this->assertSame([$near->id], $ids);
        $this->assertNotContains($far->id, $ids);
    }

    public function test_radius_needs_both_coordinates(): void
    {
        $this->getJson('/api/v1/facilities/branches/locations?radius_km=10')->assertStatus(422);
    }

    private function facility(string $name): Facility
    {
        return Facility::create([
            'name' => ['en' => $name, 'ar' => $name],
            'facility_type_id' => $this->clinic->id,
        ]);
    }

    private function branch(Facility $facility, float $lat, float $lng, string $name = 'Main'): FacilityBranch
    {
        return FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => $name, 'ar' => $name],
            'phone' => ['0100000000'],
            'latitude' => $lat,
            'longitude' => $lng,
        ]);
    }
}
