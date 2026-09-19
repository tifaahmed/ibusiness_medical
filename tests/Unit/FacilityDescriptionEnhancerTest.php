<?php

namespace Tests\Unit;

use App\Services\Ai\GeminiClient;
use App\Services\FacilityDescriptionEnhancer;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * "Enhance with AI" must move the Arabic AND the English description together.
 */
class FacilityDescriptionEnhancerTest extends TestCase
{
    private function enhancer(array $answer, ?callable $seeUser = null): FacilityDescriptionEnhancer
    {
        $ai = Mockery::mock(GeminiClient::class);
        $ai->shouldReceive('json')->andReturnUsing(function ($system, $user) use ($answer, $seeUser) {
            $seeUser && $seeUser($user);

            return $answer;
        });

        return new FacilityDescriptionEnhancer($ai);
    }

    public function test_both_languages_are_enhanced(): void
    {
        $out = $this->enhancer([
            'ar' => '<h3>🏷️ الخصومات</h3><ul><li>خصم 20% على الكشف</li></ul>',
            'en' => '<h3>🏷️ Discounts</h3><ul><li>20% discount on consultation</li></ul>',
        ])->enhance([
            'ar' => '<p>خصم 20% على الكشف</p>',
            'en' => '<p>20% discount on consultation</p>',
        ]);

        $this->assertSame(['ar', 'en'], array_keys($out));
    }

    public function test_an_empty_language_is_filled_from_the_other_one(): void
    {
        $asked = '';
        $out = $this->enhancer([
            'ar' => '<h3>🏷️ الخصومات</h3><ul><li>خصم 20% على الكشف</li></ul>',
            'en' => '<h3>🏷️ Discounts</h3><ul><li>20% discount on consultation</li></ul>',
        ], function ($user) use (&$asked) {
            $asked = $user;
        })->enhance(['ar' => '<p>خصم 20% على الكشف</p>', 'en' => '']);

        $this->assertStringContainsString('en: (empty', $asked);
        $this->assertArrayHasKey('en', $out);
        $this->assertArrayHasKey('ar', $out);
    }

    public function test_a_translation_that_loses_a_number_is_discarded(): void
    {
        $out = $this->enhancer([
            'ar' => '<h3>🏷️ الخصومات</h3><ul><li>خصم 20% على الكشف</li></ul>',
            'en' => '<ul><li>a discount on consultation</li></ul>',
        ])->enhance(['ar' => '<p>خصم 20% على الكشف</p>']);

        $this->assertArrayHasKey('ar', $out);
        $this->assertArrayNotHasKey('en', $out);
    }
}
