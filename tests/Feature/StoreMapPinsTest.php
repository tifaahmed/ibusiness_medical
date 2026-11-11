<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\StoreBranch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The stores map: light pins nearest first, filtered by name and range, and one pin's popup card. */
class StoreMapPinsTest extends TestCase
{
    use RefreshDatabase;

    private function branch(string $title, float $lat, float $lng): StoreBranch
    {
        $store = Store::query()->create(['title' => ['en' => $title, 'ar' => $title]]);

        return StoreBranch::factory()->create(['store_id' => $store->id, 'latitude' => $lat, 'longitude' => $lng]);
    }

    /** @test */
    public function pins_come_nearest_first_and_the_range_and_name_filter_them(): void
    {
        $near = $this->branch('Honey House', 30.0500, 31.2400);
        $this->branch('Coffee Corner', 30.0600, 31.2500);
        $this->branch('Far Away Oils', 31.2000, 29.9000);

        $all = $this->getJson('/api/v1/mobile/store-map-pins?lat=30.05&lng=31.24')->assertOk();
        $this->assertSame($near->id, $all->json('data.0.id'));
        $this->assertSame('Honey House', $all->json('data.0.name'));
        $this->assertCount(3, $all->json('data'));

        $this->assertCount(2, $this->getJson('/api/v1/mobile/store-map-pins?lat=30.05&lng=31.24&radius_km=5')->assertOk()->json('data'));
        $this->assertCount(1, $this->getJson('/api/v1/mobile/store-map-pins?lat=30.05&lng=31.24&q=Coffee')->assertOk()->json('data'));
    }

    /** @test */
    public function a_pin_opens_with_its_store_and_branch(): void
    {
        $branch = $this->branch('Honey House', 30.05, 31.24);

        $this->getJson('/api/v1/mobile/store-map-pins/'.$branch->id)
            ->assertOk()
            ->assertJsonPath('title', 'Honey House')
            ->assertJsonPath('branch.id', $branch->id)
            ->assertJsonPath('branch.lat', 30.05);

        $this->getJson('/api/v1/mobile/store-map-pins/999999')->assertNotFound();
    }

    /** @test */
    public function the_store_list_can_be_ordered_by_the_biggest_offer(): void
    {
        $low = Store::query()->create(['title' => ['en' => 'Low', 'ar' => 'Low'], 'offer_percent_to' => 5]);
        $high = Store::query()->create(['title' => ['en' => 'High', 'ar' => 'High'], 'offer_percent_to' => 30]);

        $ids = $this->getJson('/api/v1/mobile/stores?sort=discount')->assertOk()->json('data.*.id');

        $this->assertSame([$high->id, $low->id], $ids);
    }

    /** @test */
    public function pins_need_a_position(): void
    {
        $this->getJson('/api/v1/mobile/store-map-pins')->assertStatus(422);
    }
}
