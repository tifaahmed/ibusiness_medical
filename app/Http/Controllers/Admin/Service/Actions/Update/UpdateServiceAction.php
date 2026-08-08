<?php

namespace App\Http\Controllers\Admin\Service\Actions\Update;

use App\Models\Service;
use App\Services\MediaService;
use Illuminate\Support\Facades\Log;

class UpdateServiceAction
{
    private MediaService $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    public function execute(Service $service, array $validated): Service
    {
        $updates = [];

        if (isset($validated['category_id'])) {
            $updates['category_id'] = $validated['category_id'];
        }
        if (isset($validated['title'])) {
            $updates['title'] = $validated['title'];
        }
        if (array_key_exists('short_subject', $validated)) {
            $updates['short_subject'] = $validated['short_subject'];
        }
        if (array_key_exists('subject', $validated)) {
            $updates['subject'] = $validated['subject'];
        }
        if (array_key_exists('old_price', $validated)) {
            $updates['old_price'] = $validated['old_price'];
        }
        if (array_key_exists('new_price', $validated)) {
            $updates['new_price'] = $validated['new_price'];
        }
        if (array_key_exists('discount_percent', $validated)) {
            $updates['discount_percent'] = $validated['discount_percent'];
        }
        if (array_key_exists('governorate_id', $validated)) {
            $updates['governorate_id'] = $validated['governorate_id'];
        }
        if (array_key_exists('city_id', $validated)) {
            $updates['city_id'] = $validated['city_id'];
        }
        if (array_key_exists('iframe_location', $validated)) {
            $updates['iframe_location'] = $validated['iframe_location'];
        }
        if (array_key_exists('lat', $validated)) {
            $updates['lat'] = $validated['lat'];
        }
        if (array_key_exists('long', $validated)) {
            $updates['long'] = $validated['long'];
        }
        if (array_key_exists('tag', $validated)) {
            $updates['tag'] = $validated['tag'];
        }

        if (!empty($updates)) {
            $service->update($updates);
        }

        if (isset($validated['image'])) {
            $service->clearMediaCollection('image');
            $service->addMedia($validated['image'])
                ->toMediaCollection('image');
        }

        if (!empty($validated['deleted_gallery_ids'])) {
            $this->mediaService->deleteMediaByIds($service, $validated['deleted_gallery_ids'], 'gallery');
        }

        if (isset($validated['gallery']) && is_array($validated['gallery'])) {
            foreach ($validated['gallery'] as $image) {
                $service->addMedia($image)
                    ->toMediaCollection('gallery');
            }
        }

        if (array_key_exists('tag_ids', $validated)) {
            $service->tags()->sync($validated['tag_ids'] ?? []);
        }

        Log::info('Service updated', ['id' => $service->id]);

        return $service->refresh();
    }
}
