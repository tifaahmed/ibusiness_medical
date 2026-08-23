<?php

namespace Tests\Feature\Api;

use App\Enums\Address\AddressTypeEnum;
use App\Enums\FamilyMember\RelationshipEnum;
use App\Models\Address;
use App\Models\FamilyMember;
use App\Models\Governorate;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerMembershipApiTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'partner-api-test-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.partner_api.key' => self::KEY]);
    }

    /**
     * Call the lookup endpoint with the shared key attached.
     */
    private function lookup(string $membershipNumber, ?string $key = self::KEY): \Illuminate\Testing\TestResponse
    {
        return $this->getJson(
            '/api/v1/partner/memberships/'.rawurlencode($membershipNumber),
            $key === null ? [] : ['X-Api-Key' => $key],
        );
    }

    /** @test */
    public function a_partner_reads_a_membership_by_its_number(): void
    {
        $user = User::factory()->create(['name' => 'Mona Adel']);
        $membership = Membership::factory()->active()->withMembershipNumber('MEM-000123-2026-01')->create([
            'user_id' => $user->id,
        ]);

        $response = $this->lookup('MEM-000123-2026-01');

        $response->assertOk()
            ->assertJsonPath('data.membership_number', 'MEM-000123-2026-01')
            ->assertJsonPath('data.member.name', 'Mona Adel')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.is_expired', false)
            ->assertJsonPath('data.expiration_date', $membership->expiration_date->toDateString());
    }

    /** @test */
    public function the_slug_works_as_well_as_the_number(): void
    {
        $membership = Membership::factory()->create();

        $this->lookup($membership->slug)
            ->assertOk()
            ->assertJsonPath('data.membership_number', $membership->membership_number);
    }

    /** @test */
    public function only_the_family_members_still_on_the_card_come_back(): void
    {
        $membership = Membership::factory()->create();

        FamilyMember::factory()->for($membership)->create([
            'name' => 'Youssef Adel',
            'relationship' => RelationshipEnum::SON,
        ]);
        FamilyMember::factory()->for($membership)->inactive()->create(['name' => 'Removed Person']);

        $response = $this->lookup($membership->membership_number);

        $response->assertOk()
            ->assertJsonCount(1, 'data.family_members')
            ->assertJsonPath('data.family_members.0.name', 'Youssef Adel')
            ->assertJsonPath('data.family_members.0.relationship', 'son');
    }

    /** @test */
    public function the_card_carries_the_members_addresses_with_translated_labels(): void
    {
        $governorate = Governorate::create(['name' => ['en' => 'Cairo', 'ar' => 'القاهرة']]);
        $city = \App\Models\City::create([
            'name' => ['en' => 'Nasr City', 'ar' => 'مدينة نصر'],
            'governorate_id' => $governorate->id,
        ]);
        $membership = Membership::factory()->create();

        Address::factory()->for($membership)->create([
            'type' => AddressTypeEnum::HOME,
            'address' => 'Behind the central hospital',
            'street' => 'Abbas El Akkad',
            'governorate_id' => $governorate->id,
            'city_id' => $city->id,
            'building_number' => '12',
            'apartment_number' => '3',
            'floor_number' => '5',
            'special_mark' => 'Next to the pharmacy',
        ]);

        $this->lookup($membership->membership_number, key: self::KEY)
            ->assertOk()
            ->assertJsonCount(1, 'data.addresses')
            ->assertJsonPath('data.addresses.0.type', 'home')
            ->assertJsonPath('data.addresses.0.type_label', __('admin.member.address_type_home'))
            ->assertJsonPath('data.addresses.0.address', 'Behind the central hospital')
            ->assertJsonPath('data.addresses.0.street', 'Abbas El Akkad')
            ->assertJsonPath('data.addresses.0.governorate_label', 'Cairo')
            ->assertJsonPath('data.addresses.0.city_label', 'Nasr City')
            ->assertJsonPath('data.addresses.0.building_number', '12')
            ->assertJsonPath('data.addresses.0.apartment_number', '3')
            ->assertJsonPath('data.addresses.0.floor_number', '5')
            ->assertJsonPath('data.addresses.0.special_mark', 'Next to the pharmacy');
    }

    /** @test */
    public function an_address_the_member_removed_is_not_published(): void
    {
        $membership = Membership::factory()->create();

        Address::factory()->for($membership)->create();
        Address::factory()->for($membership)->create()->delete();

        $this->lookup($membership->membership_number)
            ->assertOk()
            ->assertJsonCount(1, 'data.addresses');
    }

    /**
     * The partner payload is a summary; spend history stays on our side.
     *
     * @test
     */
    public function the_response_carries_no_financial_detail(): void
    {
        $membership = Membership::factory()->create();

        $data = $this->lookup($membership->membership_number)->json('data');

        $this->assertArrayNotHasKey('usages', $data);
        $this->assertArrayNotHasKey('total_paid', $data);
        $this->assertArrayNotHasKey('card_layouts', $data);
    }

    /** @test */
    public function an_expired_membership_is_reported_as_expired(): void
    {
        $membership = Membership::factory()->expired()->create();

        $this->lookup($membership->membership_number)
            ->assertOk()
            ->assertJsonPath('data.is_expired', true)
            ->assertJsonPath('data.is_active', false);
    }

    /** @test */
    public function hidden_memberships_are_not_exposed(): void
    {
        $membership = Membership::factory()->create(['is_visible' => false]);

        $this->lookup($membership->membership_number)->assertNotFound();
    }

    /** @test */
    public function an_unknown_number_returns_a_not_found_message(): void
    {
        $this->lookup('MEM-999999-2026-99')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    /** @test */
    public function the_endpoint_is_closed_without_the_shared_key(): void
    {
        $membership = Membership::factory()->create();

        $this->lookup($membership->membership_number, key: null)->assertUnauthorized();
        $this->lookup($membership->membership_number, key: 'wrong-key')->assertUnauthorized();
    }

    /**
     * A deploy that forgets the key must fail closed, not open.
     *
     * @test
     */
    public function an_unconfigured_key_locks_the_endpoint(): void
    {
        config(['services.partner_api.key' => null]);

        $membership = Membership::factory()->create();

        $this->lookup($membership->membership_number, key: 'anything')->assertServiceUnavailable();
    }
}
