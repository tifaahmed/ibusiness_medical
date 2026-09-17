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
                'offer_percent_from' => $store->offer_percent_from,
                'offer_percent_to' => $store->offer_percent_to,
                'logo' => $store->logo,
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
            'governorates' => Governorate::query()->orderBy('id')->get()->map(fn (Governorate $g) => [
                'id' => $g->id,
                'name' => $g->getTranslations('name'),
            ]),
            'cities' => City::query()->orderBy('id')->get()->map(fn (City $c) => [
                'id' => $c->id,
                'governorate_id' => $c->governorate_id,
                'name' => $c->getTranslations('name'),
            ]),
        ]);
    }
}
