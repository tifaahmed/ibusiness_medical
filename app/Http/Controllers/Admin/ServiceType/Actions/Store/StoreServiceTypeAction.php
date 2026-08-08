<?php

namespace App\Http\Controllers\Admin\ServiceType\Actions\Store;

use App\Models\ServiceType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreServiceTypeAction
{
    public function execute(array $validated): ServiceType
    {
        DB::beginTransaction();
        try {
            $serviceType = ServiceType::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'icon' => $validated['icon'] ?? null,
                'color' => $validated['color'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'created_by' => Auth::id(),
            ]);

            if (isset($validated['image'])) {
                $serviceType->addMedia($validated['image'])
                    ->toMediaCollection('image');
            }

            DB::commit();
            Log::info('ServiceType created', ['id' => $serviceType->id]);
            return $serviceType;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create ServiceType', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
