<?php

namespace App\Http\Controllers\Admin\Facility\Edit;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Facility\Edit\AdminFacilityEditResource;
use App\Models\City;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Governorate;
use App\Models\Sales;
use App\Models\Tag;
use App\Services\FacilitySeoGenerator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminFacilityEditController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_FACILITIES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_FACILITIES;
    }

    /**
     * Show the form for editing the specified facility.
     */
    public function __invoke(Request $request, string $facility): Response
    {
        $facility = Facility::with(['branches', 'media', 'tags', 'managers'])->where('slug', $facility)->firstOrFail();
        $this->assertOwns($facility);

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

        $resourceArray = (new AdminFacilityEditResource($facility))->toArray($request);
        \Illuminate\Support\Facades\Log::error('AdminFacilityEditResource response', [
            'keys' => array_keys($resourceArray),
            'logo' => $resourceArray['logo'] ?? 'MISSING',
            'mobile_logo' => $resourceArray['mobile_logo'] ?? 'MISSING',
            'image' => $resourceArray['image'] ?? 'MISSING',
            'mobile_image' => $resourceArray['mobile_image'] ?? 'MISSING',
            '_debug_mobile_logo' => $resourceArray['_debug_mobile_logo'] ?? 'MISSING',
            '_debug_mobile_image' => $resourceArray['_debug_mobile_image'] ?? 'MISSING',
            'slug' => $facility->slug,
            'media_count' => $facility->media->count(),
        ]);

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

        $tags = Tag::forPicker();

        $result = [
            'facility' => $resourceArray,
            'facilityTypes' => $facilityTypes,
            'governorates' => $governorates,
            'cities' => $cities,
            'tags' => $tags,
            'salesOptions' => $salesOptions,
            'seoAiEnabled' => FacilitySeoGenerator::isConfigured(),
        ];

        return Inertia::render('Admin/Facility/Edit/FacilityEditView', $result);
    }
}
