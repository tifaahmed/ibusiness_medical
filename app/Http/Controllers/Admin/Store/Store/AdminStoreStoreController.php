<?php

namespace App\Http\Controllers\Admin\Store\Store;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Store\StoreStoreRequest;
use App\Models\Store;
use App\Models\StoreBranch;
use App\Models\StoreGallery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminStoreStoreController extends BaseController
{
    public function __invoke(StoreStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $store = Store::create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'short_description' => $validated['short_description'] ?? null,
                'youtube_link' => $validated['youtube_link'] ?? null,
                'offer_percent_from' => $validated['offer_percent_from'] ?? null,
                'offer_percent_to' => $validated['offer_percent_to'] ?? null,
                'created_by' => Auth::id(),
            ]);

            if (! empty($validated['logo'])) {
                $store->addMedia($validated['logo'])->toMediaCollection('logo');
            }

            if (! empty($validated['header'])) {
                $store->addMedia($validated['header'])->toMediaCollection('header');
            }

            foreach ($validated['branches'] ?? [] as $branch) {
                StoreBranch::create([
                    'store_id' => $store->id,
                    'governorate_id' => $branch['governorate_id'],
                    'city_id' => $branch['city_id'],
                    'latitude' => $branch['latitude'] ?? null,
                    'longitude' => $branch['longitude'] ?? null,
                    'google_location_url' => $branch['google_location_url'] ?? null,
                    'name' => $branch['name'] ?? null,
                    'address' => $branch['address'] ?? null,
                    'area' => $branch['area'] ?? null,
                    'phone' => $branch['phone'] ?? [],
                    'created_by' => Auth::id(),
                ]);
            }

            $sortOrder = 0;
            foreach ($validated['gallery_images'] ?? [] as $image) {
                $path = $image->store("stores/gallery/{$store->id}", 'public');
                StoreGallery::create([
                    'store_id' => $store->id,
                    'media_path' => $path,
                    'type' => StoreGallery::TYPE_IMAGE,
                    'sort_order' => $sortOrder++,
                ]);
            }
            foreach ($validated['gallery_videos'] ?? [] as $video) {
                $path = $video->store("stores/gallery/{$store->id}", 'public');
                StoreGallery::create([
                    'store_id' => $store->id,
                    'media_path' => $path,
                    'type' => StoreGallery::TYPE_VIDEO,
                    'sort_order' => $sortOrder++,
                ]);
            }

            DB::commit();

            Log::info('Store created successfully', [
                'store_id' => $store->id,
                'store_slug' => $store->slug,
            ]);

            return redirect()->route('admin.store.list')
                ->with('success', 'Store created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create store', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to create store. Please try again.'])
                ->withInput();
        }
    }
}
