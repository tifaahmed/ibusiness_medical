<?php

namespace Tests\Feature;

use App\Models\Membership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The card artwork as the Deilar marketing site reads it: one key-gated GET
 * that answers with a PNG, so that site can show a member their card without
 * knowing how one is drawn.
 */
class PartnerMembershipCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.partner_api.key' => 'partner-test-key']);
    }

    /** @test */
    public function the_card_is_not_readable_without_the_shared_key(): void
    {
        $membership = Membership::factory()->create(['is_visible' => true]);

        $this->getJson("/api/v1/partner/memberships/{$membership->slug}/card")
            ->assertUnauthorized();
    }

    /** @test */
    public function a_member_with_no_card_of_their_own_gets_the_default_one_drawn(): void
    {
        Storage::fake('public');

        $membership = Membership::factory()->create(['is_visible' => true]);

        $response = $this->withHeader('X-Api-Key', 'partner-test-key')
            ->get("/api/v1/partner/memberships/{$membership->slug}/card");

        $response->assertOk()->assertHeader('Content-Type', 'image/png');

        // Drawing it also files it against the membership, so the next visit
        // serves the same image rather than redrawing it.
        $this->assertNotNull($membership->cardLayouts()->where('mode', 'full')->first()?->generated_image_path);
    }

    /** @test */
    public function the_card_an_admin_generated_is_the_one_served(): void
    {
        Storage::fake('public');

        $membership = Membership::factory()->create(['is_visible' => true]);

        /*
         * Stand in for the admin having generated a card from a layout they
         * customised: the file on disk is what a partner must get back, not a
         * fresh default rendering that would lose their work.
         */
        Storage::disk('public')->put('cards/card-admins-own.png', 'admin-generated-bytes');
        $membership->cardLayouts()->create([
            'mode' => 'full',
            'generated_image_path' => 'cards/card-admins-own.png',
        ]);

        $response = $this->withHeader('X-Api-Key', 'partner-test-key')
            ->get("/api/v1/partner/memberships/{$membership->slug}/card");

        $response->assertOk();
        $this->assertSame(
            'admin-generated-bytes',
            file_get_contents($response->getFile()->getPathname()),
        );
    }

    /** @test */
    public function the_membership_number_resolves_as_well_as_the_slug(): void
    {
        Storage::fake('public');

        $membership = Membership::factory()->create(['is_visible' => true]);

        $this->withHeader('X-Api-Key', 'partner-test-key')
            ->get("/api/v1/partner/memberships/{$membership->membership_number}/card")
            ->assertOk();
    }

    /** @test */
    public function there_is_no_card_for_a_membership_that_does_not_exist(): void
    {
        $this->withHeader('X-Api-Key', 'partner-test-key')
            ->getJson('/api/v1/partner/memberships/nope/card')
            ->assertNotFound();
    }
}
