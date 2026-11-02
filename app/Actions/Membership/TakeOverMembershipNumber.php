<?php

namespace App\Actions\Membership;

use App\Models\MemberLog;
use App\Models\Membership;
use App\Models\MembershipCard;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * An admin wants a membership number that another card already holds. Two steps, kept apart on purpose:
 *
 *  1. {@see release()} — the other card (incomplete, someone else's, whatever) is soft-deleted exactly as the member delete
 *     does (its relatives with it, its user when no other card remains) and its number AND slug are renamed `N~del<id>`:
 *     both columns are UNIQUE even for soft-deleted rows, so without the rename the new holder could never save. The old
 *     number is written to `member_logs` so the card can be put back by hand. Nothing is hard-deleted.
 *  2. {@see relink()} — runs when the admin SAVES the form: every card batch ("patch", `membership_card_patches`) that
 *     listed the deleted card now lists the card that holds the number, so a printed batch is never left pointing at a
 *     deleted row (and never at both). Abandoning the form therefore leaves the batch exactly as a plain delete would.
 */
class TakeOverMembershipNumber
{
    /** What an admin must see before confirming: who holds the number and what deleting it takes with it. */
    public function describe(Membership $holder): array
    {
        $holder->loadMissing(['user', 'company', 'partner']);
        $patches = $this->patchesOf($holder);

        return [
            'membership_id' => $holder->id,
            'is_complete' => $holder->completed_at !== null && ! str_starts_with((string) $holder->membership_number, 'REG-'),
            'is_relative' => $holder->isRelative(),
            'relatives_count' => $holder->isRelative() ? 0 : $holder->relatives()->count(),
            'other_cards_of_user' => $holder->user ? $holder->user->memberships()->where('id', '!=', $holder->id)->count() : 0,
            'batches' => $patches->map(fn (MembershipCard $p) => ['id' => $p->id, 'name' => $p->batch_name])->values()->all(),
        ];
    }

    /**
     * @return array{old_number: string, released_membership_id: int, relatives_deleted: int, user_deleted: bool}
     */
    public function release(Membership $holder, ?int $adminId, ?Request $request = null, ?int $forMembershipId = null): array
    {
        $number = (string) $holder->membership_number;

        return DB::transaction(function () use ($holder, $number, $adminId, $request, $forMembershipId) {
            $user = $holder->user;
            $relatives = $holder->isRelative() ? 0 : $holder->relatives()->count();

            MemberLog::record(
                membershipId: $holder->id,
                adminId: $adminId,
                action: MemberLog::ACTION_DELETED,
                oldValues: [
                    'reason' => 'membership_number_taken_over',
                    'membership_number' => $number,
                    'slug' => $holder->slug,
                    'user_name' => $user?->name,
                    'for_membership_id' => $forMembershipId,
                    'relatives' => $relatives,
                ],
                newValues: null,
                request: $request,
            );

            $holder->delete(); // soft; a primary takes its relatives with it (Membership::boot)

            $userDeleted = false;
            if ($user instanceof User && $user->memberships()->count() === 0) {
                $user->delete();
                $userDeleted = true;
            }

            // Free both UNIQUE columns. Query-builder update: no model events, and the row is already soft-deleted.
            $freed = $number.'~del'.$holder->id;
            Membership::withTrashed()->whereKey($holder->id)->update(['membership_number' => $freed, 'slug' => $freed]);

            Log::warning('Membership number taken over: the previous holder was deleted.', [
                'admin_id' => $adminId,
                'old_number' => $number,
                'released_membership_id' => $holder->id,
                'relatives_deleted' => $relatives,
                'user_deleted' => $userDeleted,
            ]);

            return ['old_number' => $number, 'released_membership_id' => $holder->id, 'relatives_deleted' => $relatives, 'user_deleted' => $userDeleted];
        });
    }

    /** The number a released card had before it was renamed, or null when it was never released by a takeover. */
    public function originalNumberOf(Membership $card): ?string
    {
        return preg_match('/^(.+)~del'.$card->id.'$/', (string) $card->membership_number, $m) ? $m[1] : null;
    }

    /**
     * Put a released card's own number back when it is restored from the trash — only for cards this action renamed
     * (`N~del<id>`); a normally deleted card is never touched. If somebody holds N now, the card keeps its `~del`
     * number (nothing collides, nothing is lost) and the admin renumbers it by hand. Returns true when N came back.
     */
    public function reinstateNumber(Membership $card): bool
    {
        $original = $this->originalNumberOf($card);

        if ($original === null) {
            return false;
        }

        $taken = Membership::withTrashed()->where('id', '!=', $card->id)
            ->where(fn ($q) => $q->where('membership_number', $original)->orWhere('slug', $original))->exists();

        if ($taken) {
            Log::warning('Restored card kept its renamed number: the original is held by another card.', ['membership_id' => $card->id, 'original' => $original]);

            return false;
        }

        Membership::withTrashed()->whereKey($card->id)->update(['membership_number' => $original, 'slug' => $original]);

        return true;
    }

    /** Point every batch that listed the released card at the card that now holds its number. Idempotent. */
    public function relink(int $releasedId, Membership $newHolder): int
    {
        // `takeover_membership_id` comes from the browser, so it is proven before it may rewrite any batch: it must be a
        // card that THIS admin released for exactly this number (renamed `N~del<id>`, with the log row to show for it).
        // A forged id of some other card therefore changes nothing.
        $released = Membership::withTrashed()->find($releasedId);
        $adminId = \Illuminate\Support\Facades\Auth::id();

        $proven = $released !== null
            && $released->trashed()
            && $this->originalNumberOf($released) === (string) $newHolder->membership_number
            && MemberLog::query()->where('membership_id', $releasedId)->where('action', MemberLog::ACTION_DELETED)->where('admin_id', $adminId)->get()
                ->contains(fn (MemberLog $l) => ($l->old_values['reason'] ?? null) === 'membership_number_taken_over');

        if (! $proven) {
            Log::warning('Batch relink refused: the card was not released by this admin for this number.', ['released_id' => $releasedId, 'new_id' => $newHolder->id, 'admin_id' => $adminId]);

            return 0;
        }

        $changed = 0;

        foreach ($this->patchesOf($releasedId) as $patch) {
            $ids = collect($patch->membership_ids ?? [])->map(fn ($i) => (int) $i)
                ->map(fn (int $i) => $i === $releasedId ? $newHolder->id : $i)
                ->unique()->values()->all();

            $patch->forceFill(['membership_ids' => $ids])->save();
            $changed++;
        }

        if ($changed > 0) {
            MemberLog::record(
                membershipId: $newHolder->id,
                adminId: \Illuminate\Support\Facades\Auth::id(),
                action: MemberLog::ACTION_UPDATED,
                oldValues: ['batches_listed' => $releasedId],
                newValues: ['batches_relinked_to' => $newHolder->id, 'batches' => $changed],
                request: null,
            );
        }

        return $changed;
    }

    /** @return \Illuminate\Support\Collection<int, MembershipCard> */
    private function patchesOf(Membership|int $card): \Illuminate\Support\Collection
    {
        $id = $card instanceof Membership ? $card->id : $card;

        return MembershipCard::query()->whereNotNull('membership_ids')->whereJsonContains('membership_ids', $id)->get()
            ->merge(MembershipCard::query()->whereNotNull('membership_ids')->whereJsonContains('membership_ids', (string) $id)->get())
            ->unique('id')->values();
    }
}
