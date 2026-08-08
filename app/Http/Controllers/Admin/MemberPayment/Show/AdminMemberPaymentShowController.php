<?php

namespace App\Http\Controllers\Admin\MemberPayment\Show;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\MemberPayment\Show\AdminMemberPaymentShowResource;
use App\Models\MemberPayment;
use Inertia\Response;

class AdminMemberPaymentShowController extends BaseController
{
    use \App\Http\Controllers\Concerns\ScopesByMembershipCreator;

    public function __invoke(MemberPayment $memberPayment): Response
    {
        if ($this->scopesPaymentsToPartner()) {
            $adminPartnerId = $this->currentAdminPaymentPartnerId();
            if ($adminPartnerId === null || (int) $memberPayment->membership->partner_id !== $adminPartnerId) {
                abort(403);
            }
        }

        $memberPayment->load(['membership.user', 'membership.partner']);

        return inertia('Admin/MemberPayment/Show', [
            'payment' => new AdminMemberPaymentShowResource($memberPayment),
        ]);
    }
}
