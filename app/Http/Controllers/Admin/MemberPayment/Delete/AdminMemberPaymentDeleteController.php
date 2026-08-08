<?php

namespace App\Http\Controllers\Admin\MemberPayment\Delete;

use App\Http\Controllers\Controller as BaseController;
use App\Models\MemberPayment;
use Illuminate\Http\RedirectResponse;

class AdminMemberPaymentDeleteController extends BaseController
{
    use \App\Http\Controllers\Concerns\ScopesByMembershipCreator;

    public function __invoke(MemberPayment $memberPayment): RedirectResponse
    {
        if ($this->scopesPaymentsToPartner()) {
            $adminPartnerId = $this->currentAdminPaymentPartnerId();
            if ($adminPartnerId === null || (int) $memberPayment->membership->partner_id !== $adminPartnerId) {
                abort(403);
            }
        }

        $memberPayment->delete();

        return redirect()->route('admin.member-payment.list')
            ->with('success', __('admin.member_payment.deleted'));
    }
}
