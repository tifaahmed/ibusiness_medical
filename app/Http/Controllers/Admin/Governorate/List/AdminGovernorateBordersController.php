<?php

namespace App\Http\Controllers\Admin\Governorate\List;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Governorate;
use Illuminate\Http\JsonResponse;

/**
 * Every governorate with its border as a GeoJSON geometry, for the map view of
 * the governorate list to colour and hit-test on the client. `geometry` is
 * null for a governorate with no stored border (it stays in the legend, it is
 * just not drawn).
 *
 * Read-only reference data, behind the same permission as the list. Not
 * creator-scoped: the borders are the country's, not an admin's own rows.
 */
class AdminGovernorateBordersController extends BaseController
{
    public function __invoke(): JsonResponse
    {
        $governorates = Governorate::query()
            ->select(['id', 'slug', 'name', 'boundary'])
            ->orderBy('id')
            ->get()
            ->map(fn (Governorate $governorate) => [
                'id' => $governorate->id,
                'slug' => $governorate->slug,
                'name' => $governorate->getTranslations('name'),
                'geometry' => $governorate->boundary,
            ])
            ->values();

        return response()->json(['governorates' => $governorates]);
    }
}
