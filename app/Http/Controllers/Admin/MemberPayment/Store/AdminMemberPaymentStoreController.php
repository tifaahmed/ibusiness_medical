<?php

namespace App\Http\Controllers\Admin\MemberPayment\Store;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\MemberPayment\StoreMemberPaymentRequest;
use App\Models\MemberPayment;
use App\Models\Membership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class AdminMemberPaymentStoreController extends BaseController
{
    use \App\Http\Controllers\Concerns\ScopesByMembershipCreator;

    public function __invoke(StoreMemberPaymentRequest $request): RedirectResponse
    {
        $partnerFilter = $this->scopesPaymentsToPartner();
        $adminPartnerId = $this->currentAdminPaymentPartnerId();

        if ($partnerFilter) {
            $membership = Membership::findOrFail($request->membership_id);
            if ((int) $membership->partner_id !== $adminPartnerId) {
                abort(403, 'You can only add payments for memberships belonging to your partner.');
            }
        }

        $data = $request->validated();
        if (($data['type'] ?? 'commission') === 'free') {
            $data['amount'] = 0;
        }
        MemberPayment::create($data);

        return redirect()->route('admin.member-payment.list')
            ->with('success', __('admin.member_payment.created'));
    }
}
