<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Governorate;
use App\Models\Store;
use App\Models\StoreBranch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** The area buttons of the stores page: only governorates and cities that hold a store branch are listed. */
class StoreAreasTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function only_areas_with_a_store_branch_are_listed(): void
    {
        Cache::forget('mobile:store-areas');
        $gov = Governorate::factory()->create();
        $empty = Governorate::factory()->create();
        $city = City::factory()->create(['governorate_id' => $gov->id]);
        City::factory()->create(['governorate_id' => $gov->id]);

        $store = Store::query()->create(['title' => ['en' => 'Honey', 'ar' => 'Honey']]);
        StoreBranch::factory()->create(['store_id' => $store->id, 'governorate_id' => $gov->id, 'city_id' => $city->id]);

        $data = $this->getJson('/api/v1/mobile/store-areas')->assertOk()->json('data');

        $this->assertSame([$gov->id], array_column($data, 'id'));
        $this->assertSame([$city->id], $data[0]['cities']);
        $this->assertNotContains($empty->id, array_column($data, 'id'));
    }
}
