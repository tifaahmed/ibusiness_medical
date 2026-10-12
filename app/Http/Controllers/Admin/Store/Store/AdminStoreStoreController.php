<?php

namespace App\Http\Controllers\Admin\Store\Store;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Store\StoreStoreRequest;
use App\Models\Store;
use App\Support\ErrorTrace;
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
                'online_only' => (bool) ($validated['online_only'] ?? false),
                'websites' => $validated['websites'] ?? [],
                'app_store_url' => $validated['app_store_url'] ?? null,
                'google_play_url' => $validated['google_play_url'] ?? null,
                'social_links' => $validated['social_links'] ?? [],
                'coupons' => $validated['coupons'] ?? [],
                'meta_title' => array_filter($validated['meta_title'] ?? []) ?: null,
                'meta_description' => array_filter($validated['meta_description'] ?? []) ?: null,
                'meta_keywords' => array_filter($validated['meta_keywords'] ?? []) ?: null,
                'supports_shipping' => $validated['supports_shipping'] ?? false,
                'ships_everywhere' => $validated['ships_everywhere'] ?? true,
                'offer_percent_from' => $validated['offer_percent_from'] ?? null,
                'offer_percent_to' => $validated['offer_percent_to'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $store->categories()->sync($validated['category_ids'] ?? []);
            $store->tags()->sync($validated['tag_ids'] ?? []);
            $store->shippingGovernorates()->sync($validated['shipping_governorate_ids'] ?? []);

            if (! empty($validated['logo'])) {
                $store->addMedia($validated['logo'])->toMediaCollection('logo');
            }

            if (! empty($validated['seo_image'])) {
                $store->addMedia($validated['seo_image'])->toMediaCollection('seo_image');
            }
            $store->ensureSeoImage();

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

            $store->attachEditorImages($validated['editor_gallery_paths'] ?? []);

            DB::commit();

            Log::info('Store created successfully', [
                'store_id' => $store->id,
                'store_slug' => $store->slug,
            ]);

            // "Save & stay" sends `stay`: land back on the edit form.
            if ($request->boolean('stay')) {
                return redirect()->route('admin.store.edit', $store)
                    ->with('success', 'Store created successfully.');
            }

            return redirect()->route('admin.store.list')
                ->with('success', 'Store created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to create store', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to create store. Please try again.'])
                ->with('error_debug', ErrorTrace::from($e))
                ->withInput();
        }
    }
}
