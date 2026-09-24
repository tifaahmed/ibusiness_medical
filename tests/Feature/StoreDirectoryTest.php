<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Governorate;
use App\Models\Store;
use App\Models\StoreBranch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public stores directory behind the Deilar storefront: the listing
 * endpoint, one store's own page, and the branch map — mirrors the coverage
 * shape a Facility directory test would have.
 */
class StoreDirectoryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function the_directory_lists_stores(): void
    {
        Store::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/stores');

        $response->assertOk();
        $response->assertJsonCount(3, 'stores.data');
    }

    /** @test */
    public function the_directory_can_be_searched_by_title(): void
    {
        Store::factory()->create(['title' => ['en' => 'Sunrise Pharmacy', 'ar' => 'صيدلية الشروق']]);
        Store::factory()->create(['title' => ['en' => 'Downtown Books', 'ar' => 'مكتبة وسط البلد']]);

        $response = $this->getJson('/api/v1/stores?search=Sunrise');

        $response->assertOk();
        $response->assertJsonCount(1, 'stores.data');
        $response->assertJsonPath('stores.data.0.title', 'Sunrise Pharmacy');
    }

    /** @test */
    public function the_directory_can_be_filtered_by_governorate(): void
    {
        $governorate = Governorate::factory()->create();
        $elsewhere = Governorate::factory()->create();

        $inGovernorate = Store::factory()->create();
        StoreBranch::factory()->create(['store_id' => $inGovernorate->id, 'governorate_id' => $governorate->id]);

        $notInGovernorate = Store::factory()->create();
        StoreBranch::factory()->create(['store_id' => $notInGovernorate->id, 'governorate_id' => $elsewhere->id]);

        $response = $this->getJson('/api/v1/stores?governorate_id='.$governorate->id);

        $response->assertOk();
        $response->assertJsonCount(1, 'stores.data');
        $response->assertJsonPath('stores.data.0.slug', $inGovernorate->slug);
    }

    /** @test */
    public function one_store_is_shown_with_its_branches(): void
    {
        $store = Store::factory()->create();
        $branch = StoreBranch::factory()->create(['store_id' => $store->id]);

        $response = $this->getJson('/api/v1/stores/'.$store->slug);

        $response->assertOk();
        $response->assertJsonPath('store.slug', $store->slug);
        $response->assertJsonCount(1, 'branches');
        $response->assertJsonPath('branches.0.id', $branch->id);
    }

    /** @test */
    public function an_unknown_slug_is_a_404(): void
    {
        $this->getJson('/api/v1/stores/does-not-exist')->assertNotFound();
    }

    /** @test */
    public function the_branch_map_returns_clusters_for_geocoded_branches(): void
    {
        $store = Store::factory()->create();
        StoreBranch::factory()->create([
            'store_id' => $store->id,
            'latitude' => 30.0444,
            'longitude' => 31.2357,
        ]);

        $response = $this->getJson('/api/v1/stores/branches/map?zoom=6');

        $response->assertOk();
        $response->assertJsonCount(1, 'clusters');
        $response->assertJsonPath('clusters.0.count', 1);
    }

    /** @test */
    public function city_belongs_to_governorate_is_available_for_the_directory_filters(): void
    {
        $city = City::factory()->create();

        $this->assertNotNull($city->governorate);
    }
}
