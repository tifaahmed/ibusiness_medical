<?php

namespace Tests\Unit;

use App\Support\PublicMembershipUrl;
use Tests\TestCase;

/**
 * These addresses get printed onto physical cards, so the shapes asserted here
 * are the ones already in people's wallets.
 */
class PublicMembershipUrlTest extends TestCase
{
    /** @test */
    public function a_card_points_at_the_deilar_marketing_site(): void
    {
        config(['services.deilar.url' => 'https://deilar.test/']);

        $this->assertSame(
            'https://deilar.test/membership/mem-1000',
            PublicMembershipUrl::forSlug('mem-1000'),
        );
    }

    /** @test */
    public function without_a_marketing_site_a_card_points_back_here(): void
    {
        config([
            'services.deilar.url' => null,
            'app.url' => 'https://ibusiness.test',
        ]);

        $this->assertSame(
            'https://ibusiness.test/membership/mem-1000',
            PublicMembershipUrl::forSlug('mem-1000'),
        );
    }

    /** @test */
    public function a_member_with_no_slug_gets_a_code_that_still_scans(): void
    {
        config(['services.deilar.url' => 'https://deilar.test']);

        $this->assertSame('https://deilar.test/membership', PublicMembershipUrl::forSlug(null));
        $this->assertSame('https://deilar.test/membership', PublicMembershipUrl::forSlug('  '));
    }

    /** @test */
    public function a_qr_code_carries_the_slug_as_a_query(): void
    {
        config(['services.deilar.url' => 'https://deilar.test']);

        $this->assertSame(
            'https://deilar.test/membership/mem-1000?slug=mem-1000',
            PublicMembershipUrl::qrForSlug('mem-1000'),
        );
    }

    /** @test */
    public function a_qr_code_for_a_member_with_no_slug_has_nothing_to_tag(): void
    {
        config(['services.deilar.url' => 'https://deilar.test']);

        $this->assertSame('https://deilar.test/membership', PublicMembershipUrl::qrForSlug(null));
    }
}
