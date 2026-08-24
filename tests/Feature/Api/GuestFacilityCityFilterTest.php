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
 * `/api/v1/facilities` narrowed to one city.
 *
 * A facility belongs to a place twice over: its head office sits somewhere, and
 * so does every branch. A visitor picking a city means "somewhere I can walk
 * into", so either counts — which is the one thing worth pinning down here,
 * along with the city list the dropdown itself is drawn from.
 *
 * Read by the Deilar storefront's directory; see `App\Actions\Facilities\
 * ListFacilities` over there.
 */
class GuestFacilityCityFilterTest extends TestCase
{
    use RefreshDatabase;

    private Governorate $cairo;

    private Governorate $giza;

    private City $nasrCity;

    private City $maadi;

    private City $dokki;

    private FacilityType $clinic;

    private FacilityType $pharmacy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cairo = Governorate::create(['name' => ['en' => 'Cairo', 'ar' => 'القاهرة']]);
        $this->giza = Governorate::create(['name' => ['en' => 'Giza', 'ar' => 'الجيزة']]);

        $this->nasrCity = $this->city($this->cairo, 'Nasr City', 'مدينة نصر');
        $this->maadi = $this->city($this->cairo, 'Maadi', 'المعادي');
        $this->dokki = $this->city($this->giza, 'Dokki', 'الدقي');

        $this->clinic = FacilityType::create(['name' => ['en' => 'Clinic', 'ar' => 'عيادة']]);
        $this->pharmacy = FacilityType::create(['name' => ['en' => 'Pharmacy', 'ar' => 'صيدلية']]);
    }

    public function test_a_city_narrows_the_directory_to_what_is_actually_in_it(): void
    {
        $inNasrCity = $this->facility('Nile Clinic', $this->clinic, $this->cairo, $this->nasrCity);
        $inMaadi = $this->facility('Maadi Clinic', $this->clinic, $this->cairo, $this->maadi);

        $response = $this->getJson('/api/v1/facilities?city_id='.$this->nasrCity->id)
            ->assertOk();

        $ids = collect($response->json('facilities.data'))->pluck('id')->all();

        $this->assertContains($inNasrCity->id, $ids);
        $this->assertNotContains($inMaadi->id, $ids);
    }

    /*
     * A chain's head office is registered in one city and it has a branch in
     * another. Filtering by the branch's city has to find it, or the directory
     * only ever answers "where is this company registered" rather than "where
     * can I use my card".
     */
    public function test_a_branch_in_the_city_is_enough_to_be_listed(): void
    {
        $facility = $this->facility('Delta Labs', $this->clinic, $this->cairo, $this->nasrCity);

        FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'Dokki Branch', 'ar' => 'فرع الدقي'],
            'address' => ['en' => '5 Tahrir St', 'ar' => '٥ شارع التحرير'],
            'phone' => ['0100000000'],
            'governorate_id' => $this->giza->id,
            'city_id' => $this->dokki->id,
        ]);

        $ids = collect(
            $this->getJson('/api/v1/facilities?city_id='.$this->dokki->id)
                ->assertOk()
                ->json('facilities.data')
        )->pluck('id')->all();

        $this->assertContains($facility->id, $ids);
    }

    /*
     * The card leads with a branch, so the branch in the city being filtered on
     * has to be the one it leads with — otherwise a search for Dokki shows a
     * card whose address is in Nasr City.
     */
    public function test_the_matching_branch_is_ordered_first_on_the_card(): void
    {
        $facility = $this->facility('Delta Labs', $this->clinic, $this->cairo, $this->nasrCity);

        $nasr = FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'Nasr City Branch', 'ar' => 'فرع مدينة نصر'],
            'phone' => ['0100000000'],
            'governorate_id' => $this->cairo->id,
            'city_id' => $this->nasrCity->id,
        ]);

        $dokki = FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'Dokki Branch', 'ar' => 'فرع الدقي'],
            'phone' => ['0100000001'],
            'governorate_id' => $this->giza->id,
            'city_id' => $this->dokki->id,
        ]);

        $this->assertLessThan($dokki->id, $nasr->id, 'The natural order has to be the wrong one for this to prove anything.');

        $branches = $this->getJson('/api/v1/facilities?city_id='.$this->dokki->id)
            ->assertOk()
            ->json('facilities.data.0.branches');

        $this->assertSame($dokki->id, $branches[0]['id']);
    }

    /*
     * The dropdown is drawn from this list, so a city nobody is listed in is a
     * choice whose only outcome is an empty grid.
     */
    public function test_only_cities_hosting_something_are_offered(): void
    {
        $this->facility('Nile Clinic', $this->clinic, $this->cairo, $this->nasrCity);

        $names = collect($this->getJson('/api/v1/facilities')->assertOk()->json('cities'))
            ->pluck('name')
            ->all();

        $this->assertSame(['Nasr City'], $names);
    }

    public function test_the_city_list_narrows_to_the_chosen_governorate(): void
    {
        $this->facility('Nile Clinic', $this->clinic, $this->cairo, $this->nasrCity);
        $this->facility('Dokki Clinic', $this->clinic, $this->giza, $this->dokki);

        $names = collect(
            $this->getJson('/api/v1/facilities?governorate_id='.$this->cairo->id)
                ->assertOk()
                ->json('cities')
        )->pluck('name')->all();

        $this->assertSame(['Nasr City'], $names);
    }

    /*
     * The types are narrowed the same way the cities are, and a city is the
     * narrower of the two — offering "Pharmacy" in a city that has none is a
     * dropdown entry that can only return nothing.
     */
    public function test_the_facility_types_narrow_to_the_chosen_city(): void
    {
        $this->facility('Nile Clinic', $this->clinic, $this->cairo, $this->nasrCity);
        $this->facility('Maadi Pharmacy', $this->pharmacy, $this->cairo, $this->maadi);

        $names = collect(
            $this->getJson('/api/v1/facilities?governorate_id='.$this->cairo->id.'&city_id='.$this->nasrCity->id)
                ->assertOk()
                ->json('facility_types')
        )->pluck('name')->all();

        $this->assertSame(['Clinic'], $names);
    }

    /*
     * `X-Locale` is the only thing the storefront sends, and the names are what
     * end up on a visitor's screen.
     */
    public function test_the_city_names_come_back_in_the_requested_locale(): void
    {
        $this->facility('Nile Clinic', $this->clinic, $this->cairo, $this->nasrCity);

        $names = collect(
            $this->getJson('/api/v1/facilities', ['X-Locale' => 'ar'])
                ->assertOk()
                ->json('cities')
        )->pluck('name')->all();

        $this->assertSame(['مدينة نصر'], $names);
    }

    public function test_the_applied_city_is_echoed_back_in_the_filters(): void
    {
        $this->getJson('/api/v1/facilities?city_id='.$this->maadi->id)
            ->assertOk()
            ->assertJsonPath('filters.city_id', (string) $this->maadi->id);
    }

    private function city(Governorate $governorate, string $en, string $ar): City
    {
        return City::create([
            'governorate_id' => $governorate->id,
            'name' => ['en' => $en, 'ar' => $ar],
        ]);
    }

    private function facility(
        string $name,
        FacilityType $type,
        Governorate $governorate,
        City $city,
    ): Facility {
        $facility = Facility::create([
            'name' => ['en' => $name, 'ar' => $name],
            'facility_type_id' => $type->id,
        ]);

        /* governorate_id and city_id are not fillable on the model — the admin
           screens set them past the guard too. */
        $facility->forceFill([
            'governorate_id' => $governorate->id,
            'city_id' => $city->id,
        ])->save();

        return $facility->refresh();
    }
}
