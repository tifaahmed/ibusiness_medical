<?php

namespace App\Http\Controllers\Admin\MemberPayment\Update;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\MemberPayment\UpdateMemberPaymentRequest;
use App\Models\MemberPayment;
use App\Models\Membership;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class AdminMemberPaymentUpdateController extends BaseController
{
    use \App\Http\Controllers\Concerns\ScopesByMembershipCreator;

    public function __invoke(MemberPayment $memberPayment, UpdateMemberPaymentRequest $request): RedirectResponse
    {
        if ($this->scopesPaymentsToPartner()) {
            $adminPartnerId = $this->currentAdminPaymentPartnerId();
            if ($adminPartnerId === null || (int) $memberPayment->membership->partner_id !== $adminPartnerId) {
                abort(403);
            }
            $membership = Membership::findOrFail($request->membership_id);
            if ((int) $membership->partner_id !== $adminPartnerId) {
                abort(403, 'You can only update payments for memberships belonging to your partner.');
            }
        }

        $membershipId = $memberPayment->membership_id;
        $oldToDate = $memberPayment->to_date?->copy();

        $data = $request->validated();
        if (($data['type'] ?? 'commission') === 'free') {
            $data['amount'] = 0;
        }
        $memberPayment->update($data);
        $memberPayment->refresh();

        $newToDate = $memberPayment->to_date;

        if ($oldToDate && $newToDate && !$oldToDate->equalTo($newToDate)) {
            $diffInDays = $newToDate->diffInDays($oldToDate);
            if ($newToDate->lessThan($oldToDate)) {
                $diffInDays = -$diffInDays;
            }

            if ($diffInDays !== 0) {
                $interval = ($diffInDays >= 0 ? '+' : '') . $diffInDays;

                DB::table('member_payments')
                    ->where('membership_id', $membershipId)
                    ->where('id', '!=', $memberPayment->id)
                    ->where('from_date', '>', $oldToDate->toDateString())
                    ->update([
                        'from_date' => DB::raw("DATE_ADD(from_date, INTERVAL {$interval} DAY)"),
                        'to_date' => DB::raw("DATE_ADD(to_date, INTERVAL {$interval} DAY)"),
                    ]);
            }
        }

        return redirect()->route('admin.member-payment.list')
            ->with('success', __('admin.member_payment.updated'));
    }
}
