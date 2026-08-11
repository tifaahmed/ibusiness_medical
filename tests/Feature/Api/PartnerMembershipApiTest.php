<?php

namespace Tests\Feature\Api;

use App\Enums\FamilyMember\RelationshipEnum;
use App\Models\FamilyMember;
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
