<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\StoreBranch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** `sort=none` = no ordering chosen: the nearest store no longer ranks first, but the distance is still returned. */
class StoreSortNoneTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function none_does_not_rank_by_distance_and_nearest_does(): void
    {
        $near = Store::query()->create(['title' => ['en' => 'Near', 'ar' => 'Near']]);
        StoreBranch::factory()->create(['store_id' => $near->id, 'latitude' => 30.05, 'longitude' => 31.24]);
        $far = Store::query()->create(['title' => ['en' => 'Far', 'ar' => 'Far']]);
        StoreBranch::factory()->create(['store_id' => $far->id, 'latitude' => 31.2, 'longitude' => 29.9]);

        $nearest = $this->getJson('/api/v1/mobile/stores?lat=30.05&lng=31.24&sort=nearest')->assertOk()->json('data');
        $this->assertSame($near->id, $nearest[0]['id']);

        $none = $this->getJson('/api/v1/mobile/stores?lat=30.05&lng=31.24&sort=none')->assertOk()->json('data');
        $this->assertSame($far->id, $none[0]['id'], 'newest first, not nearest first');
        $this->assertNotNull($none[0]['distance_km'], 'the distance is still shown');
    }
}
