<?php

namespace App\Http\Controllers\Admin\FacilityBranch\Create;

use App\Http\Controllers\Controller as BaseController;
use App\Models\City;
use App\Models\Facility;
use App\Models\Governorate;
use App\Services\BranchGeocoder;
use App\Services\BranchPlaceResolver;
use App\Services\FacilityEnglishBackfiller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminFacilityBranchCreateController extends BaseController
{
    public function __invoke(Request $request): Response
    {
        $facilities = Facility::with(['facilityType'])
            ->withCount('branches')
            ->get()
            ->map(function ($facility) {
                return [
                    'id' => $facility->id,
                    'slug' => $facility->slug,
                    // Both spellings: the form builds the branch name in each
                    // language, and hands the facility name to the AI as context.
                    'name' => $facility->getTranslations('name'),
                    'facility_type' => $facility->facilityType ? [
                        'id' => $facility->facilityType->id,
                        'name' => $facility->facilityType->getTranslations('name'),
                    ] : null,
                    'branches_count' => $facility->branches_count,
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
                // "Add city to name" appends this city's own spelling to each
                // language of the branch name, so both must travel.
                'name' => $city->getTranslations('name'),
            ];
        });

        return Inertia::render('Admin/FacilityBranch/Form/FacilityBranchFormView', [
            'facilities' => $facilities,
            'governorates' => $governorates,
            'cities' => $cities,
            // False when GEMINI_API_KEY is unset: the AI buttons say why they
            // cannot run rather than being offered and then refused.
            'locationAiEnabled' => BranchGeocoder::isConfigured(),
            'englishFixEnabled' => FacilityEnglishBackfiller::isConfigured(),
            'placeAiEnabled' => BranchPlaceResolver::isConfigured(),
        ]);
    }
}



