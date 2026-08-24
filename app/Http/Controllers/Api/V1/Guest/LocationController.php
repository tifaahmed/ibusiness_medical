<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Governorate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;

/**
 * Every governorate with the cities inside it, for an address picker.
 *
 * Public and key-less like the facilities and the catalogue above it: a list
 * of place names carries nothing about anybody. It exists because the Deilar
 * checkout used to take the governorate and the city as free text, and orders
 * archive both as text — `UpsertMemberAddressFromOrderAction` then has to
 * resolve that text back to a row to keep a member's address book current, so
 * a typed spelling is a mirror that silently stops working.
 *
 * The whole table ships in one response (27 governorates, ~300 cities): it is
 * a few kilobytes, it changes a handful of times a year, and a storefront that
 * has it all can filter the cities as the governorate is picked without a
 * second round trip.
 */
class LocationController extends Controller
{
    /** An hour, matching what the storefront keeps it for. */
    private const CACHE_SECONDS = 3600;

    public function __invoke(): JsonResponse
    {
        $locale = App::getLocale();

        $governorates = Governorate::query()
            ->with(['cities'])
            ->get()
            ->map(fn (Governorate $governorate) => [
                'id' => $governorate->id,
                'name' => $governorate->getTranslation('name', $locale),
                'cities' => $this->cities($governorate, $locale),
            ])
            /* Alphabetical in the language being read, not by id: this is a
               list somebody scans for their own governorate. */
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return response()
            ->json(['governorates' => $governorates])
            ->header('Cache-Control', 'public, max-age='.self::CACHE_SECONDS);
    }

    /**
     * The cities of one governorate, in the same order rule as their parent.
     *
     * @return list<array{id: int, name: string}>
     */
    private function cities(Governorate $governorate, string $locale): array
    {
        return $governorate->cities
            ->map(fn (City $city) => [
                'id' => $city->id,
                'name' => $city->getTranslation('name', $locale),
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }
}
