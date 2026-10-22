<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

/**
 * The endpoint the mobile app reads to decide whether the installed build
 * must be updated before it can be used.
 */
class MobileAppConfigTest extends TestCase
{
    public function test_it_returns_the_minimum_version_and_store_url(): void
    {
        config([
            'mobile_app.min_version_code' => 4,
            'mobile_app.store_url' => 'https://play.google.com/store/apps/details?id=com.deilar.card',
            'mobile_app.ios_min_build' => 2,
            'mobile_app.ios_store_url' => 'https://apps.apple.com/app/id123',
        ]);

        $this->getJson('/api/v1/mobile/app-config')
            ->assertOk()
            ->assertExactJson([
                'min_version_code' => 4,
                'store_url' => 'https://play.google.com/store/apps/details?id=com.deilar.card',
                'ios_min_build' => 2,
                'ios_store_url' => 'https://apps.apple.com/app/id123',
            ]);
    }

    public function test_it_is_never_cached(): void
    {
        $this->getJson('/api/v1/mobile/app-config')
            ->assertOk()
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private');
    }
}
