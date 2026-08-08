<?php

namespace App\Http\Controllers\Admin\MembershipUsage\Edit;

use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\MembershipUsage\Show\AdminMembershipUsageShowResource;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityType;
use App\Models\Membership;
use App\Models\MembershipUsage;
use Inertia\Inertia;
use Inertia\Response;

class AdminMembershipUsageEditController extends BaseController
{
    use ScopesByMembershipCreator;

    public function __invoke(MembershipUsage $membershipUsage): Response
    {
        $membershipUsage->load(['membership.user', 'membership.creator:id,name,email', 'facility', 'facilityBranch', 'facilityType']);
        if ($membershipUsage->membership) {
            $this->assertCanManageMembership($membershipUsage->membership);
        }

        $memberships = $this->applyMembershipScope(Membership::with('user'))
            ->orderBy('membership_number')
            ->get()
            ->map(fn($m) => [
                'id'                => $m->id,
                'membership_number' => $m->membership_number,
                'user_name'         => $m->user?->name,
            ]);

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

        return Inertia::render('Admin/MembershipUsage/Edit', [
            'usage'            => (new AdminMembershipUsageShowResource($membershipUsage))->resolve(),
            'memberships'      => $memberships,
            'facilities'       => $facilities,
            'facilityBranches' => $facilityBranches,
            'facilityTypes'    => $facilityTypes,
        ]);
    }
}
