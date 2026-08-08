<?php

namespace App\Http\Controllers\Admin\User\Membership\Edit;

use App\Enums\FamilyMember\RelationshipEnum;
use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\User\Membership\Edit\AdminUserMembershipEditResource;
use App\Models\Company;
use App\Models\Governorate;
use App\Models\Partner;
use App\Models\Sales;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserMembershipEditController extends BaseController
{
    use ScopesByMembershipCreator;

    /**
     * Show the form for editing the specified user and membership.
     */
    public function __invoke(string $user): Response
    {
        $restricted = $this->isMembershipScopeRestricted();
        $scopeFilter = $this->membershipScopeFilter();

        $withRelations = ['memberships' => function ($query) use ($restricted, $scopeFilter) {
            if ($restricted) {
                $scopeFilter($query);
            }
            $query->with('creator:id,name,email');
            $query->withCount('memberPayments');
            $query->orderBy('is_active', 'desc')->orderBy('created_at', 'desc');
        }];

        // Only eager load familyMembers if the table exists
        if (Schema::hasTable('family_members')) {
            $withRelations['memberships.familyMembers'] = function ($query) {
                $query->orderBy('created_at', 'asc');
            };
        }

        $user = User::with($withRelations)->where('slug', $user)->firstOrFail();
        $this->assertCanManageUser($user);
        $locale = app()->getLocale();

        $companies = Company::orderBy('slug')->get()->map(fn($c) => [
            'value' => $c->id,
            'label' => $c->getTranslation('name', $locale) ?: $c->getTranslation('name', 'ar') ?: $c->getTranslation('name', 'en'),
        ])->toArray();

        $partnerLocked = $this->membershipPartnerLocked();
        $adminPartnerId = $this->currentAdminPartnerId();

        $partners = Partner::query()
            ->when($partnerLocked, fn($q) => $q->where('id', $adminPartnerId ?? -1))
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

        $result = [
            'member' => (new AdminUserMembershipEditResource($user))->resolve(),
            'relationshipOptions' => RelationshipEnum::getOptions(),
            'companyOptions' => $companies,
            'partnerOptions' => $partners,
            'salesOptions' => $salesOptions,
            'governorateOptions' => $governorates,
            'partnerLocked' => $partnerLocked,
            'lockedPartnerId' => $partnerLocked ? $adminPartnerId : null,
        ];
        return Inertia::render('Admin/User/Member/Edit/MemberEditView', $result);
    }
}

