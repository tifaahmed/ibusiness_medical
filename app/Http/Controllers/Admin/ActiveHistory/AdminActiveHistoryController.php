<?php

namespace App\Http\Controllers\Admin\ActiveHistory;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Models\MemberActiveHistory;
use App\Models\Membership;
use App\Models\Partner;
use App\Models\Sales;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AdminActiveHistoryController extends BaseController
{
    use ScopesByMembershipCreator;

    public function __invoke(Request $request): Response
    {
        $filters = $this->getFilters($request);

        $creatorFilter = $this->scopesMembershipsToCreator() ? (int) Auth::id() : null;
        $partnerIdFilter = $this->currentAdminPartnerId();
        $restricted = $this->isMembershipScopeRestricted();

        $listingFilter = function ($mq) use ($creatorFilter, $partnerIdFilter) {
            $mq->where(function ($w) use ($creatorFilter, $partnerIdFilter) {
                if ($creatorFilter !== null) {
                    $w->orWhere('created_by', $creatorFilter);
                }
                if ($partnerIdFilter !== null) {
                    $w->orWhere('partner_id', $partnerIdFilter);
                }
            });
        };

        $historiesQuery = MemberActiveHistory::query()
            ->with(['membership.user:id,name,email,phone,slug', 'membership.partner:id,title', 'membership.sales:id,name', 'changer:id,name,email'])
            ->whereHas('membership', function ($mq) use ($filters, $listingFilter, $restricted) {
                if ($restricted) {
                    $listingFilter($mq);
                }
                $mq->when(!empty($filters['search']), fn($q) => $q->where(function ($wq) use ($filters) {
                    $wq->where('membership_number', 'like', '%' . $filters['search'] . '%')
                      ->orWhereHas('user', fn($uq) => $uq->where(function ($uw) use ($filters) {
                          $uw->where('name', 'like', '%' . $filters['search'] . '%')
                             ->orWhere('email', 'like', '%' . $filters['search'] . '%')
                             ->orWhere('phone', 'like', '%' . $filters['search'] . '%');
                      }));
                }));
                $mq->when(!empty($filters['membership_number']), fn($q) => $q->where('membership_number', 'like', '%' . $filters['membership_number'] . '%'));
                $mq->when(!empty($filters['phone']), fn($q) => $q->whereHas('user', fn($uq) => $uq->where('phone', 'like', '%' . $filters['phone'] . '%')));
                $mq->when(!empty($filters['partner_id']), fn($q) => $q->where('partner_id', $filters['partner_id']));
                $mq->when(!empty($filters['sale_id']), fn($q) => $q->where('sales_id', $filters['sale_id']));
                $mq->when(!empty($filters['creator_id']), fn($q) => $q->where('created_by', $filters['creator_id']));
            })
            ->when(!empty($filters['changed_by']), fn($q) => $q->whereHas('changer', fn($cq) => $cq->where('name', $filters['changed_by'])))
            ->when(!empty($filters['created_from']), fn($q) => $q->whereDate('created_at', '>=', $filters['created_from']))
            ->when(!empty($filters['created_to']), fn($q) => $q->whereDate('created_at', '<=', $filters['created_to']))
            ->latest('created_at');

        $histories = $historiesQuery
            ->paginate($request->input('per_page', 20))
            ->withQueryString();

        $histories->getCollection()->transform(function (MemberActiveHistory $history) {
            $sales = $history->membership?->sales;
            $partner = $history->membership?->partner;
            return [
                'id' => $history->id,
                'membership_id' => $history->membership_id,
                'old_is_active' => $history->old_is_active,
                'new_is_active' => $history->new_is_active,
                'created_at' => $history->created_at?->toIso8601String(),
                'changer' => $history->changer ? [
                    'id' => $history->changer->id,
                    'name' => $history->changer->name,
                    'email' => $history->changer->email,
                ] : null,
                'member' => $history->membership?->user ? [
                    'id' => $history->membership->user->id,
                    'name' => $history->membership->user->name,
                    'email' => $history->membership->user->email,
                    'phone' => $history->membership->user->phone,
                    'slug' => $history->membership->user->slug,
                    'membership_number' => $history->membership->membership_number,
                ] : null,
                'sales' => $sales ? [
                    'id' => $sales->id,
                    'label' => $sales->getTranslation('name', app()->getLocale())
                        ?: $sales->getTranslation('name', 'ar')
                        ?: $sales->getTranslation('name', 'en')
                        ?: "#{$sales->id}",
                ] : null,
                'partner' => $partner ? [
                    'id' => $partner->id,
                    'title' => $partner->title,
                ] : null,
            ];
        });

        // Filter dropdown options
        $partners = Partner::query()
            ->whereIn('id', Membership::query()
                ->select('partner_id')
                ->when($restricted, $listingFilter)
                ->whereNotNull('partner_id')
                ->distinct())
            ->orderBy('title')
            ->get()
            ->map(fn($p) => ['value' => $p->id, 'label' => $p->title])
            ->toArray();

        $sales = Sales::query()
            ->orderBy('id')
            ->get()
            ->map(fn($s) => [
                'value' => $s->id,
                'label' => $s->getTranslation('name', app()->getLocale())
                    ?: $s->getTranslation('name', 'ar')
                    ?: $s->getTranslation('name', 'en')
                    ?: "#{$s->id}",
            ])->toArray();

        $creatorIdsQuery = Membership::query()
            ->whereNotNull('created_by')
            ->when($restricted, $listingFilter)
            ->select('created_by')
            ->distinct();
        $creators = User::whereIn('id', $creatorIdsQuery)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn($u) => ['value' => $u->id, 'label' => $u->name, 'email' => $u->email])
            ->toArray();

        $changerIdsQuery = MemberActiveHistory::query()
            ->whereHas('membership', function ($mq) use ($restricted, $listingFilter) {
                if ($restricted) {
                    $listingFilter($mq);
                }
            })
            ->select('changed_by')
            ->distinct();
        $changedByOptions = User::whereIn('id', $changerIdsQuery)
            ->orderBy('name')
            ->get(['name'])
            ->map(fn($u) => ['value' => $u->name, 'label' => $u->name])
            ->toArray();

        $userNames = User::query()
            ->whereNull('deleted_at')
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->whereHas('memberships', function ($mq) use ($restricted, $listingFilter) {
                $mq->whereHas('activeHistories');
                if ($restricted) {
                    $listingFilter($mq);
                }
            })
            ->orderBy('created_at', 'desc')
            ->limit(2000)
            ->get(['id', 'name', 'slug', 'created_at'])
            ->map(fn($u) => ['id' => $u->id, 'name' => $u->name, 'slug' => $u->slug])
            ->toArray();

        return Inertia::render('Admin/ActiveHistory/List', [
            'histories' => $histories->toArray(),
            'filters' => $filters,
            'partnerOptions' => $partners,
            'salesOptions' => $sales,
            'creatorOptions' => $creators,
            'changedByOptions' => $changedByOptions,
            'userNames' => $userNames,
        ]);
    }

    protected function getFilters(Request $request): array
    {
        return [
            'search' => $request->input('search', ''),
            'membership_number' => $request->input('membership_number', ''),
            'phone' => $request->input('phone', ''),
            'partner_id' => $request->filled('partner_id') ? (int) $request->input('partner_id') : null,
            'sale_id' => $request->filled('sale_id') ? (int) $request->input('sale_id') : null,
            'creator_id' => $request->filled('creator_id') ? (int) $request->input('creator_id') : null,
            'changed_by' => $request->input('changed_by', ''),
            'created_from' => $request->input('created_from', ''),
            'created_to' => $request->input('created_to', ''),
        ];
    }
}
