<?php

namespace App\Http\Controllers\Admin\MemberPayment\List;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\MemberPayment\List\AdminMemberPaymentListResource;
use App\Models\MemberPayment;
use App\Models\Membership;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Response;

class AdminMemberPaymentListController extends BaseController
{
    use \App\Http\Controllers\Concerns\ScopesByMembershipCreator;

    public function __invoke(Request $request): Response
    {
        $query = MemberPayment::query()
            ->with(['membership.user', 'membership.partner']);

        // Partner scope: restrict to memberships within the admin's partner scope
        $partnerFilter = $this->scopesPaymentsToPartner();
        $adminPartnerId = $this->currentAdminPaymentPartnerId();
        if ($partnerFilter) {
            $query->whereHas('membership', function ($mq) use ($adminPartnerId) {
                $mq->where('partner_id', $adminPartnerId ?? -1);
            });
        }

        $query
            ->when($request->search, fn ($q, $v) => $q->whereHas('membership', fn ($sq) => $sq
                ->where('membership_number', 'like', "%{$v}%")
                ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$v}%"))
            ))
            ->when($request->name, fn ($q, $v) => $q->whereHas('membership.user', fn ($uq) => $uq
                ->where('name', 'like', "%{$v}%")
            ))
            ->when($request->membership_number, fn ($q, $v) => $q->whereHas('membership', fn ($sq) => $sq
                ->where('membership_number', 'like', "%{$v}%")
            ))
            ->when($request->partner_id, fn ($q, $v) => $q->whereHas('membership', fn ($sq) => $sq
                ->where('partner_id', $v)
            ))
            ->when($request->phone, fn ($q, $v) => $q->whereHas('membership.user', fn ($uq) => $uq
                ->where('phone', 'like', "%{$v}%")
            ))
            ->when($request->email, fn ($q, $v) => $q->whereHas('membership.user', fn ($uq) => $uq
                ->where('email', 'like', "%{$v}%")
            ))
            ->when($request->type, fn ($q, $v) => $q->where('type', $v))
            ->orderBy('created_at', 'desc');

        $perPage = min((int) $request->get('per_page', 15), 100);

        $partners = Partner::query()
            ->whereIn('id', Membership::query()
                ->whereNotNull('partner_id')
                ->when($partnerFilter, fn ($q) => $q->where('id', $adminPartnerId ?? -1))
                ->select('partner_id'))
            ->orderBy('title')
            ->get()
            ->map(fn ($p) => [
                'value' => $p->id,
                'label' => $p->title,
            ]);

        $userNames = User::query()
            ->whereNull('deleted_at')
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->whereHas('memberships.memberPayments')
            ->orderBy('name')
            ->limit(2000)
            ->get(['id', 'name', 'slug'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'slug' => $u->slug])
            ->values()
            ->toArray();

        return inertia('Admin/MemberPayment/List', [
            'payments'      => AdminMemberPaymentListResource::collection($query->paginate($perPage)),
            'partnerOptions' => $partners,
            'userNames'      => $userNames,
        ]);
    }
}
