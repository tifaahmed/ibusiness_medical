<?php

namespace Tests\Feature\Api;

use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `/api/v1/facilities?lat=&lng=&radius_km=`: "within X km of me".
 *
 * Distance is measured to a facility's NEAREST branch — see
 * `PartnersController::nearestBranchKm()` — and, unlike the id filters
 * (`governorate_id`, `city_id`, `facility_type_id`), it is a real database
 * filter rather than something narrowed after the fact, so a radius has to
 * actually shrink what a page of results can contain.
 *
 * Read by the Deilar storefront's directory; distance itself (`distance_km`)
 * rides along on every facility whenever `lat`/`lng` are sent, radius or not —
 * that is what lets the storefront sort a page by "closest first" without a
 * second round trip. See `App\Actions\Facilities\ListFacilities` over there.
 */
class GuestFacilityRadiusFilterTest extends TestCase
{
    use RefreshDatabase;

    private FacilityType $clinic;

    /** Roughly Cairo. Every distance below is measured from here. */
    private const LAT = 30.05;

    private const LNG = 31.20;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = FacilityType::create(['name' => ['en' => 'Clinic', 'ar' => 'عيادة']]);
    }

    public function test_a_facility_outside_the_radius_is_left_out(): void
    {
        $near = $this->facilityWithBranch('Nile Clinic', self::LAT + 0.01, self::LNG + 0.01);
        $far = $this->facilityWithBranch('Alexandria Clinic', 31.20, 29.92);

        $ids = $this->search(['lat' => self::LAT, 'lng' => self::LNG, 'radius_km' => 10])
            ->pluck('id')->all();

        $this->assertContains($near->id, $ids);
        $this->assertNotContains($far->id, $ids);
    }

    public function test_a_wider_radius_reaches_further(): void
    {
        /* ~67km from the centre: outside a 10km radius, inside a 100km one. */
        $facility = $this->facilityWithBranch('Suez Road Clinic', self::LAT + 0.6, self::LNG);

        $this->assertNotContains(
            $facility->id,
            $this->search(['lat' => self::LAT, 'lng' => self::LNG, 'radius_km' => 10])->pluck('id')->all(),
        );
        $this->assertContains(
            $facility->id,
            $this->search(['lat' => self::LAT, 'lng' => self::LNG, 'radius_km' => 100])->pluck('id')->all(),
        );
    }

    public function test_a_facility_with_no_geocoded_branch_never_matches_a_radius(): void
    {
        $facility = Facility::create([
            'name' => ['en' => 'HQ Only Clinic', 'ar' => 'عيادة بلا فروع'],
            'facility_type_id' => $this->clinic->id,
        ]);

        $ids = $this->search(['lat' => self::LAT, 'lng' => self::LNG, 'radius_km' => 100])
            ->pluck('id')->all();

        $this->assertNotContains($facility->id, $ids);
    }

    public function test_distance_rides_along_without_a_radius_at_all(): void
    {
        /* A non-zero offset: PHP's `json_encode` prints a float `0.0` as a
           bare `0`, which would make this assert a JSON quirk rather than
           the distance calculation. */
        $this->facilityWithBranch('Nile Clinic', self::LAT + 0.01, self::LNG + 0.01);

        $distance = $this->search(['lat' => self::LAT, 'lng' => self::LNG])
            ->first()['distance_km'];

        $this->assertIsFloat($distance);
        $this->assertEqualsWithDelta(1.5, $distance, 0.2);
    }

    public function test_distance_is_absent_without_coordinates_at_all(): void
    {
        $this->facilityWithBranch('Nile Clinic', self::LAT, self::LNG);

        $this->assertArrayNotHasKey('distance_km', $this->search([])->first());
    }

    public function test_radius_needs_both_coordinates(): void
    {
        $this->getJson('/api/v1/facilities?radius_km=10')->assertStatus(422);
    }

    public function test_radius_is_limited_to_the_offered_steps(): void
    {
        $this->getJson('/api/v1/facilities?lat='.self::LAT.'&lng='.self::LNG.'&radius_km=15')
            ->assertStatus(422);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function search(array $query): \Illuminate\Support\Collection
    {
        return collect(
            $this->getJson('/api/v1/facilities?'.http_build_query($query))
                ->assertOk()
                ->json('facilities.data')
        );
    }

    private function facilityWithBranch(string $name, float $lat, float $lng): Facility
    {
        $facility = Facility::create([
            'name' => ['en' => $name, 'ar' => $name],
            'facility_type_id' => $this->clinic->id,
        ]);

        FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'Main', 'ar' => 'الرئيسي'],
            'phone' => ['0100000000'],
            'latitude' => $lat,
            'longitude' => $lng,
        ]);

        return $facility;
    }
}
