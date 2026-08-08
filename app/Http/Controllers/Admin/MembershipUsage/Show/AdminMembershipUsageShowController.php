<?php

namespace App\Http\Controllers\Admin\MembershipUsage\Show;

use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\MembershipUsage\Show\AdminMembershipUsageShowResource;
use App\Models\MembershipUsage;
use Inertia\Inertia;
use Inertia\Response;

class AdminMembershipUsageShowController extends BaseController
{
    use ScopesByMembershipCreator;

    public function __invoke(MembershipUsage $membershipUsage): Response
    {
        $membershipUsage->load(['membership.user', 'membership.creator:id,name,email', 'facility', 'facilityBranch', 'facilityType']);
        if ($membershipUsage->membership) {
            $this->assertCanManageMembership($membershipUsage->membership);
        }

        return Inertia::render('Admin/MembershipUsage/Show', [
            'usage' => (new AdminMembershipUsageShowResource($membershipUsage))->resolve(),
        ]);
    }
}
