<?php

namespace App\Http\Controllers\Admin\Area;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Area;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Add, rename and delete an area of a city (JSON, called from the city page).
 * The area's border goes through AdminBoundaryController. Behind `manage areas`
 * (route middleware), its own permission apart from the governorates'.
 *
 * An area added here has no CAPMAS code, so it gets a generated `M…` one (the
 * imported ones are `EG…`) — `pcode` is the seeder's upsert key and must stay
 * unique, and a made-up code can never collide with a real one.
 */
class AdminAreaController extends BaseController
{
    public function store(Request $request, City $city): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'array'],
            'name.ar' => ['required', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],
            'pcode' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/', 'unique:areas,pcode'],
        ]);

        $pcode = strtoupper($data['pcode'] ?? '') ?: 'M'.strtoupper(Str::random(8));

        try {
            $area = Area::create([
                'governorate_id' => $city->governorate_id,
                'city_id' => $city->id,
                'name' => array_filter(['ar' => $data['name']['ar'], 'en' => $data['name']['en'] ?? null]),
                'pcode' => $pcode,
                'slug' => strtolower($pcode),
            ]);
        } catch (\Throwable $e) {
            return $this->failed($request, 'Failed to create an area', $e, ['city_id' => $city->id, 'pcode' => $pcode]);
        }

        Log::info('Area created', ['area_id' => $area->id, 'city_id' => $city->id, 'admin_id' => $request->user()?->id]);

        return response()->json($this->row($area, false), 201);
    }

    public function update(Request $request, Area $area): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'array'],
            'name.ar' => ['required', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $area->update(['name' => array_filter(['ar' => $data['name']['ar'], 'en' => $data['name']['en'] ?? null])]);
        } catch (\Throwable $e) {
            return $this->failed($request, 'Failed to update an area', $e, ['area_id' => $area->id]);
        }

        Log::info('Area updated', ['area_id' => $area->id, 'admin_id' => $request->user()?->id]);

        return response()->json($this->row($area, $area->boundary !== null));
    }

    public function destroy(Request $request, Area $area): JsonResponse
    {
        // Branches that named this area would silently lose it (the FK nulls out).
        if ($area->branches()->exists()) {
            Log::warning('Area delete refused: still named by branches', ['area_id' => $area->id, 'admin_id' => $request->user()?->id]);

            return response()->json(['message' => 'Facility branches still use this area, so it cannot be deleted.'], 422);
        }

        try {
            $area->delete();
        } catch (\Throwable $e) {
            return $this->failed($request, 'Failed to delete an area', $e, ['area_id' => $area->id]);
        }

        Log::info('Area deleted', ['area_id' => $area->id, 'city_id' => $area->city_id, 'pcode' => $area->pcode, 'admin_id' => $request->user()?->id]);

        return response()->json(['id' => $area->id]);
    }

    private function row(Area $area, bool $hasBorder): array
    {
        return [
            'id' => $area->id,
            'pcode' => $area->pcode,
            'name' => $area->getTranslations('name'),
            'has_border' => $hasBorder,
        ];
    }

    private function failed(Request $request, string $message, \Throwable $e, array $context): JsonResponse
    {
        Log::error($message, $context + [
            'admin_id' => $request->user()?->id,
            'error_message' => $e->getMessage(),
            'error_trace' => $e->getTraceAsString(),
        ]);

        return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
    }
}
