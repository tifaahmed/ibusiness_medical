<?php

namespace App\Http\Controllers\Admin\Store\Edit;

use App\Http\Controllers\Controller as BaseController;
use App\Models\City;
use App\Models\Governorate;
use App\Models\Store;
use Inertia\Inertia;
use Inertia\Response;

class AdminStoreEditController extends BaseController
{
    public function __invoke(Store $store): Response
    {
        $store->load(['branches.governorate', 'branches.city', 'galleries']);

        return Inertia::render('Admin/Store/Edit/StoreEditView', [
            'store' => [
                'id' => $store->id,
                'title' => $store->getTranslations('title'),
                'description' => $store->getTranslations('description'),
                'short_description' => $store->getTranslations('short_description'),
                'youtube_link' => $store->youtube_link,
                'online_only' => (bool) $store->online_only,
                'websites' => $store->websites ?? [],
                'app_store_url' => $store->app_store_url,
                'google_play_url' => $store->google_play_url,
                'social_links' => $store->social_links ?? [],
                'coupons' => $store->coupons ?? [],
                'meta_title' => $store->getTranslations('meta_title'),
                'meta_description' => $store->getTranslations('meta_description'),
                'meta_keywords' => $store->getTranslations('meta_keywords'),
                'category_ids' => $store->categories()->pluck('store_categories.id')->all(),
                'tag_ids' => $store->tags()->pluck('tags.id')->all(),
                'supports_shipping' => (bool) $store->supports_shipping,
                'ships_everywhere' => (bool) $store->ships_everywhere,
                'shipping_governorate_ids' => $store->shippingGovernorates()->pluck('governorates.id')->all(),
                'offer_percent_from' => $store->offer_percent_from,
                'offer_percent_to' => $store->offer_percent_to,
                'logo' => $store->logo,
                'seo_image' => $store->seo_image,
                'header' => $store->header,
                'gallery' => $store->gallery,
                'branches' => $store->branches->map(fn (\App\Models\StoreBranch $branch) => [
                    'id' => $branch->id,
                    'governorate_id' => $branch->governorate_id,
                    'city_id' => $branch->city_id,
                    'latitude' => $branch->latitude,
                    'longitude' => $branch->longitude,
                    'google_location_url' => $branch->google_location_url,
                    'name' => $branch->getTranslations('name'),
                    'address' => $branch->getTranslations('address'),
                    'area' => $branch->getTranslations('area'),
                    'phone' => $branch->phone,
                ]),
            ],
            'aiEnabled' => \App\Services\BranchGeocoder::isConfigured(),
            'tags' => \App\Models\Tag::forPicker('stores', $store->tags()->pluck('tags.id')->all()),
            'tagIconOptions' => \App\Enums\Tag\TagEnum::getIconOptions(),
            'tagColorOptions' => \App\Enums\Tag\TagEnum::getColorOptions(),
            'categories' => \App\Models\StoreCategory::query()->withCount('stores')->orderBy('id')->get()->map(fn ($c) => ['id' => $c->id, 'name' => $c->getTranslations('name'), 'stores_count' => $c->stores_count]),
            'governorates' => Governorate::query()->orderBy('id')->get()->map(fn (Governorate $g) => [
                'id' => $g->id,
                'name' => $g->getTranslations('name'),
            ]),
            'cities' => City::query()->withCount('areas')->orderBy('id')->get()->map(fn (City $c) => [
                'id' => $c->id,
                'governorate_id' => $c->governorate_id,
                'areas_count' => $c->areas_count,
                'name' => $c->getTranslations('name'),
            ]),
        ]);
    }
}
