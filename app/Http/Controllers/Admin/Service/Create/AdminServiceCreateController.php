<?php

namespace App\Http\Controllers\Admin\Service\Create;

use App\Enums\Service\ServiceTagEnum;
use App\Http\Controllers\Controller as BaseController;
use App\Models\City;
use App\Models\Governorate;
use App\Models\ServiceType;
use App\Models\Tag;
use Inertia\Inertia;
use Inertia\Response;

class AdminServiceCreateController extends BaseController
{
    public function __invoke(): Response
    {
        $serviceTypes = ServiceType::active()->get(['id', 'name'])->map(function ($serviceType) {
            return [
                'id' => $serviceType->id,
                'name' => $serviceType->name,
            ];
        });

        $governorates = Governorate::all()->map(function ($governorate) {
            return [
                'id' => $governorate->id,
                'name' => $governorate->name,
            ];
        });

        $cities = City::all()->map(function ($city) {
            return [
                'id' => $city->id,
                'governorate_id' => $city->governorate_id,
                'name' => $city->name,
            ];
        });

        return Inertia::render('Admin/Service/Create/ServiceCreateView', [
            'serviceTypes' => $serviceTypes,
            'governorates' => $governorates,
            'cities' => $cities,
            'tagOptions' => ServiceTagEnum::getOptions(),
            'tags' => Tag::orderBy('name')->get(['id', 'name', 'icon', 'color']),
        ]);
    }
}
