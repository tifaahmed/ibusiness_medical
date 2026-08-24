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
 * The public guest facilities endpoint that the Deilar marketing site reads.
 *
 * Branches carry their governorate and city, and once a governorate or city
 * filter is applied the branches are ordered so the ones in that place lead
 * each card.
 */
class FacilitiesApiTest extends TestCase
{
    use RefreshDatabase;

    private function governorate(string $en, string $ar): Governorate
    {
        return Governorate::create(['name' => ['en' => $en, 'ar' => $ar]]);
    }

    private function city(Governorate $governorate, string $en, string $ar): City
    {
        return City::create([
            'governorate_id' => $governorate->id,
            'name' => ['en' => $en, 'ar' => $ar],
        ]);
    }

    private function facilityType(): FacilityType
    {
        return FacilityType::create(['name' => ['en' => 'Clinic', 'ar' => 'عيادة']]);
    }

    /**
     * A facility with a branch in each of the two governorates, the first
     * branch recorded in the one we do not filter by.
     *
     * @return array{facility: Facility, cairo: Governorate, giza: Governorate, cairoBranch: FacilityBranch}
     */
    private function facilityWithBranchesInBothGovernorates(): array
    {
        $type = FacilityType::create(['name' => ['en' => 'Clinic', 'ar' => 'عيادة']]);
        $cairo = $this->governorate('Cairo', 'القاهرة');
        $giza = $this->governorate('Giza', 'الجيزة');

        $facility = Facility::create([
            'name' => ['en' => 'Nile Clinic', 'ar' => 'عيادة النيل'],
            'facility_type_id' => $type->id,
            'discount_percent' => 20,
        ]);

        $gizaBranch = FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'Giza Branch', 'ar' => 'فرع الجيزة'],
            'governorate_id' => $giza->id,
        ]);

        $cairoBranch = FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'Cairo Branch', 'ar' => 'فرع القاهرة'],
            'governorate_id' => $cairo->id,
        ]);

        return [
            'facility' => $facility,
            'cairo' => $cairo,
            'giza' => $giza,
            'cairoBranch' => $cairoBranch,
            'gizaBranch' => $gizaBranch,
        ];
    }

    /** @test */
    public function branches_carry_their_governorate_and_city(): void
    {
        $this->facilityWithBranchesInBothGovernorates();

        $this->withHeader('X-Locale', 'en')
            ->getJson('/api/v1/facilities')
            ->assertOk()
            ->assertJsonPath('facilities.data.0.branches.0.governorate.name', 'Giza')
            ->assertJsonPath('facilities.data.0.branches.1.governorate.name', 'Cairo');
    }

    /** @test */
    public function filtering_by_governorate_orders_its_branches_first(): void
    {
        ['cairo' => $cairo, 'giza' => $giza, 'cairoBranch' => $cairoBranch, 'gizaBranch' => $gizaBranch]
            = $this->facilityWithBranchesInBothGovernorates();

        $this->withHeader('X-Locale', 'en')
            ->getJson('/api/v1/facilities?governorate_id='.$cairo->id)
            ->assertOk()
            ->assertJsonCount(1, 'facilities.data')
            ->assertJsonPath('facilities.data.0.branches.0.id', $cairoBranch->id)
            ->assertJsonPath('facilities.data.0.branches.0.governorate.name', 'Cairo')
            ->assertJsonPath('facilities.data.0.branches.1.id', $gizaBranch->id)
            ->assertJsonPath('facilities.data.0.branches.1.governorate.name', 'Giza');
    }

    /** @test */
    public function the_branch_order_is_left_alone_without_a_governorate_filter(): void
    {
        ['gizaBranch' => $gizaBranch] = $this->facilityWithBranchesInBothGovernorates();

        $this->withHeader('X-Locale', 'en')
            ->getJson('/api/v1/facilities')
            ->assertOk()
            ->assertJsonPath('facilities.data.0.branches.0.id', $gizaBranch->id);
    }

    /** @test */
    public function filtering_by_city_keeps_facilities_with_a_branch_there(): void
    {
        $cairo = $this->governorate('Cairo', 'القاهرة');
        $nasrCity = $this->city($cairo, 'Nasr City', 'مدينة نصر');
        $maadi = $this->city($cairo, 'Maadi', 'المعادي');
        $type = FacilityType::create(['name' => ['en' => 'Clinic', 'ar' => 'عيادة']]);

        $listed = Facility::create([
            'name' => ['en' => 'Nile Clinic', 'ar' => 'عيادة النيل'],
            'facility_type_id' => $type->id,
        ]);
        FacilityBranch::create([
            'facility_id' => $listed->id,
            'name' => ['en' => 'Nasr City Branch', 'ar' => 'فرع مدينة نصر'],
            'governorate_id' => $cairo->id,
            'city_id' => $nasrCity->id,
        ]);

        $elsewhere = Facility::create([
            'name' => ['en' => 'Delta Clinic', 'ar' => 'عيادة الدلتا'],
            'facility_type_id' => $type->id,
        ]);
        FacilityBranch::create([
            'facility_id' => $elsewhere->id,
            'name' => ['en' => 'Maadi Branch', 'ar' => 'فرع المعادي'],
            'governorate_id' => $cairo->id,
            'city_id' => $maadi->id,
        ]);

        $this->withHeader('X-Locale', 'en')
            ->getJson('/api/v1/facilities?city_id='.$nasrCity->id)
            ->assertOk()
            ->assertJsonCount(1, 'facilities.data')
            ->assertJsonPath('facilities.data.0.slug', $listed->slug);
    }

    /** @test */
    public function a_head_office_in_the_city_counts_even_without_a_branch(): void
    {
        $cairo = $this->governorate('Cairo', 'القاهرة');
        $nasrCity = $this->city($cairo, 'Nasr City', 'مدينة نصر');
        $type = $this->facilityType();

        $headOffice = Facility::create([
            'name' => ['en' => 'Nile Clinic', 'ar' => 'عيادة النيل'],
            'facility_type_id' => $type->id,
            'city_id' => $nasrCity->id,
        ]);

        $this->withHeader('X-Locale', 'en')
            ->getJson('/api/v1/facilities?city_id='.$nasrCity->id)
            ->assertOk()
            ->assertJsonCount(1, 'facilities.data')
            ->assertJsonPath('facilities.data.0.slug', $headOffice->slug);
    }

    /** @test */
    public function filtering_by_city_orders_its_branches_first(): void
    {
        $cairo = $this->governorate('Cairo', 'القاهرة');
        $nasrCity = $this->city($cairo, 'Nasr City', 'مدينة نصر');
        $maadi = $this->city($cairo, 'Maadi', 'المعادي');

        $facility = Facility::create([
            'name' => ['en' => 'Nile Clinic', 'ar' => 'عيادة النيل'],
            'facility_type_id' => $this->facilityType()->id,
        ]);

        // The branch outside the filtered city is recorded first.
        $maadiBranch = FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'Maadi Branch', 'ar' => 'فرع المعادي'],
            'governorate_id' => $cairo->id,
            'city_id' => $maadi->id,
        ]);
        $nasrCityBranch = FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'Nasr City Branch', 'ar' => 'فرع مدينة نصر'],
            'governorate_id' => $cairo->id,
            'city_id' => $nasrCity->id,
        ]);

        $this->withHeader('X-Locale', 'en')
            ->getJson('/api/v1/facilities?city_id='.$nasrCity->id)
            ->assertOk()
            ->assertJsonPath('facilities.data.0.branches.0.id', $nasrCityBranch->id)
            ->assertJsonPath('facilities.data.0.branches.1.id', $maadiBranch->id);
    }

    /** @test */
    public function the_cities_list_is_scoped_to_the_chosen_governorate_and_hosting_places_only(): void
    {
        $cairo = $this->governorate('Cairo', 'القاهرة');
        $giza = $this->governorate('Giza', 'الجيزة');
        $hosting = $this->city($cairo, 'Nasr City', 'مدينة نصر');

        // A city with nothing listed in it, and one outside the governorate.
        $this->city($cairo, 'Obour', 'العبور');
        $this->city($giza, '6th of October', 'السادس من أكتوبر');

        $facility = Facility::create([
            'name' => ['en' => 'Nile Clinic', 'ar' => 'عيادة النيل'],
            'facility_type_id' => $this->facilityType()->id,
        ]);
        FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'Nasr City Branch', 'ar' => 'فرع مدينة نصر'],
            'governorate_id' => $cairo->id,
            'city_id' => $hosting->id,
        ]);

        $this->withHeader('X-Locale', 'en')
            ->getJson('/api/v1/facilities?governorate_id='.$cairo->id)
            ->assertOk()
            ->assertJsonCount(1, 'cities')
            ->assertJsonPath('cities.0.id', $hosting->id);

        $this->withHeader('X-Locale', 'en')
            ->getJson('/api/v1/facilities')
            ->assertOk()
            ->assertJsonCount(1, 'cities')
            ->assertJsonPath('cities.0.name', 'Nasr City');
    }
}
