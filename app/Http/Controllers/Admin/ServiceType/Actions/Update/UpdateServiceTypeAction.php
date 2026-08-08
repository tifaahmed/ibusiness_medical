<?php

namespace App\Http\Controllers\Admin\ServiceType\Actions\Update;

use App\Models\ServiceType;
use Illuminate\Support\Facades\Log;

class UpdateServiceTypeAction
{
    public function execute(ServiceType $serviceType, array $validated): ServiceType
    {
        $updates = [];

        if (isset($validated['name'])) {
            $updates['name'] = $validated['name'];
        }
        if (array_key_exists('description', $validated)) {
            $updates['description'] = $validated['description'];
        }
        if (array_key_exists('icon', $validated)) {
            $updates['icon'] = $validated['icon'];
        }
        if (array_key_exists('color', $validated)) {
            $updates['color'] = $validated['color'];
        }
        if (array_key_exists('is_active', $validated)) {
            $updates['is_active'] = $validated['is_active'];
        }

        if (!empty($updates)) {
            $serviceType->update($updates);
        }

        if (isset($validated['image'])) {
            $serviceType->clearMediaCollection('image');
            $serviceType->addMedia($validated['image'])
                ->toMediaCollection('image');
        }

        Log::info('ServiceType updated', ['id' => $serviceType->id]);

        return $serviceType->refresh();
    }
}
