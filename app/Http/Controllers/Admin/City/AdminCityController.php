<?php

namespace App\Http\Controllers\Admin\City;

use App\Http\Controllers\Controller as BaseController;
use App\Models\City;
use App\Models\Governorate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Add, rename and delete a city (JSON, called from the city pages). Behind
 * `manage cities` (route middleware) — its own permission, not the
 * governorate's: a city has no owner, so there is no "manage own" variant.
 */
class AdminCityController extends BaseController
{
    /** Tables that point at a city; a delete would silently null those places out. */
    private const REFERENCED_BY = ['facilities', 'facility_branches', 'memberships', 'addresses', 'services'];

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'name' => ['required', 'array'],
            'name.ar' => ['required', 'string', 'max:255'],
            'name.en' => ['required', 'string', 'max:255'],
        ]);

        $governorate = Governorate::findOrFail($data['governorate_id']);
        try {
            $city = City::create([
                'governorate_id' => $governorate->id,
                'name' => ['ar' => $data['name']['ar'], 'en' => $data['name']['en']],
            ]);
        } catch (\Throwable $e) {
            return $this->failed($request, 'Failed to create a city', $e, ['governorate_id' => $governorate->id]);
        }

        Log::info('City created', ['city_id' => $city->id, 'governorate_id' => $governorate->id, 'admin_id' => $request->user()?->id]);

        return response()->json(['id' => $city->id, 'redirect' => route('admin.city.show', $city->id)], 201);
    }

    public function update(Request $request, City $city): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'array'],
            'name.ar' => ['required', 'string', 'max:255'],
            'name.en' => ['required', 'string', 'max:255'],
        ]);

        try {
            $city->update(['name' => ['ar' => $data['name']['ar'], 'en' => $data['name']['en']]]);
        } catch (\Throwable $e) {
            return $this->failed($request, 'Failed to update a city', $e, ['city_id' => $city->id]);
        }

        Log::info('City updated', ['city_id' => $city->id, 'admin_id' => $request->user()?->id]);

        return response()->json(['name' => $city->getTranslations('name')]);
    }

    public function destroy(Request $request, City $city): JsonResponse
    {
        $inUse = [];
        foreach (self::REFERENCED_BY as $table) {
            if (Schema::hasColumn($table, 'city_id') && DB::table($table)->where('city_id', $city->id)->exists()) {
                $inUse[] = $table;
            }
        }

        if ($inUse !== []) {
            Log::warning('City delete refused: still in use', ['city_id' => $city->id, 'tables' => $inUse, 'admin_id' => $request->user()?->id]);

            return response()->json([
                'message' => 'This city is still used by other records ('.implode(', ', $inUse).'), so it cannot be deleted.',
            ], 422);
        }

        try {
            // Its areas go with it (cascade).
            $city->delete();
        } catch (\Throwable $e) {
            return $this->failed($request, 'Failed to delete a city', $e, ['city_id' => $city->id]);
        }

        Log::info('City deleted', ['city_id' => $city->id, 'governorate_id' => $city->governorate_id, 'admin_id' => $request->user()?->id]);

        return response()->json(['redirect' => route('admin.city.list')]);
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
