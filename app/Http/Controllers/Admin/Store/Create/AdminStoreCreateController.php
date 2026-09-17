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
