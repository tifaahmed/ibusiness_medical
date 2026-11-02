<?php

namespace App\Support;

use App\Models\Membership;
use App\Models\MembershipCard;
use Illuminate\Support\Facades\DB;

/**
 * Card batches ("patches", `membership_card_patches`) own a RANGE of numbers — prefix + start_number … start_number + quantity - 1 —
 * but no memberships: printing 1,000 cards must not leave 1,000 hidden members behind. A number in a batch's range is therefore
 * RESERVED: nothing generates it for a new member, and when an admin (or an import) gives a real membership one of those numbers,
 * {@see linkMembership()} files that membership under the batch (`membership_ids`), exactly as a takeover relinks one.
 */
class BatchNumbers
{
    /** @return \Illuminate\Support\Collection<int, MembershipCard> live batches, only the columns a range needs */
    public static function batches()
    {
        return MembershipCard::query()->get(['id', 'batch_name', 'prefix', 'start_number', 'quantity', 'membership_ids']);
    }

    /** The batch whose range holds this number, if any. */
    public static function reservingBatch(string $number, ?iterable $batches = null, ?int $ignoreBatchId = null): ?MembershipCard
    {
        foreach ($batches ?? static::batches() as $batch) {
            if ($ignoreBatchId !== null && $batch->id === $ignoreBatchId) {
                continue;
            }
            if (static::covers($batch, $number)) {
                return $batch;
            }
        }

        return null;
    }

    public static function covers(MembershipCard $batch, string $number): bool
    {
        $prefix = (string) ($batch->prefix ?? '');
        if ($prefix !== '' && ! str_starts_with($number, $prefix)) {
            return false;
        }
        $rest = substr($number, strlen($prefix));
        if ($rest === '' || strlen($rest) > 12 || ! ctype_digit($rest) || (string) (int) $rest !== $rest) {
            return false;
        }
        $n = (int) $rest;

        return $n >= (int) $batch->start_number && $n <= (int) $batch->start_number + max(0, (int) $batch->quantity - 1);
    }

    /** Every number a batch reserves, as stored. */
    public static function numbersOf(MembershipCard $batch): array
    {
        $out = [];
        for ($i = 0; $i < (int) $batch->quantity; $i++) {
            $out[] = ($batch->prefix ?? '').((int) $batch->start_number + $i);
        }

        return $out;
    }

    /** File a membership under the batch that reserved its number. Idempotent; returns the batch it joined. */
    public static function linkMembership(Membership $membership): ?MembershipCard
    {
        $batch = static::reservingBatch((string) $membership->membership_number);
        if ($batch === null) {
            return null;
        }

        DB::transaction(function () use ($batch, $membership) {
            $fresh = MembershipCard::query()->lockForUpdate()->find($batch->id);
            $ids = $fresh?->membership_ids ?: [];
            if ($fresh && ! in_array($membership->id, $ids, true)) {
                $ids[] = $membership->id;
                $fresh->forceFill(['membership_ids' => array_values($ids)])->save();
            }
        });

        return $batch;
    }
}
