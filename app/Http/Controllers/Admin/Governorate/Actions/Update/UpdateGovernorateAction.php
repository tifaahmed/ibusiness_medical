<?php

namespace App\Http\Controllers\Admin\Governorate\Actions\Update;

use App\Models\City;
use App\Models\Governorate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateGovernorateAction
{
    /**
     * Execute the action to update a governorate.
     *
     * @param Governorate $governorate
     * @param array $validated
     * @return Governorate
     * @throws \Exception
     */
    public function execute(Governorate $governorate, array $validated): Governorate
    {
        DB::beginTransaction();

        try {
            // Update the governorate
            $governorate->update([
                'name' => $validated['name'],
            ]);

            // Handle cities (create, update, delete)
            if (array_key_exists('cities', $validated)) {
                $this->handleCities($governorate, $validated['cities'] ?? []);
            }

            $governorate->refresh();

            DB::commit();

            Log::info('Governorate updated successfully', [
                'governorate_id' => $governorate->id,
                'governorate_slug' => $governorate->slug,
            ]);

            return $governorate;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update governorate', [
                'governorate_id' => $governorate->id,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle cities CRUD scoped to the governorate.
     *
     * @param Governorate $governorate
     * @param array $citiesData
     * @return void
     */
    private function handleCities(Governorate $governorate, array $citiesData): void
    {
        $keepIds = [];
        foreach ($citiesData as $cityData) {
            if (!empty($cityData['id'])) {
                $keepIds[] = $cityData['id'];
            }
        }

        // Delete cities removed in the request
        $governorate->cities()
            ->whereNotIn('id', $keepIds)
            ->delete();

        // Create or update cities
        foreach ($citiesData as $cityData) {
            $name = $cityData['name'] ?? [];

            if (!empty($cityData['id'])) {
                $city = City::where('id', $cityData['id'])
                    ->where('governorate_id', $governorate->id)
                    ->first();

                if ($city) {
                    $city->update(['name' => $name]);
                }
            } else {
                City::create([
                    'governorate_id' => $governorate->id,
                    'name' => $name,
                ]);
            }
        }
    }
}
