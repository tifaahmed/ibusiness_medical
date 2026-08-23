<?php

namespace App\Http\Controllers\Admin\User\Membership\Create;

use App\Enums\FamilyMember\RelationshipEnum;
use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Company;
use App\Models\Governorate;
use App\Models\Partner;
use App\Models\Sales;
use Illuminate\Http\Request;
use Illuminate\Auth\Access\AuthorizationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserMembershipCreateController extends BaseController
{
    use ScopesByMembershipCreator;

    public function __invoke(): Response
    {
        $locale = app()->getLocale();

        $partnerLocked = $this->membershipPartnerLocked();
        $adminPartnerId = $this->currentAdminPartnerId();

        // Partner-locked = partner is the ONLY narrowing scope. An admin who
        // also holds `manage own memberships` keeps a free partner selector
        // so they can create memberships outside their partner under the
        // creator scope. The lock only matters when partner is the sole gate.
        if ($partnerLocked && $adminPartnerId === null) {
            throw new AuthorizationException('No partner is attached to your account. Ask a super admin to assign one before creating memberships.');
        }

        $companies = Company::orderBy('slug')->get()->map(fn($c) => [
            'value' => $c->id,
            'label' => $c->getTranslation('name', $locale) ?: $c->getTranslation('name', 'ar') ?: $c->getTranslation('name', 'en'),
        ])->toArray();

        $partners = Partner::query()
            ->when($partnerLocked, fn($q) => $q->where('id', $adminPartnerId))
            ->orderBy('title')
            ->get()
            ->map(fn($p) => [
                'value' => $p->id,
                'label' => $p->title,
            ])->toArray();

        $salesOptions = Sales::query()
            ->orderBy('name')
            ->get()
            ->map(fn($s) => [
                'value' => $s->id,
                'label' => $s->name,
            ])->toArray();

        $governorates = Governorate::with(['cities' => fn($q) => $q->orderBy('slug')])
            ->orderBy('slug')
            ->get()
            ->map(fn(Governorate $g) => [
                'value' => $g->id,
                'label' => $g->getTranslation('name', $locale) ?: $g->getTranslation('name', 'ar') ?: $g->getTranslation('name', 'en'),
                'cities' => $g->cities->map(fn($c) => [
                    'value' => $c->id,
                    'label' => $c->getTranslation('name', $locale) ?: $c->getTranslation('name', 'ar') ?: $c->getTranslation('name', 'en'),
                ])->values(),
            ])
            ->toArray();

        return Inertia::render('Admin/User/Member/Create/MemberCreateView', [
            'relationshipOptions' => RelationshipEnum::getOptions(),
            'addressTypeOptions' => \App\Enums\Address\AddressTypeEnum::getOptions(),
            'companyOptions' => $companies,
            'partnerOptions' => $partners,
            'salesOptions' => $salesOptions,
            'governorateOptions' => $governorates,
            'partnerLocked' => $partnerLocked,
            'lockedPartnerId' => $partnerLocked ? $adminPartnerId : null,
        ]);
    }
}
