<?php

namespace Tests\Feature\Api;

use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityType;
use App\Models\Governorate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public guest facilities endpoint that the Deilar marketing site reads.
 *
 * Branches carry their governorate and city, and once a governorate filter is
 * applied the branches are ordered so the ones in that governorate lead each
 * card.
 */
class FacilitiesApiTest extends TestCase
{
    use RefreshDatabase;

    private function governorate(string $en, string $ar): Governorate
    {
        return Governorate::create(['name' => ['en' => $en, 'ar' => $ar]]);
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
}
