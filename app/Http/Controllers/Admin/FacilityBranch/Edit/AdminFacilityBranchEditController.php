<?php

namespace App\Http\Controllers\Admin\FacilityBranch\Edit;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\FacilityBranch\Edit\AdminFacilityBranchEditResource;
use App\Models\City;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\Governorate;
use App\Services\BranchGeocoder;
use App\Services\BranchPlaceResolver;
use App\Services\FacilityEnglishBackfiller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminFacilityBranchEditController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_FACILITY_BRANCHES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_FACILITY_BRANCHES;
    }

    /**
     * Show the form for editing the specified facility branch.
     */
    public function __invoke(Request $request, string $facilityBranch): Response
    {
        $facilityBranch = FacilityBranch::where('slug', $facilityBranch)->firstOrFail();
        $this->assertOwns($facilityBranch);

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

        $result = [
            'facilityBranch' => (new AdminFacilityBranchEditResource($facilityBranch))->toArray($request),
            'facilities' => $facilities,
            'governorates' => $governorates,
            'cities' => $cities,
            // False when GEMINI_API_KEY is unset: the AI buttons say why they
            // cannot run rather than being offered and then refused.
            'locationAiEnabled' => BranchGeocoder::isConfigured(),
            'englishFixEnabled' => FacilityEnglishBackfiller::isConfigured(),
            'placeAiEnabled' => BranchPlaceResolver::isConfigured(),
        ];

        return Inertia::render('Admin/FacilityBranch/Form/FacilityBranchFormView', $result);
    }
}
