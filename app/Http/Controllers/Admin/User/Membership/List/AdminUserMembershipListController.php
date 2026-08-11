<?php

namespace App\Http\Controllers\Admin\User\Membership\List;

use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\User\Membership\List\AdminUserMembershipListCollection;
use App\Models\CardLayout;
use App\Models\Company;
use App\Models\MemberActiveHistory;
use App\Models\Membership;
use App\Models\MembershipCard;
use App\Models\Partner;
use App\Models\Sales;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserMembershipListController extends BaseController
{
    use ScopesByMembershipCreator;

    /**
     * Display a listing of users with their memberships.
     */
    public function __invoke(Request $request): Response
    {
        $filters = $this->getFilters($request);
        $isActiveFilter = $filters['is_active'];
        $isPaidFilter = $filters['is_paid'];

        $sort = in_array($request->input('sort'), ['name', 'created_at', 'updated_at', 'days_covered', 'outstanding_days', 'registration_date'], true) ? $request->input('sort') : null;
        $direction = strtolower($request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        $creatorScoped = $this->scopesMembershipsToCreator();
        $partnerScoped = $this->scopesMembershipsToPartner();
        $restricted = $this->isMembershipScopeRestricted();

        $creatorId = $creatorScoped ? Auth::id() : null;
        $adminPartnerId = $this->currentAdminPartnerId();

        // URL filters narrow within the union scope — they cannot widen it.
        // The trait's scope filter is applied independently below, so a
        // creator-scoped admin can't reach other admins' memberships by
        // poking ?creator_id=X, and a partner-scoped admin can't widen via
        // ?partner_id=Y. Both filters are passed through as-is to allow
        // mixed-scope admins to drill into a single creator or partner.
        $partnerIdFilter = $filters['partner_id'];
        $creatorFilter = $filters['creator_id'];
        $saleIdFilter = $filters['sale_id'];
        $companyIdFilter = $filters['company_id'];

        $membershipNumberFilter = $filters['membership_number'];

        // Membership IDs that were created through a card batch (batch import).
        $cardPatchMembershipIds = MembershipCard::query()
            ->whereNotNull('membership_ids')
            ->lazyById(200)
            ->flatMap(fn(MembershipCard $card) => $card->membership_ids ?? [])
            ->unique()
            ->values()
            ->toArray();

        // Memberships whose card was changed away from its design — the rest
        // still render from the template and follow every change to it.
        $customCardMembershipIds = CardLayout::customisedMembershipIds();

        // Closure that applies the union (creator OR partner) restriction to
        // a Membership sub-query. No-op when the admin has full access.
        $scopeFilter = $this->membershipScopeFilter();

        // The admin list view hides in-progress (placeholder/card-batch)
        // memberships — only fully completed records show up. Applied to every
        // memberships sub-query below so users without a completed membership
        // disappear from the list entirely.
        $listingFilter = function ($mq) use ($scopeFilter, $restricted) {
            $mq->whereNotNull('completed_at');
            if ($restricted) {
                $scopeFilter($mq);
            }
        };

        $users = User::query()
            ->whereNull('deleted_at')
            ->whereHas('memberships', $listingFilter)
            ->when($creatorFilter !== null, fn($q) => $q->whereHas('memberships', function ($mq) use ($creatorFilter, $listingFilter) {
                $mq->where('created_by', $creatorFilter);
                $listingFilter($mq);
            }))
            ->when($partnerIdFilter !== null, fn($q) => $q->whereHas('memberships', function ($mq) use ($partnerIdFilter, $listingFilter) {
                $mq->where('partner_id', $partnerIdFilter);
                $listingFilter($mq);
            }))
            ->when($saleIdFilter !== null, fn($q) => $q->whereHas('memberships', function ($mq) use ($saleIdFilter, $listingFilter) {
                $mq->where('sales_id', $saleIdFilter);
                $listingFilter($mq);
            }))
            ->when($companyIdFilter !== null, fn($q) => $q->whereHas('memberships', function ($mq) use ($companyIdFilter, $listingFilter) {
                $mq->where('company_id', $companyIdFilter);
                $listingFilter($mq);
            }))
            ->when(!empty($filters['search']), fn($q) => $q->where('name', 'like', '%' . $filters['search'] . '%'))
            ->when(!empty($filters['email']), fn($q) => $q->where('email', 'like', '%' . $filters['email'] . '%'))
            ->when(!empty($filters['phone']), fn($q) => $q->where('phone', 'like', '%' . $filters['phone'] . '%'))
            ->when(!empty($membershipNumberFilter), function ($q) use ($membershipNumberFilter, $listingFilter) {
                $q->whereHas('memberships', function ($mq) use ($membershipNumberFilter, $listingFilter) {
                    $mq->where('membership_number', $membershipNumberFilter);
                    $listingFilter($mq);
                });
            })
            ->when(!empty($filters['created_from']), fn($q) => $q->whereDate('created_at', '>=', $filters['created_from']))
            ->when(!empty($filters['created_to']), fn($q) => $q->whereDate('created_at', '<=', $filters['created_to']))
            ->when(!empty($filters['registration_date_from']), fn($q) => $q->whereHas('memberships', function ($mq) use ($filters, $listingFilter) {
                $mq->whereDate('registration_date', '>=', $filters['registration_date_from']);
                $listingFilter($mq);
            }))
            ->when(!empty($filters['registration_date_to']), fn($q) => $q->whereHas('memberships', function ($mq) use ($filters, $listingFilter) {
                $mq->whereDate('registration_date', '<=', $filters['registration_date_to']);
                $listingFilter($mq);
            }))
            ->when(!empty($filters['expiration_date_from']), fn($q) => $q->whereHas('memberships', function ($mq) use ($filters, $listingFilter) {
                $mq->whereDate('expiration_date', '>=', $filters['expiration_date_from']);
                $listingFilter($mq);
            }))
            ->when(!empty($filters['expiration_date_to']), fn($q) => $q->whereHas('memberships', function ($mq) use ($filters, $listingFilter) {
                $mq->whereDate('expiration_date', '<=', $filters['expiration_date_to']);
                $listingFilter($mq);
            }))
            ->when(!empty($filters['last_activation_changer']), fn($q) => $q->whereHas('memberships', function ($mq) use ($filters, $listingFilter) {
                $mq->whereHas('activeHistories', function ($ahq) use ($filters) {
                    $ahq->whereHas('changer', fn($cq) => $cq->where('name', 'like', '%' . $filters['last_activation_changer'] . '%'));
                });
                $listingFilter($mq);
            }))
            ->when(!empty($filters['last_activation_from']), fn($q) => $q->whereHas('memberships', function ($mq) use ($filters, $listingFilter) {
                $mq->whereHas('activeHistories', function ($ahq) use ($filters) {
                    $ahq->whereDate('created_at', '>=', $filters['last_activation_from']);
                });
                $listingFilter($mq);
            }))
            ->when(!empty($filters['last_activation_to']), fn($q) => $q->whereHas('memberships', function ($mq) use ($filters, $listingFilter) {
                $mq->whereHas('activeHistories', function ($ahq) use ($filters) {
                    $ahq->whereDate('created_at', '<=', $filters['last_activation_to']);
                });
                $listingFilter($mq);
            }))
            ->when($filters['outstanding_days_from'] !== null, fn($q) => $q->whereHas('memberships', function ($mq) use ($filters, $listingFilter) {
                $mq->whereRaw('DATEDIFF(CURDATE(), registration_date) - COALESCE((
                    SELECT SUM(DATEDIFF(to_date, from_date) + 1)
                    FROM member_payments
                    WHERE membership_id = memberships.id
                ), 0) >= ?', [(int) $filters['outstanding_days_from']]);
                $listingFilter($mq);
            }))
            ->when($filters['outstanding_days_to'] !== null, fn($q) => $q->whereHas('memberships', function ($mq) use ($filters, $listingFilter) {
                $mq->whereRaw('DATEDIFF(CURDATE(), registration_date) - COALESCE((
                    SELECT SUM(DATEDIFF(to_date, from_date) + 1)
                    FROM member_payments
                    WHERE membership_id = memberships.id
                ), 0) <= ?', [(int) $filters['outstanding_days_to']]);
                $listingFilter($mq);
            }))
            ->when($filters['is_active'] === true, fn($q) => $q->whereHas('memberships', function ($mq) use ($listingFilter) {
                $mq->where('is_active', true);
                $listingFilter($mq);
            }))
            ->when($filters['is_active'] === false, fn($q) => $q->whereHas('memberships', function ($mq) use ($listingFilter) {
                $mq->where('is_active', false);
                $listingFilter($mq);
            })->whereDoesntHave('memberships', function ($mq) use ($listingFilter) {
                $mq->where('is_active', true);
                $listingFilter($mq);
            }))
            ->when($filters['is_paid'] === true, fn($q) => $q->whereHas('memberships', function ($mq) use ($listingFilter) {
                $mq->where('is_paid', true);
                $listingFilter($mq);
            }))
            ->when($filters['is_paid'] === false, fn($q) => $q->whereHas('memberships', function ($mq) use ($listingFilter) {
                $mq->where('is_paid', false);
                $listingFilter($mq);
            })->whereDoesntHave('memberships', function ($mq) use ($listingFilter) {
                $mq->where('is_paid', true);
                $listingFilter($mq);
            }))
            ->when($filters['is_from_card_patch'] === true, fn($q) => $q->whereHas('memberships', function ($mq) use ($cardPatchMembershipIds, $listingFilter) {
                $mq->whereIn('id', $cardPatchMembershipIds);
                $listingFilter($mq);
            }))
            ->when($filters['is_from_card_patch'] === false, fn($q) => $q->whereHas('memberships', function ($mq) use ($cardPatchMembershipIds, $listingFilter) {
                $mq->whereNotIn('id', $cardPatchMembershipIds);
                $listingFilter($mq);
            })->whereDoesntHave('memberships', function ($mq) use ($cardPatchMembershipIds, $listingFilter) {
                $mq->whereIn('id', $cardPatchMembershipIds);
                $listingFilter($mq);
            }))
            ->when($filters['has_custom_card'] === true, fn($q) => $q->whereHas('memberships', function ($mq) use ($customCardMembershipIds, $listingFilter) {
                $mq->whereIn('id', $customCardMembershipIds);
                $listingFilter($mq);
            }))
            ->when($filters['has_custom_card'] === false, fn($q) => $q->whereHas('memberships', function ($mq) use ($customCardMembershipIds, $listingFilter) {
                $mq->whereNotIn('id', $customCardMembershipIds);
                $listingFilter($mq);
            })->whereDoesntHave('memberships', function ($mq) use ($customCardMembershipIds, $listingFilter) {
                $mq->whereIn('id', $customCardMembershipIds);
                $listingFilter($mq);
            }))
            ->with(['memberships' => function ($q) use ($isActiveFilter, $partnerIdFilter, $creatorFilter, $saleIdFilter, $companyIdFilter, $membershipNumberFilter, $listingFilter) {
                if ($isActiveFilter === true) {
                    $q->active();
                } elseif ($isActiveFilter === false) {
                    $q->inactive();
                }
                $listingFilter($q);
                if ($partnerIdFilter !== null) {
                    $q->where('partner_id', $partnerIdFilter);
                }
                if ($creatorFilter !== null) {
                    $q->where('created_by', $creatorFilter);
                }
                if ($saleIdFilter !== null) {
                    $q->where('sales_id', $saleIdFilter);
                }
                if ($companyIdFilter !== null) {
                    $q->where('company_id', $companyIdFilter);
                }
                if (!empty($membershipNumberFilter)) {
                    $q->where('membership_number', $membershipNumberFilter);
                }
                // Active first, then newest, so the resource's first() picks the
                // membership the admin most likely wants to see.
                $q->orderBy('is_active', 'desc');
                $q->ordered();
                $q->with(['company', 'partner', 'creator:id,name,email', 'latestActiveHistory', 'sales', 'memberPayments']);
            }])
            ->when($sort === 'name', fn($q) => $q->orderBy('name', $direction))
            ->when($sort === 'created_at', fn($q) => $q->orderBy('users.created_at', $direction))
            ->when($sort === 'updated_at', fn($q) => $q->orderBy('users.updated_at', $direction))
            ->when($sort === 'registration_date', fn($q) => $q->orderBy(
                Membership::select('registration_date')
                    ->whereColumn('user_id', 'users.id')
                    ->whereNotNull('completed_at')
                    ->orderBy('is_active', 'desc')
                    ->orderBy('id', 'desc')
                    ->limit(1),
                $direction
            ))
            ->when($sort === 'days_covered', fn($q) => $q->orderByRaw('(SELECT COALESCE(SUM(DATEDIFF(mp.to_date, mp.from_date) + 1), 0)
                    FROM member_payments mp
                    WHERE mp.membership_id = (
                        SELECT m2.id FROM memberships m2
                        WHERE m2.user_id = users.id
                        AND m2.completed_at IS NOT NULL
                        ORDER BY m2.is_active DESC, m2.id DESC
                        LIMIT 1
                    )) ' . $direction)
            )
            ->when($sort === 'outstanding_days', fn($q) => $q->orderByRaw('(DATEDIFF(CURDATE(), (
                    SELECT m3.registration_date FROM memberships m3
                    WHERE m3.user_id = users.id
                    AND m3.completed_at IS NOT NULL
                    ORDER BY m3.is_active DESC, m3.id DESC
                    LIMIT 1
                )) - COALESCE((
                    SELECT SUM(DATEDIFF(mp2.to_date, mp2.from_date) + 1)
                    FROM member_payments mp2
                    WHERE mp2.membership_id = (
                        SELECT m4.id FROM memberships m4
                        WHERE m4.user_id = users.id
                        AND m4.completed_at IS NOT NULL
                        ORDER BY m4.is_active DESC, m4.id DESC
                        LIMIT 1
                    )
                ), 0)) ' . $direction)
            )
            ->when($sort === null, fn($q) => $q->latest())
            ->paginate($request->input('per_page', 15))->withQueryString();

        // Partner dropdown: limit to partners that appear in the admin's
        // visible memberships. A creator+partner admin sees their own
        // partner plus any partners they created memberships for. A creator-
        // only admin sees only the partners that appear on their own
        // memberships. A partner-only admin sees just their assigned partner.
        $partners = Partner::query()
            ->whereIn('id', Membership::query()
                ->whereNotNull('partner_id')
                ->where(function ($mq) use ($listingFilter) { $listingFilter($mq); })
                ->select('partner_id'))
            ->orderBy('title')
            ->get()
            ->map(fn($p) => [
                'value' => $p->id,
                'label' => $p->title,
            ])->toArray();

        // Company dropdown: limit to companies that appear in the admin's
        // visible memberships, same union-scope rule as partners/creators.
        $companies = Company::query()
            ->whereIn('id', Membership::query()
                ->whereNotNull('company_id')
                ->where(function ($mq) use ($listingFilter) { $listingFilter($mq); })
                ->select('company_id'))
            ->get()
            ->map(fn($c) => [
                'value' => $c->id,
                'label' => $c->getTranslation('name', app()->getLocale())
                    ?: ($c->getTranslation('name', 'ar') ?: ($c->getTranslation('name', 'en') ?: "#{$c->id}")),
            ])
            ->sortBy('label')
            ->values()
            ->toArray();

        // Creator dropdown: limit to creators that appear in the admin's
        // visible memberships. A creator-only admin sees just themselves.
        $creatorIdsQuery = Membership::query()
            ->whereNotNull('created_by')
            ->where(function ($mq) use ($listingFilter) { $listingFilter($mq); })
            ->select('created_by')
            ->distinct();
        $creators = User::whereIn('id', $creatorIdsQuery)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn($u) => ['value' => $u->id, 'label' => $u->name, 'email' => $u->email])
            ->toArray();

        // Sales dropdown: all sales records with translated names
        $salesOptions = Sales::query()
            ->orderBy('id')
            ->get()
            ->map(fn($s) => [
                'value' => $s->id,
                'label' => $s->getTranslation('name', app()->getLocale())
                    ?: $s->getTranslation('name', 'ar')
                    ?: $s->getTranslation('name', 'en')
                    ?: "#{$s->id}",
            ])->toArray();

        // Autocomplete sources: users that have at least one COMPLETED
        // membership within the admin's union scope. Excludes placeholder
        // rows (card batches) so suggestions stay clean.
        $completedMembershipScope = function ($mq) use ($scopeFilter, $restricted) {
            $mq->whereNotNull('completed_at');
            if ($restricted) {
                $scopeFilter($mq);
            }
        };

        $userNames = User::query()
            ->whereNull('deleted_at')
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->whereHas('memberships', $completedMembershipScope)
            ->orderBy('created_at', 'desc')
            ->limit(2000)
            ->get(['id', 'name', 'slug', 'created_at'])
            ->map(fn($u) => ['id' => $u->id, 'name' => $u->name, 'slug' => $u->slug])
            ->toArray();

        $userPhones = User::query()
            ->whereNull('deleted_at')
            ->whereHas('memberships', $completedMembershipScope)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderBy('phone')
            ->limit(2000)
            ->get(['id', 'name', 'phone'])
            ->map(fn($u) => ['id' => $u->id, 'name' => $u->name, 'phone' => $u->phone])
            ->toArray();

        $userEmails = User::query()
            ->whereNull('deleted_at')
            ->whereHas('memberships', $completedMembershipScope)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('email')
            ->limit(2000)
            ->get(['id', 'name', 'email'])
            ->map(fn($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])
            ->toArray();

        // The creator filter is only useful when the visible set can include
        // multiple creators. A creator-only admin only ever sees their own
        // rows — hide the filter. A mixed-scope (creator + partner) admin
        // does see other creators in the partner slice, so allow it.
        $canFilterByCreator = !$creatorScoped || $partnerScoped;

        // Chart data: membership counts grouped by day and by month,
        // respecting all active filters so the chart stays in sync.
        $chartMembershipQuery = Membership::query()
            ->where(function ($mq) use ($listingFilter) { $listingFilter($mq); })
            ->whereHas('user', fn($q) => $q->whereNull('deleted_at'))
            ->when(!empty($filters['search']), fn($q) => $q->whereHas('user', fn($uq) => $uq->where('name', 'like', '%'.$filters['search'].'%')))
            ->when(!empty($filters['email']), fn($q) => $q->whereHas('user', fn($uq) => $uq->where('email', 'like', '%'.$filters['email'].'%')))
            ->when(!empty($filters['phone']), fn($q) => $q->whereHas('user', fn($uq) => $uq->where('phone', 'like', '%'.$filters['phone'].'%')))
            ->when($creatorFilter !== null, fn($q) => $q->where('created_by', $creatorFilter))
            ->when($partnerIdFilter !== null, fn($q) => $q->where('partner_id', $partnerIdFilter))
            ->when($saleIdFilter !== null, fn($q) => $q->where('sales_id', $saleIdFilter))
            ->when($companyIdFilter !== null, fn($q) => $q->where('company_id', $companyIdFilter))
            ->when(!empty($membershipNumberFilter), fn($q) => $q->where('membership_number', $membershipNumberFilter))
            ->when($filters['is_active'] === true, fn($q) => $q->where('is_active', true))
            ->when($filters['is_active'] === false, fn($q) => $q->where('is_active', false))
            ->when($filters['is_paid'] === true, fn($q) => $q->where('is_paid', true))
            ->when($filters['is_paid'] === false, fn($q) => $q->where('is_paid', false))
            ->when(!empty($filters['registration_date_from']), fn($q) => $q->whereDate('registration_date', '>=', $filters['registration_date_from']))
            ->when(!empty($filters['registration_date_to']), fn($q) => $q->whereDate('registration_date', '<=', $filters['registration_date_to']))
            ->when(!empty($filters['expiration_date_from']), fn($q) => $q->whereDate('expiration_date', '>=', $filters['expiration_date_from']))
            ->when(!empty($filters['expiration_date_to']), fn($q) => $q->whereDate('expiration_date', '<=', $filters['expiration_date_to']))
            ->when(!empty($filters['created_from']), fn($q) => $q->whereHas('user', fn($uq) => $uq->whereDate('created_at', '>=', $filters['created_from'])))
            ->when(!empty($filters['created_to']), fn($q) => $q->whereHas('user', fn($uq) => $uq->whereDate('created_at', '<=', $filters['created_to'])))
            ->when(!empty($filters['last_activation_changer']), fn($q) => $q->whereHas('activeHistories', fn($ahq) => $ahq->whereHas('changer', fn($cq) => $cq->where('name', 'like', '%'.$filters['last_activation_changer'].'%'))))
            ->when(!empty($filters['last_activation_from']), fn($q) => $q->whereHas('activeHistories', fn($ahq) => $ahq->whereDate('created_at', '>=', $filters['last_activation_from'])))
            ->when(!empty($filters['last_activation_to']), fn($q) => $q->whereHas('activeHistories', fn($ahq) => $ahq->whereDate('created_at', '<=', $filters['last_activation_to'])))
            ->when($filters['outstanding_days_from'] !== null, fn($q) => $q->whereRaw('DATEDIFF(CURDATE(), registration_date) - COALESCE((
                SELECT SUM(DATEDIFF(to_date, from_date) + 1)
                FROM member_payments
                WHERE membership_id = memberships.id
            ), 0) >= ?', [(int) $filters['outstanding_days_from']]))
            ->when($filters['outstanding_days_to'] !== null, fn($q) => $q->whereRaw('DATEDIFF(CURDATE(), registration_date) - COALESCE((
                SELECT SUM(DATEDIFF(to_date, from_date) + 1)
                FROM member_payments
                WHERE membership_id = memberships.id
            ), 0) <= ?', [(int) $filters['outstanding_days_to']]))
            ->when($filters['is_from_card_patch'] === true, fn($q) => $q->whereIn('id', $cardPatchMembershipIds))
            ->when($filters['is_from_card_patch'] === false, fn($q) => $q->whereNotIn('id', $cardPatchMembershipIds))
            ->when($filters['has_custom_card'] === true, fn($q) => $q->whereIn('id', $customCardMembershipIds))
            ->when($filters['has_custom_card'] === false, fn($q) => $q->whereNotIn('id', $customCardMembershipIds))
            ->when(!empty($filters['chart_days']), fn($q) => $q->whereHas('user', fn($uq) => $uq->where('created_at', '>=', now()->subDays((int)$filters['chart_days']))));

        $dailyCounts = (clone $chartMembershipQuery)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');

        $monthlyCounts = (clone $chartMembershipQuery)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('count', 'month');

        // Last activation changer dropdown: users who have changed activation
        // status within the visible membership set.
        $changerIdsQuery = MemberActiveHistory::query()
            ->whereHas('membership', function ($mq) use ($listingFilter) {
                $mq->where(function ($mq2) use ($listingFilter) { $listingFilter($mq2); });
            })
            ->select('changed_by')
            ->distinct();
        $lastActivationChangerOptions = User::whereIn('id', $changerIdsQuery)
            ->orderBy('name')
            ->get(['name'])
            ->map(fn($u) => ['value' => $u->name, 'label' => $u->name])
            ->toArray();

        return Inertia::render('Admin/User/Membership/List', [
            'users' => new AdminUserMembershipListCollection($users)->toArray($request),
            'filters' => $filters,
            'partnerOptions' => $partners,
            'creatorOptions' => $creators,
            'salesOptions' => $salesOptions,
            'companyOptions' => $companies,
            'lastActivationChangerOptions' => $lastActivationChangerOptions,
            'canFilterByCreator' => $canFilterByCreator,
            'userNames' => $userNames,
            'userPhones' => $userPhones,
            'userEmails' => $userEmails,
            'sort' => $sort,
            'direction' => $direction,
            'chartData' => [
                'daily' => $dailyCounts,
                'monthly' => $monthlyCounts,
            ],
        ]);
    }

    /**
     * Get filters from request.
     */
    protected function getFilters(Request $request): array
    {
        return [
            'search' => $request->input('search', ''),
            'email' => $request->input('email', ''),
            'phone' => $request->input('phone', ''),
            'membership_number' => $request->input('membership_number', ''),
            'is_active' => $request->has('is_active') ? (bool) $request->input('is_active') : null,
            'is_paid' => $request->has('is_paid') ? (bool) $request->input('is_paid') : null,
            'partner_id' => $request->filled('partner_id') ? (int) $request->input('partner_id') : null,
            'creator_id' => $request->filled('creator_id') ? (int) $request->input('creator_id') : null,
            'sale_id' => $request->filled('sale_id') ? (int) $request->input('sale_id') : null,
            'company_id' => $request->filled('company_id') ? (int) $request->input('company_id') : null,
            'created_from' => $request->filled('created_from') ? $request->input('created_from') : null,
            'created_to' => $request->filled('created_to') ? $request->input('created_to') : null,
            'registration_date_from' => $request->filled('registration_date_from') ? $request->input('registration_date_from') : null,
            'registration_date_to' => $request->filled('registration_date_to') ? $request->input('registration_date_to') : null,
            'expiration_date_from' => $request->filled('expiration_date_from') ? $request->input('expiration_date_from') : null,
            'expiration_date_to' => $request->filled('expiration_date_to') ? $request->input('expiration_date_to') : null,
            'is_from_card_patch' => $request->has('is_from_card_patch') ? (bool) $request->input('is_from_card_patch') : null,
            'has_custom_card' => $request->has('has_custom_card') ? (bool) $request->input('has_custom_card') : null,
            'last_activation_changer' => $request->input('last_activation_changer', ''),
            'last_activation_from' => $request->filled('last_activation_from') ? $request->input('last_activation_from') : null,
            'last_activation_to' => $request->filled('last_activation_to') ? $request->input('last_activation_to') : null,
            'outstanding_days_from' => $request->filled('outstanding_days_from') ? (int) $request->input('outstanding_days_from') : null,
            'outstanding_days_to' => $request->filled('outstanding_days_to') ? (int) $request->input('outstanding_days_to') : null,
            'chart_days' => $request->input('chart_days'),
        ];
    }
}
