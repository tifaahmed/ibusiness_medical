<?php

namespace App\Http\Controllers\Admin\MembershipCard\List;

use App\Http\Controllers\Concerns\ScopesByMembershipCardCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\MembershipCard\List\AdminMembershipCardListResource;
use App\Models\Membership;
use App\Models\MembershipCard;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminMembershipCardListController extends BaseController
{
    use ScopesByMembershipCardCreator;

    public function __invoke(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $membershipNumber = trim((string) $request->input('membership_number', ''));

        $restricted = $this->isCardScopeRestricted();

        $cards = MembershipCard::query()
            ->with(['creator:id,name,email', 'media'])
            ->when($restricted, fn($q) => $this->applyCardScope($q))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('batch_name', 'like', "%{$search}%")
                       ->orWhere('id', $search)
                       ->orWhere('start_number', $search);
                });
            })
            ->when($membershipNumber !== '', function ($q) use ($membershipNumber) {
                $matchingIds = Membership::withTrashed()
                    ->where('membership_number', 'like', "%{$membershipNumber}%")
                    ->pluck('id')
                    ->all();

                if (empty($matchingIds)) {
                    // No memberships match — short-circuit to an empty result set.
                    $q->whereRaw('1 = 0');
                    return;
                }

                $q->where(function ($qq) use ($matchingIds) {
                    foreach ($matchingIds as $id) {
                        $qq->orWhereJsonContains('membership_ids', $id);
                    }
                });
            })
            ->latest()
            ->paginate((int) $request->input('per_page', 15))
            ->withQueryString();

        // Per-card completed membership counts (avoid N+1).
        $cardMembershipIds = $cards->getCollection()->mapWithKeys(fn ($card) => [
            $card->id => $card->membership_ids ?: [],
        ]);

        $allIds = $cardMembershipIds->flatten()->unique()->values()->all();

        $completedIds = !empty($allIds)
            ? Membership::whereIn('id', $allIds)->whereNotNull('completed_at')->pluck('id')->all()
            : [];

        $completedCounts = [];
        foreach ($cardMembershipIds as $cardId => $ids) {
            $completedCounts[$cardId] = count(array_intersect($ids, $completedIds));
        }

        $cards->getCollection()->each(fn ($card) => $card->completed_count = $completedCounts[$card->id] ?? 0);

        // Stats only count cards visible to this admin.
        $statsQuery = MembershipCard::query()
            ->when($restricted, fn($q) => $this->applyCardScope($q));

        $allCardMembershipIds = (clone $statsQuery)->pluck('membership_ids')
            ->filter()
            ->flatMap(fn ($ids) => is_array($ids) ? $ids : [])
            ->unique()
            ->values()
            ->all();

        $totalMemberships = count($allCardMembershipIds);
        $completedMemberships = $totalMemberships
            ? Membership::whereIn('id', $allCardMembershipIds)->whereNotNull('completed_at')->count()
            : 0;

        return Inertia::render('Admin/MembershipCard/List', [
            'cards' => [
                'data' => AdminMembershipCardListResource::collection($cards)->resolve(),
                'meta' => [
                    'current_page' => $cards->currentPage(),
                    'last_page' => $cards->lastPage(),
                    'per_page' => $cards->perPage(),
                    'total' => $cards->total(),
                    'from' => $cards->firstItem(),
                    'to' => $cards->lastItem(),
                    'links' => $cards->linkCollection()->toArray(),
                ],
            ],
            'filters' => [
                'search' => $search,
                'membership_number' => $membershipNumber,
            ],
            'stats' => [
                'batches' => $cards->total(),
                'total_memberships' => $totalMemberships,
                'completed_memberships' => $completedMemberships,
            ],
        ]);
    }
}
