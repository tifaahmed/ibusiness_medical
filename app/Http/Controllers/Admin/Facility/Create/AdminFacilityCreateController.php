<?php

namespace App\Http\Controllers\Admin\Facility\Create;

use App\Http\Controllers\Controller as BaseController;
use App\Models\City;
use App\Models\FacilityType;
use App\Models\Governorate;
use App\Models\Sales;
use App\Models\Tag;
use App\Services\FacilitySeoGenerator;
use Inertia\Inertia;
use Inertia\Response;

class AdminFacilityCreateController extends BaseController
{
    public function __invoke(): Response
    {
        $facilityTypes = FacilityType::all()->map(function ($type) {
            return [
                'id' => $type->id,
                'name' => $type->name,
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

        $salesOptions = Sales::query()
            ->orderBy('id')
            ->get()
            ->map(fn (Sales $sale) => [
                'value' => $sale->id,
                'label' => $sale->getTranslation('name', app()->getLocale())
                    ?: $sale->getTranslation('name', 'ar')
                    ?: $sale->getTranslation('name', 'en')
                    ?: "#{$sale->id}",
            ])->toArray();

        $tags = Tag::orderBy('name')->get(['id', 'name', 'icon', 'color']);

        return Inertia::render('Admin/Facility/Create/FacilityCreateView', [
            'facilityTypes' => $facilityTypes,
            'governorates' => $governorates,
            'cities' => $cities,
            'tags' => $tags,
            'salesOptions' => $salesOptions,
            'seoAiEnabled' => FacilitySeoGenerator::isConfigured(),
        ]);
    }
}
