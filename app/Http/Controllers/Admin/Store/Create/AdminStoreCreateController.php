<?php

namespace App\Http\Controllers\Admin\Store\Create;

use App\Http\Controllers\Controller as BaseController;
use App\Models\City;
use App\Models\Governorate;
use Inertia\Inertia;
use Inertia\Response;

class AdminStoreCreateController extends BaseController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Store/Create/StoreCreateView', [
            'aiEnabled' => \App\Services\BranchGeocoder::isConfigured(),
            'tags' => \App\Models\Tag::forPicker('stores'),
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
