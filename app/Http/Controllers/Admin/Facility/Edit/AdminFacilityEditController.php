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
use App\Services\BranchGeocoder;
use App\Services\FacilityEnglishBackfiller;
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
        $facility = Facility::with(['branches.creator', 'media', 'tags', 'managers'])->where('slug', $facility)->firstOrFail();
        $this->assertOwns($facility);

        // Both spellings, not the reader's one.
        //
        // `$type->name` resolves to the current locale and throws the other
        // language away, which cost the form two things: a picker could only
        // ever show one language, and "Add city to name (AR)" sat permanently
        // disabled — it looks for an Arabic spelling in a value that, under an
        // English locale, is only ever the English one. The components have
        // always read a {ar, en} map when they are given one.
        $facilityTypes = FacilityType::all()->map(function ($type) {
            return [
                'id' => $type->id,
                'name' => $type->getTranslations('name'),
            ];
        });

        $governorates = Governorate::all()->map(function ($governorate) {
            return [
                'id' => $governorate->id,
                'name' => $governorate->getTranslations('name'),
            ];
        });

        $cities = City::all()->map(function ($city) {
            return [
                'id' => $city->id,
                'governorate_id' => $city->governorate_id,
                'name' => $city->getTranslations('name'),
            ];
        });

        $resourceArray = (new AdminFacilityEditResource($facility))->toArray($request);

        $salesOptions = Sales::query()
            ->orderBy('id')
            ->get()
            // The label stays a single readable string for callers that only
            // want one; `name` carries both the way every other lookup does.
            // A rep whose name column holds a bare varchar reads the same in
            // both — see Sales::nameTranslations().
            ->map(fn (Sales $sale) => [
                'value' => $sale->id,
                'label' => $sale->displayName(),
                'name' => $sale->nameTranslations(),
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
            'locationAiEnabled' => BranchGeocoder::isConfigured(),
            'englishFixEnabled' => FacilityEnglishBackfiller::isConfigured(),
        ];

        return Inertia::render('Admin/Facility/Edit/FacilityEditView', $result);
    }
}
