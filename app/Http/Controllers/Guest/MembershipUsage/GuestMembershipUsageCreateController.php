<?php

namespace App\Http\Controllers\Guest\MembershipUsage;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityType;
use App\Models\Membership;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GuestMembershipUsageCreateController extends BaseController
{
    public function __invoke(Request $request, string $membership): Response
    {
        $membershipModel = Membership::visible()
            ->where(function ($query) use ($membership) {
                $query->where('slug', $membership)
                    ->orWhere('membership_number', $membership);
            })
            ->firstOrFail();

        $locale = app()->getLocale();
        $other  = $locale === 'ar' ? 'en' : 'ar';

        $facilities = Facility::with('facilityType')
            ->orderBy('id')
            ->get()
            ->map(fn($f) => [
                'id'               => $f->id,
                'name'             => $f->getTranslation('name', $locale) ?: $f->getTranslation('name', $other),
                'facility_type_id' => $f->facility_type_id,
            ]);

        $facilityBranches = FacilityBranch::orderBy('facility_id')
            ->get()
            ->map(fn($b) => [
                'id'          => $b->id,
                'name'        => $b->getTranslation('name', $locale) ?: $b->getTranslation('name', $other),
                'facility_id' => $b->facility_id,
            ]);

        $facilityTypes = FacilityType::orderBy('id')
            ->get()
            ->map(fn($t) => [
                'id'   => $t->id,
                'name' => $t->getTranslation('name', $locale) ?: $t->getTranslation('name', $other),
            ]);

        return Inertia::render('Guest/MembershipUsage/Create', [
            'membership'       => [
                'id'                => $membershipModel->id,
                'membership_number' => $membershipModel->membership_number,
                'slug'              => $membershipModel->slug,
            ],
            'facilities'       => $facilities,
            'facilityBranches' => $facilityBranches,
            'facilityTypes'    => $facilityTypes,
        ]);
    }
}
