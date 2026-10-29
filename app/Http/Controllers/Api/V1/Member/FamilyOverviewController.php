<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Enums\FamilyMember\RelationshipEnum;
use App\Http\Controllers\Controller;
use App\Models\LoyaltyTransaction;
use App\Models\Membership;
use App\Models\User;
use App\Services\Loyalty\QuizBadges;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The family a member belongs to, seen from the inside: every person on the card with their phone, card number,
 * loyalty points and earned badges. A relative sees the card they belong to (the primary holder first); a primary
 * member sees their own card, and — if they used to be a relative before paying — the card they came from as `former`.
 * Everyone in a group sees the others (that is the point of a family card), and nobody outside it sees any of it.
 */
class FamilyOverviewController extends Controller
{
    public function __construct(private readonly QuizBadges $badges) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $me = $user->memberships()->latest('id')->first();

        if (! $me instanceof Membership) {
            return response()->json(['data' => ['role' => null, 'current' => [], 'former' => [], 'former_label' => null]]);
        }

        $isRelative = $me->isRelative();
        $headId = $isRelative ? $me->parent_membership_id : $me->id;

        return response()->json(['data' => [
            'role' => $isRelative ? 'relative' : 'primary',
            'current' => $this->group($headId, $me),
            // The old family stays visible after paying: the card the member came from and the people still on it.
            'former' => $me->former_parent_membership_id ? $this->group((int) $me->former_parent_membership_id, $me) : [],
            'can_add_relatives' => ! $isRelative,
        ]]);
    }

    /** @return list<array<string, mixed>> the primary holder first, then the relatives */
    private function group(?int $headId, Membership $me): array
    {
        if (! $headId) {
            return [];
        }

        $head = Membership::query()->with('user')->find($headId);

        if (! $head instanceof Membership) {
            return [];
        }

        $people = collect([$head])->merge(
            Membership::query()->with('user')->where('parent_membership_id', $head->id)->orderBy('id')->get(),
        )->unique('id');

        // The National ID and birth date are the card owner's to see on their OWN family; a relative looking at the
        // card they belong to (or the one they left) sees names, phones, points and badges only.
        $ownerView = ! $me->isRelative() && $head->id === $me->id;

        return $people->map(fn (Membership $m) => $this->person($m, $m->id === $head->id, $m->id === $me->id, $ownerView))->values()->all();
    }

    /** @return array<string, mixed> */
    private function person(Membership $m, bool $isHead, bool $isMe, bool $ownerView = false): array
    {
        $user = $m->user;
        $relationship = $m->relationship ? RelationshipEnum::tryFrom($m->relationship) : null;
        $earned = $user ? collect($this->badges->catalog($user->id)['badges'])->where('earned', true) : collect();

        return [
            'id' => $m->id,
            'name' => $user?->name,
            'phone' => $user?->phone,
            'phone_verified' => $user?->phone_verified_at !== null,
            'avatar_url' => $user ? get_image_url($user, 'avatar') : null,
            'membership_number' => $m->membership_number,
            'is_primary' => $isHead,
            'is_me' => $isMe,
            'relationship' => $m->relationship,
            'relationship_label' => $relationship?->label(),
            'is_active' => (bool) $m->is_active,
            'approval_status' => $m->approval_status,
            'rejection_reason' => $m->rejection_reason,
            // The owner may complete / remove their relatives from the app (never themselves).
            'editable' => $ownerView && ! $isHead,
            'national_id' => $ownerView ? $m->national_id : null,
            'date_of_birth' => $ownerView ? $user?->date_of_birth?->format('Y-m-d') : null,
            'points' => $user ? LoyaltyTransaction::balanceOf($user->id) : 0,
            'badges' => $earned->map(fn ($b) => ['title' => $b['title'] ?? ($b['key'] ?? ''), 'icon' => $b['icon'] ?? null, 'quiz' => $b['quiz'] ?? null])->values()->all(),
        ];
    }
}
