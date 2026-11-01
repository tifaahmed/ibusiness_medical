<?php

namespace App\Http\Controllers\Admin\User\Membership\ExtraCard;

use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Models\MemberExtraCard;
use App\Models\MemberLog;
use App\Models\Membership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Gives a member (primary OR relative) as many cards as needed, physical or soft. A card either follows the
 * member's own number (leave `card_number` empty) or carries a different one that opens the SAME member. A number
 * held by another member, or on another member's card, is refused. Every change is filed in `member_logs`.
 */
class AdminMemberExtraCardController extends BaseController
{
    use ScopesByMembershipCreator;

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'membership_id' => ['required', 'integer', 'exists:memberships,id'],
            'kind' => ['required', 'in:'.implode(',', MemberExtraCard::kinds())],
            'card_number' => ['nullable', 'string', 'max:64'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $membership = Membership::query()->findOrFail($data['membership_id']);
        $this->assertCanManageMembership($membership->isRelative() ? ($membership->parent ?? $membership) : $membership);

        $number = $this->cleanNumber($data['card_number'] ?? null, $membership);

        try {
            $created = [];

            for ($i = 0; $i < (int) ($data['quantity'] ?? 1); $i++) {
                $created[] = MemberExtraCard::query()->create([
                    'membership_id' => $membership->id,
                    'kind' => $data['kind'],
                    'card_number' => $number,
                    'source' => MemberExtraCard::SOURCE_ADMIN,
                    'created_by' => Auth::id(),
                    'note' => $data['note'] ?? null,
                ]);
            }

            MemberLog::record(
                membershipId: $membership->isRelative() ? $membership->parent_membership_id : $membership->id,
                adminId: Auth::id(),
                action: MemberLog::ACTION_CARD_CREATED,
                oldValues: null,
                newValues: ['membership_number' => $membership->membership_number, 'kind' => $data['kind'], 'card_number' => $number, 'count' => count($created)],
                request: $request,
            );
        } catch (Throwable $e) {
            Log::error('Extra card could not be created.', ['membership_id' => $membership->id, 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return back()->withErrors(['card_number' => 'The card could not be saved. Please try again.']);
        }

        return back()->with('success', 'Card added.');
    }

    public function update(Request $request, int $card): RedirectResponse
    {
        $extra = MemberExtraCard::query()->with('membership.parent')->findOrFail($card);
        $this->assertCanManageMembership($extra->membership->parent ?? $extra->membership);

        $data = $request->validate([
            'kind' => ['required', 'in:'.implode(',', MemberExtraCard::kinds())],
            'card_number' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', 'in:'.implode(',', MemberExtraCard::statuses())],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $old = $extra->only(['kind', 'card_number', 'status', 'note']);
        $number = $this->cleanNumber($data['card_number'] ?? null, $extra->membership, $extra->id);

        $extra->update(['kind' => $data['kind'], 'card_number' => $number, 'status' => $data['status'] ?? $extra->status, 'note' => $data['note'] ?? null]);

        MemberLog::record(
            membershipId: $extra->membership->parent_membership_id ?: $extra->membership_id,
            adminId: Auth::id(),
            action: MemberLog::ACTION_CARD_UPDATED,
            oldValues: $old,
            newValues: $extra->only(['kind', 'card_number', 'status', 'note']),
            request: $request,
        );

        return back()->with('success', 'Card updated.');
    }

    public function destroy(Request $request, int $card): RedirectResponse
    {
        $extra = MemberExtraCard::query()->with('membership.parent')->findOrFail($card);
        $this->assertCanManageMembership($extra->membership->parent ?? $extra->membership);
        $old = $extra->only(['kind', 'card_number', 'note']);
        $extra->delete(); // soft

        MemberLog::record(
            membershipId: $extra->membership->parent_membership_id ?: $extra->membership_id,
            adminId: Auth::id(),
            action: MemberLog::ACTION_CARD_DELETED,
            oldValues: $old,
            newValues: null,
            request: $request,
        );

        return back()->with('success', 'Card removed.');
    }

    /** Empty / equal to the member's own number = follow it (null); otherwise it must not be someone else's. */
    private function cleanNumber(?string $number, Membership $membership, ?int $exceptCardId = null): ?string
    {
        $number = trim((string) $number);

        if ($number === '' || $number === (string) $membership->membership_number) {
            return null;
        }

        if (MemberExtraCard::numberTakenBy($number, $membership->id, $exceptCardId) !== null) {
            throw ValidationException::withMessages(['card_number' => 'This number already belongs to another member or card.']);
        }

        return $number;
    }
}
