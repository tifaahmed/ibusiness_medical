<?php

namespace Tests\Feature\Api;

use App\Models\City;
use App\Models\Governorate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public guest locations endpoint the Deilar checkout's address picker
 * reads. Every governorate with the cities inside it, in one response.
 */
class LocationsApiTest extends TestCase
{
    use RefreshDatabase;

    private function governorate(string $en, string $ar): Governorate
    {
        return Governorate::create(['name' => ['en' => $en, 'ar' => $ar]]);
    }

    private function city(Governorate $governorate, string $en, string $ar): City
    {
        return City::create([
            'governorate_id' => $governorate->id,
            'name' => ['en' => $en, 'ar' => $ar],
        ]);
    }

    public function test_it_returns_every_governorate_with_its_cities(): void
    {
        $cairo = $this->governorate('Cairo', 'القاهرة');
        $this->city($cairo, 'Nasr City', 'مدينة نصر');
        $this->city($cairo, 'Maadi', 'المعادي');

        $giza = $this->governorate('Giza', 'الجيزة');
        $this->city($giza, 'Dokki', 'الدقي');

        $response = $this->getJson('/api/v1/locations', ['X-Locale' => 'en']);

        $response->assertOk();

        $governorates = $response->json('governorates');

        $this->assertCount(2, $governorates);
        $this->assertSame('Cairo', $governorates[0]['name']);
        $this->assertSame($cairo->id, $governorates[0]['id']);
        $this->assertSame(
            ['Maadi', 'Nasr City'],
            array_column($governorates[0]['cities'], 'name'),
        );
        $this->assertSame(['Dokki'], array_column($governorates[1]['cities'], 'name'));
    }

    /**
     * The picker is read in Arabic as often as in English, and the name it
     * shows is the name the order archives — so the locale has to reach the
     * translation, not just the surrounding page.
     */
    public function test_it_answers_in_the_requested_locale(): void
    {
        $cairo = $this->governorate('Cairo', 'القاهرة');
        $this->city($cairo, 'Nasr City', 'مدينة نصر');

        $response = $this->getJson('/api/v1/locations', ['X-Locale' => 'ar']);

        $response->assertOk();
        $this->assertSame('القاهرة', $response->json('governorates.0.name'));
        $this->assertSame('مدينة نصر', $response->json('governorates.0.cities.0.name'));
    }

    /**
     * A governorate nobody has added cities to yet still belongs in the list:
     * dropping it would leave a buyer who lives there unable to say so.
     */
    public function test_a_governorate_with_no_cities_is_still_listed(): void
    {
        $this->governorate('Matrouh', 'مطروح');

        $response = $this->getJson('/api/v1/locations', ['X-Locale' => 'en']);

        $response->assertOk();
        $this->assertSame('Matrouh', $response->json('governorates.0.name'));
        $this->assertSame([], $response->json('governorates.0.cities'));
    }

    /**
     * The list changes a handful of times a year, so it is worth a browser
     * keeping — the storefront caches it for an hour and the header says so.
     */
    public function test_the_answer_may_be_cached_for_an_hour(): void
    {
        $this->getJson('/api/v1/locations')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=3600, public');
    }
}
