<?php

namespace App\Http\Controllers\Admin\Store\Show;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Store;
use App\Models\StoreBranch;
use Inertia\Inertia;
use Inertia\Response;

class AdminStoreShowController extends BaseController
{
    public function __invoke(Store $store): Response
    {
        $store->load(['branches.governorate', 'branches.city', 'galleries', 'creator:id,name,email'])
            ->loadCount('products');

        return Inertia::render('Admin/Store/Show', [
            'store' => [
                'id' => $store->id,
                'title' => $store->getTranslations('title'),
                'description' => $store->getTranslations('description'),
                'short_description' => $store->getTranslations('short_description'),
                'youtube_link' => $store->youtube_link,
                'offer_percent_from' => $store->offer_percent_from,
                'offer_percent_to' => $store->offer_percent_to,
                'logo' => $store->logo,
                'header' => $store->header,
                'gallery' => $store->gallery,
                'products_count' => $store->products_count,
                'creator_name' => $store->creator?->name,
                'created_at' => $store->created_at?->format('Y-m-d H:i'),
                'branches' => $store->branches->map(fn (StoreBranch $branch) => [
                    'id' => $branch->id,
                    'name' => $branch->getTranslations('name'),
                    'address' => $branch->getTranslations('address'),
                    'area' => $branch->getTranslations('area'),
                    'governorate' => $branch->governorate?->getTranslations('name'),
                    'city' => $branch->city?->getTranslations('name'),
                    'latitude' => $branch->latitude,
                    'longitude' => $branch->longitude,
                    'google_location_url' => $branch->google_location_url,
                    'phone' => $branch->phone,
                ]),
            ],
        ]);
    }
}
