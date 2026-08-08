<?php

namespace App\Http\Controllers\Admin\Governorate\Actions\Store;

use App\Models\Governorate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreGovernorateAction
{
    /**
     * Execute the action to store a governorate.
     *
     * @param array $validated
     * @return Governorate
     * @throws \Exception
     */
    public function execute(array $validated): Governorate
    {
        DB::beginTransaction();

        try {
            // Create the governorate
            $governorate = Governorate::create([
                'name' => $validated['name'],
                'created_by' => Auth::id(),
            ]);

            DB::commit();

            Log::info('Governorate created successfully', [
                'governorate_id' => $governorate->id,
                'governorate_slug' => $governorate->slug,
            ]);

            return $governorate;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create governorate', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}



