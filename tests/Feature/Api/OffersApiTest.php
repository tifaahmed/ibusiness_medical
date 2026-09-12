<?php

namespace Tests\Feature\Api;

use App\Models\City;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityType;
use App\Models\Governorate;
use App\Models\Offer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The offers carousel that rides along in the guest facilities endpoint.
 *
 * It is narrowed by the same filters as the grid beside it, and it carries
 * the type and branches of whatever it was raised on so the marketing site's
 * popup can show them without a second request.
 */
class OffersApiTest extends TestCase
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

    private function facilityType(string $en = 'Clinic', string $ar = 'عيادة'): FacilityType
    {
        return FacilityType::create(['name' => ['en' => $en, 'ar' => $ar]]);
    }

    private function offer(array $overrides = []): Offer
    {
        return Offer::create(array_merge([
            'title' => ['en' => 'Free first consultation', 'ar' => 'استشارة أولى مجانية'],
        ], $overrides));
    }

    /** @test */
    public function an_offer_on_a_facility_carries_its_type_and_every_branch(): void
    {
        $cairo = $this->governorate('Cairo', 'القاهرة');
        $nasrCity = $this->city($cairo, 'Nasr City', 'مدينة نصر');
        $type = $this->facilityType();

        $facility = Facility::create([
            'name' => ['en' => 'Nile Clinic', 'ar' => 'عيادة النيل'],
            'facility_type_id' => $type->id,
        ]);

        $branch = FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'Nasr City Branch', 'ar' => 'فرع مدينة نصر'],
            'address' => ['en' => '12 Abbas El Akkad St', 'ar' => '12 شارع عباس العقاد'],
            'governorate_id' => $cairo->id,
            'city_id' => $nasrCity->id,
        ]);

        $this->offer([
            'offerable_id' => $facility->id,
            'offerable_type' => Facility::class,
        ]);

        $this->withHeader('X-Locale', 'en')
            ->getJson('/api/v1/facilities')
            ->assertOk()
            ->assertJsonPath('offers.0.offerable_facility_type.name', 'Clinic')
            ->assertJsonCount(1, 'offers.0.offerable_branches')
            ->assertJsonPath('offers.0.offerable_branches.0.id', $branch->id)
            ->assertJsonPath('offers.0.offerable_branches.0.address', '12 Abbas El Akkad St')
            ->assertJsonPath('offers.0.offerable_branches.0.governorate.name', 'Cairo')
            ->assertJsonPath('offers.0.offerable_branches.0.city.name', 'Nasr City');
    }

    /** @test */
    public function an_offer_on_a_branch_carries_only_that_branch_and_its_facilitys_type(): void
    {
        $cairo = $this->governorate('Cairo', 'القاهرة');
        $type = $this->facilityType('Pharmacy', 'صيدلية');

        $facility = Facility::create([
            'name' => ['en' => 'Nile Pharmacy', 'ar' => 'صيدلية النيل'],
            'facility_type_id' => $type->id,
        ]);

        $branch = FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'Maadi Branch', 'ar' => 'فرع المعادي'],
            'address' => ['en' => '4 Road 9', 'ar' => '4 شارع 9'],
            'governorate_id' => $cairo->id,
        ]);

        $otherBranch = FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'Zamalek Branch', 'ar' => 'فرع الزمالك'],
            'governorate_id' => $cairo->id,
        ]);

        $this->offer([
            'offerable_id' => $branch->id,
            'offerable_type' => FacilityBranch::class,
        ]);

        $this->withHeader('X-Locale', 'en')
            ->getJson('/api/v1/facilities')
            ->assertOk()
            ->assertJsonPath('offers.0.offerable_facility_type.name', 'Pharmacy')
            ->assertJsonCount(1, 'offers.0.offerable_branches')
            ->assertJsonPath('offers.0.offerable_branches.0.id', $branch->id)
            ->assertJsonMissing(['offerable_branches' => [['id' => $otherBranch->id]]]);
    }

    /** @test */
    public function searching_a_facilitys_name_keeps_only_offers_raised_on_it(): void
    {
        $type = $this->facilityType();

        $matching = Facility::create([
            'name' => ['en' => 'Nile Clinic', 'ar' => 'عيادة النيل'],
            'facility_type_id' => $type->id,
        ]);
        $other = Facility::create([
            'name' => ['en' => 'Delta Clinic', 'ar' => 'عيادة الدلتا'],
            'facility_type_id' => $type->id,
        ]);

        $this->offer([
            'title' => ['en' => 'Free first consultation', 'ar' => 'استشارة أولى مجانية'],
            'offerable_id' => $matching->id,
            'offerable_type' => Facility::class,
        ]);
        $this->offer([
            'title' => ['en' => 'Free blood test', 'ar' => 'تحليل دم مجاني'],
            'offerable_id' => $other->id,
            'offerable_type' => Facility::class,
        ]);

        $this->withHeader('X-Locale', 'en')
            ->getJson('/api/v1/facilities?search=Nile')
            ->assertOk()
            ->assertJsonCount(1, 'offers')
            ->assertJsonPath('offers.0.offerable_name', 'Nile Clinic');
    }

    /** @test */
    public function a_search_matching_no_facility_leaves_no_offers(): void
    {
        $type = $this->facilityType();

        $facility = Facility::create([
            'name' => ['en' => 'Nile Clinic', 'ar' => 'عيادة النيل'],
            'facility_type_id' => $type->id,
        ]);

        $this->offer([
            'offerable_id' => $facility->id,
            'offerable_type' => Facility::class,
        ]);

        $this->withHeader('X-Locale', 'en')
            ->getJson('/api/v1/facilities?search=nothing-matches-this')
            ->assertOk()
            ->assertJsonCount(0, 'offers');
    }

    /** @test */
    public function filtering_by_governorate_keeps_only_offers_with_a_branch_there(): void
    {
        $cairo = $this->governorate('Cairo', 'القاهرة');
        $giza = $this->governorate('Giza', 'الجيزة');
        $type = $this->facilityType();

        $cairoFacility = Facility::create([
            'name' => ['en' => 'Nile Clinic', 'ar' => 'عيادة النيل'],
            'facility_type_id' => $type->id,
        ]);
        FacilityBranch::create([
            'facility_id' => $cairoFacility->id,
            'name' => ['en' => 'Cairo Branch', 'ar' => 'فرع القاهرة'],
            'governorate_id' => $cairo->id,
        ]);

        $gizaFacility = Facility::create([
            'name' => ['en' => 'Delta Clinic', 'ar' => 'عيادة الدلتا'],
            'facility_type_id' => $type->id,
        ]);
        FacilityBranch::create([
            'facility_id' => $gizaFacility->id,
            'name' => ['en' => 'Giza Branch', 'ar' => 'فرع الجيزة'],
            'governorate_id' => $giza->id,
        ]);

        $this->offer(['offerable_id' => $cairoFacility->id, 'offerable_type' => Facility::class]);
        $this->offer(['offerable_id' => $gizaFacility->id, 'offerable_type' => Facility::class]);

        $this->withHeader('X-Locale', 'en')
            ->getJson('/api/v1/facilities?governorate_id='.$cairo->id)
            ->assertOk()
            ->assertJsonCount(1, 'offers')
            ->assertJsonPath('offers.0.offerable_name', 'Nile Clinic');
    }

    /** @test */
    public function filtering_by_facility_type_applies_to_a_branch_offer_through_its_facility(): void
    {
        $clinic = $this->facilityType('Clinic', 'عيادة');
        $pharmacy = $this->facilityType('Pharmacy', 'صيدلية');

        $clinicFacility = Facility::create([
            'name' => ['en' => 'Nile Clinic', 'ar' => 'عيادة النيل'],
            'facility_type_id' => $clinic->id,
        ]);
        $clinicBranch = FacilityBranch::create([
            'facility_id' => $clinicFacility->id,
            'name' => ['en' => 'Cairo Branch', 'ar' => 'فرع القاهرة'],
        ]);

        $pharmacyFacility = Facility::create([
            'name' => ['en' => 'Nile Pharmacy', 'ar' => 'صيدلية النيل'],
            'facility_type_id' => $pharmacy->id,
        ]);

        $this->offer(['offerable_id' => $clinicBranch->id, 'offerable_type' => FacilityBranch::class]);
        $this->offer(['offerable_id' => $pharmacyFacility->id, 'offerable_type' => Facility::class]);

        $this->withHeader('X-Locale', 'en')
            ->getJson('/api/v1/facilities?facility_type_id='.$clinic->id)
            ->assertOk()
            ->assertJsonCount(1, 'offers')
            ->assertJsonPath('offers.0.offerable_name', 'Cairo Branch');
    }
}
