<?php

namespace App\Http\Controllers\Admin\MembershipUsage\Create;

use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityType;
use App\Models\Membership;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminMembershipUsageCreateController extends BaseController
{
    use ScopesByMembershipCreator;

    public function __invoke(Request $request): Response
    {
        $memberships = $this->applyMembershipScope(Membership::with('user'))
            ->orderBy('membership_number')
            ->get()
            ->map(fn($m) => [
                'id'                => $m->id,
                'membership_number' => $m->membership_number,
                'user_name'         => $m->user?->name,
            ]);

        $locale   = app()->getLocale();
        $other    = $locale === 'ar' ? 'en' : 'ar';

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

        return Inertia::render('Admin/MembershipUsage/Create', [
            'memberships'      => $memberships,
            'facilities'       => $facilities,
            'facilityBranches' => $facilityBranches,
            'facilityTypes'    => $facilityTypes,
        ]);
    }
}
