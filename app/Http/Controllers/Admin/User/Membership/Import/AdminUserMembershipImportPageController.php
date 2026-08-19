<?php

namespace App\Http\Controllers\Admin\User\Membership\Import;

use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Company;
use App\Models\MemberActiveHistory;
use App\Models\Membership;
use App\Models\Partner;
use App\Models\Sales;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserMembershipImportPageController extends BaseController
{
    use ScopesByMembershipCreator;

    public function __invoke(): Response
    {
        $creatorScoped = $this->scopesMembershipsToCreator();
        $partnerScoped = $this->scopesMembershipsToPartner();
        $restricted = $this->isMembershipScopeRestricted();
        $scopeFilter = $this->membershipScopeFilter();

        $listingFilter = function ($mq) use ($scopeFilter, $restricted) {
            $mq->whereNotNull('completed_at');
            if ($restricted) {
                $scopeFilter($mq);
            }
        };

        $partners = Partner::query()
            ->whereIn('id', Membership::query()
                ->whereNotNull('partner_id')
                ->where(function ($mq) use ($listingFilter) {
                    $listingFilter($mq);
                })
                ->select('partner_id'))
            ->orderBy('title')
            ->get()
            ->map(fn ($p) => ['value' => $p->id, 'label' => $p->title])
            ->toArray();

        $companies = Company::query()
            ->whereIn('id', Membership::query()
                ->whereNotNull('company_id')
                ->where(function ($mq) use ($listingFilter) {
                    $listingFilter($mq);
                })
                ->select('company_id'))
            ->get()
            ->map(fn ($c) => [
                'value' => $c->id,
                'label' => $c->getTranslation('name', app()->getLocale())
                    ?: ($c->getTranslation('name', 'ar') ?: ($c->getTranslation('name', 'en') ?: "#{$c->id}")),
            ])
            ->sortBy('label')
            ->values()
            ->toArray();

        $creatorIdsQuery = Membership::query()
            ->whereNotNull('created_by')
            ->where(function ($mq) use ($listingFilter) {
                $listingFilter($mq);
            })
            ->select('created_by')
            ->distinct();
        $creators = User::whereIn('id', $creatorIdsQuery)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn ($u) => ['value' => $u->id, 'label' => $u->name, 'email' => $u->email])
            ->toArray();

        $salesOptions = Sales::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($s) => [
                'value' => $s->id,
                'label' => $s->getTranslation('name', app()->getLocale())
                    ?: $s->getTranslation('name', 'ar')
                    ?: $s->getTranslation('name', 'en')
                    ?: "#{$s->id}",
            ])
            ->toArray();

        $canFilterByCreator = ! $creatorScoped || $partnerScoped;

        $changerIdsQuery = MemberActiveHistory::query()
            ->whereHas('membership', function ($mq) use ($listingFilter) {
                $mq->where(function ($mq2) use ($listingFilter) {
                    $listingFilter($mq2);
                });
            })
            ->select('changed_by')
            ->distinct();
        $lastActivationChangerOptions = User::whereIn('id', $changerIdsQuery)
            ->orderBy('name')
            ->get(['name'])
            ->map(fn ($u) => ['value' => $u->name, 'label' => $u->name])
            ->toArray();

        return Inertia::render('Admin/User/Member/Import/MemberImportView', [
            'partnerOptions' => $partners,
            'creatorOptions' => $creators,
            'salesOptions' => $salesOptions,
            'companyOptions' => $companies,
            'lastActivationChangerOptions' => $lastActivationChangerOptions,
            'canFilterByCreator' => $canFilterByCreator,
        ]);
    }
}
