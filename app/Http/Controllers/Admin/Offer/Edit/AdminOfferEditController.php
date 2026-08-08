<?php

namespace App\Http\Controllers\Admin\Offer\Edit;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Offer\Edit\AdminOfferEditResource;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\Offer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminOfferEditController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_OFFERS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_OFFERS; }

    /**
     * Show the form for editing the specified offer.
     */
    public function __invoke(Request $request, string $offer): Response
    {
        $offer = Offer::where('slug', $offer)->firstOrFail();
        $this->assertOwns($offer);

        $facilities = Facility::with(['facilityType'])
            ->withCount('branches')
            ->get()
            ->map(function ($facility) {
                return [
                    'id' => $facility->id,
                    'name' => $facility->name,
                    'type' => 'App\\Models\\Facility',
                    'facility_type' => $facility->facilityType ? [
                        'id' => $facility->facilityType->id,
                        'name' => $facility->facilityType->name,
                    ] : null,
                    'branches_count' => $facility->branches_count,
                ];
            });

        $facilityBranches = FacilityBranch::with(['facility.facilityType', 'governorate', 'city'])
            ->get()
            ->map(function ($facilityBranch) {
                return [
                    'id' => $facilityBranch->id,
                    'name' => $facilityBranch->name,
                    'address' => $facilityBranch->address,
                    'type' => 'App\\Models\\FacilityBranch',
                    'facility' => $facilityBranch->facility ? [
                        'id' => $facilityBranch->facility->id,
                        'name' => $facilityBranch->facility->name,
                        'facility_type' => $facilityBranch->facility->facilityType ? [
                            'id' => $facilityBranch->facility->facilityType->id,
                            'name' => $facilityBranch->facility->facilityType->name,
                        ] : null,
                    ] : null,
                    'governorate' => $facilityBranch->governorate ? [
                        'id' => $facilityBranch->governorate->id,
                        'name' => $facilityBranch->governorate->name,
                    ] : null,
                    'city' => $facilityBranch->city ? [
                        'id' => $facilityBranch->city->id,
                        'name' => $facilityBranch->city->name,
                    ] : null,
                ];
            });

        $result = [
            'offer' => (new AdminOfferEditResource($offer))->toArray($request),
            'facilities' => $facilities,
            'facilityBranches' => $facilityBranches,
        ];

        return Inertia::render('Admin/Offer/Form/OfferFormView', $result);
    }
}
