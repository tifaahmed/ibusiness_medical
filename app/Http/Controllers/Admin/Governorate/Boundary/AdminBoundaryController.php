<?php

namespace App\Http\Controllers\Admin\Governorate\Boundary;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Area;
use App\Models\City;
use App\Models\Governorate;
use App\Rules\GeoJsonArea;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Saves a border drawn in the map editor (the governorate edit page and the city page). `geometry`
 * null clears the border. The write goes through the query builder: `boundary`
 * is not fillable on purpose, and a model save would re-run the slug generator.
 * A governorate's border follows the governorate permission pair; a city's is
 * behind `manage cities` and an area's behind `manage areas`, on the routes.
 */
class AdminBoundaryController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_GOVERNORATES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_GOVERNORATES; }

    public function governorate(Request $request, Governorate $governorate): JsonResponse
    {
        $this->assertOwns($governorate);

        return $this->store($request, 'governorates', $governorate->id, ['governorate_id' => $governorate->id]);
    }

    public function city(Request $request, City $city): JsonResponse
    {
        return $this->store($request, 'cities', $city->id, ['city_id' => $city->id, 'governorate_id' => $city->governorate_id]);
    }

    public function area(Request $request, Area $area): JsonResponse
    {
        return $this->store($request, 'areas', $area->id, ['area_id' => $area->id, 'city_id' => $area->city_id, 'governorate_id' => $area->governorate_id]);
    }

    private function store(Request $request, string $table, int $id, array $context): JsonResponse
    {
        $validated = $request->validate([
            'geometry' => ['present', 'nullable', new GeoJsonArea],
        ]);

        try {
            DB::table($table)->where('id', $id)->update([
                'boundary' => $validated['geometry'] === null ? null : json_encode($validated['geometry']),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to save a border', $context + [
                'table' => $table,
                'admin_id' => $request->user()?->id,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'The border could not be saved.'], 500);
        }

        Log::info('Border saved', $context + [
            'table' => $table,
            'admin_id' => $request->user()?->id,
            'cleared' => $validated['geometry'] === null,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['geometry' => $validated['geometry']]);
    }
}
