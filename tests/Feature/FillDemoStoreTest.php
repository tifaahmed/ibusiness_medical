<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Governorate;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FillDemoStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fills_blank_fields_and_branches_and_the_api_then_shows_everything(): void
    {
        $governorate = Governorate::query()->create(['name' => ['ar' => 'القاهرة', 'en' => 'Cairo']]);
        City::query()->create(['governorate_id' => $governorate->id, 'name' => ['ar' => 'مدينة نصر', 'en' => 'Nasr City']]);
        $store = Store::query()->create(['title' => ['en' => 'Real Store', 'ar' => 'متجر'], 'short_description' => ['en' => 'Keep me', 'ar' => 'ابقني']]);

        $this->artisan('stores:fill-demo', ['slug' => $store->slug, '--dry' => true])->assertSuccessful();
        $this->assertSame(0, $store->branches()->count(), 'a dry run writes nothing');

        $this->artisan('stores:fill-demo', ['slug' => $store->slug])->assertSuccessful();

        $store->refresh();
        $this->assertSame('Keep me', $store->getTranslation('short_description', 'en'), 'a field that already had a value is left alone');
        $this->assertNotEmpty($store->description);
        $this->assertCount(2, $store->branches);

        $this->getJson('/api/v1/mobile/stores/'.$store->slug)
            ->assertOk()
            ->assertJsonPath('websites.0', 'https://example.com')
            ->assertJsonCount(3, 'social_links')
            ->assertJsonCount(1, 'coupons') // the expired one is hidden
            ->assertJsonPath('coupons.0.code', 'TEST10')
            ->assertJsonPath('ships_everywhere', true)
            ->assertJsonPath('offer_from', 5)
            ->assertJsonPath('offer_to', 15)
            ->assertJsonPath('branches.0.area', 'Nasr City')
            ->assertJsonPath('branches.0.whatsapp', '01012345678')
            ->assertJsonStructure(['videos', 'youtube_link', 'app_store_url', 'google_play_url']);

        // Running it again changes nothing (no duplicate branches).
        $this->artisan('stores:fill-demo', ['slug' => $store->slug])->assertSuccessful();
        $this->assertCount(2, $store->fresh()->branches);
    }

    public function test_an_unknown_slug_fails_cleanly(): void
    {
        $this->artisan('stores:fill-demo', ['slug' => 'nope'])->assertFailed();
    }
}
