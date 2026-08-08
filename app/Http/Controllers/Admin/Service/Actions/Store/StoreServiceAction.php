<?php

namespace App\Http\Controllers\Admin\Service\Actions\Store;

use App\Models\Service;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreServiceAction
{
    public function execute(array $validated): Service
    {
        DB::beginTransaction();
        try {
            $service = Service::create([
                'category_id' => $validated['category_id'],
                'title' => $validated['title'],
                'short_subject' => $validated['short_subject'] ?? null,
                'subject' => $validated['subject'] ?? null,
                'old_price' => $validated['old_price'] ?? null,
                'new_price' => $validated['new_price'] ?? null,
                'discount_percent' => $validated['discount_percent'] ?? null,
                'governorate_id' => $validated['governorate_id'] ?? null,
                'city_id' => $validated['city_id'] ?? null,
                'iframe_location' => $validated['iframe_location'] ?? null,
                'lat' => $validated['lat'] ?? null,
                'long' => $validated['long'] ?? null,
                'tag' => $validated['tag'] ?? null,
                'created_by' => Auth::id(),
            ]);

            if (isset($validated['image'])) {
                $service->addMedia($validated['image'])
                    ->toMediaCollection('image');
            }

            if (isset($validated['gallery']) && is_array($validated['gallery'])) {
                foreach ($validated['gallery'] as $image) {
                    $service->addMedia($image)
                        ->toMediaCollection('gallery');
                }
            }

            $service->tags()->sync($validated['tag_ids'] ?? []);

            DB::commit();
            Log::info('Service created', ['id' => $service->id, 'slug' => $service->slug]);
            return $service;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create service', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
