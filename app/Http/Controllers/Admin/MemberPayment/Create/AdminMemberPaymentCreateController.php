<?php

namespace App\Http\Controllers\Admin\MemberPayment\Create;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Membership;
use Inertia\Inertia;
use Inertia\Response;

class AdminMemberPaymentCreateController extends BaseController
{
    use \App\Http\Controllers\Concerns\ScopesByMembershipCreator;

    public function __invoke(): Response
    {
        $partnerFilter = $this->scopesPaymentsToPartner();
        $adminPartnerId = $this->currentAdminPaymentPartnerId();

        $memberships = Membership::with(['user', 'memberPayments' => fn ($q) => $q->orderBy('created_at', 'desc')])
            ->whereNotNull('completed_at')
            ->when($partnerFilter, fn ($q) => $q->where('partner_id', $adminPartnerId ?? -1))
            ->orderBy('membership_number')
            ->get()
            ->map(fn ($m) => [
                'id'                => $m->id,
                'membership_number' => $m->membership_number,
                'slug'              => $m->user?->slug,
                'user_id'           => $m->user?->id,
                'user_name'         => $m->user?->name,
                'payment_type'      => $m->payment_type,
                'registration_date' => $m->registration_date?->format('Y-m-d'),
                'expiration_date'   => $m->expiration_date?->format('Y-m-d'),
                'is_active'         => $m->is_active,
                'is_paid'           => $m->is_paid,
                'payments'          => $m->memberPayments->map(fn ($p) => [
                    'id'          => $p->id,
                    'amount'      => (float) $p->amount,
                    'months_paid' => $p->months_paid,
                    'from_date'   => $p->from_date?->format('Y-m-d'),
                    'to_date'     => $p->to_date?->format('Y-m-d'),
                    'notes'       => $p->notes,
                ]),
            ]);

        return Inertia::render('Admin/MemberPayment/Create', [
            'memberships' => $memberships,
        ]);
    }
}
