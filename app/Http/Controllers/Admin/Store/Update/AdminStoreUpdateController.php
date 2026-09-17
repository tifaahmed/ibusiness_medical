<?php

namespace App\Http\Controllers\Admin\Store\Update;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Store\UpdateStoreRequest;
use App\Models\Store;
use App\Models\StoreBranch;
use App\Models\StoreGallery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AdminStoreUpdateController extends BaseController
{
    public function __invoke(UpdateStoreRequest $request, Store $store): RedirectResponse
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $store->update([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'short_description' => $validated['short_description'] ?? null,
                'youtube_link' => $validated['youtube_link'] ?? null,
                'offer_percent_from' => $validated['offer_percent_from'] ?? null,
                'offer_percent_to' => $validated['offer_percent_to'] ?? null,
            ]);

            if (! empty($validated['logo'])) {
                $store->addMedia($validated['logo'])->toMediaCollection('logo');
            } elseif (filter_var($validated['logo_delete'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $store->clearMediaCollection('logo');
            }

            if (! empty($validated['header'])) {
                $store->addMedia($validated['header'])->toMediaCollection('header');
            } elseif (filter_var($validated['header_delete'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $store->clearMediaCollection('header');
            }

            // Branches carry no independent identity anything else references
            // (no logs, no offers), so the simplest correct update is to
            // replace the set wholesale with whatever the form now holds.
            $store->branches()->delete();
            foreach ($validated['branches'] ?? [] as $branch) {
                StoreBranch::create([
                    'store_id' => $store->id,
                    'governorate_id' => $branch['governorate_id'] ?? null,
                    'city_id' => $branch['city_id'] ?? null,
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

            if (! empty($validated['gallery_delete'])) {
                $toDelete = StoreGallery::query()
                    ->where('store_id', $store->id)
                    ->whereIn('id', $validated['gallery_delete'])
                    ->get();
                foreach ($toDelete as $item) {
                    Storage::disk('public')->delete($item->media_path);
                    $item->delete();
                }
            }

            $sortOrder = (int) StoreGallery::query()->where('store_id', $store->id)->max('sort_order') + 1;
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

            Log::info('Store updated successfully', [
                'store_id' => $store->id,
                'store_slug' => $store->slug,
            ]);

            return redirect()->route('admin.store.list')
                ->with('success', 'Store updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update store', [
                'store_id' => $store->id,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to update store. Please try again.'])
                ->withInput();
        }
    }
}
