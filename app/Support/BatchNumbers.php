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
        return MembershipCard::query()->get(['id', 'batch_name', 'prefix', 'start_number', 'quantity', 'membership_ids', 'skipped_numbers', 'number_list']);
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

        // A random batch lists its numbers one by one.
        if (! empty($batch->number_list)) {
            return in_array($n, array_map('intval', $batch->number_list), true);
        }

        return $n >= (int) $batch->start_number && $n <= static::endNumber($batch)
            && ! in_array($n, static::skipped($batch), true);
    }

    /** Numbers inside the range this batch skipped (another card already held them). */
    public static function skipped(MembershipCard $batch): array
    {
        return array_map('intval', $batch->skipped_numbers ?: []);
    }

    /** Last number of the range: the cards, plus the numbers skipped on the way. */
    public static function endNumber(MembershipCard $batch): int
    {
        if (! empty($batch->number_list)) {
            return (int) max(array_map('intval', $batch->number_list));
        }

        return (int) $batch->start_number + max(0, (int) $batch->quantity - 1) + count(static::skipped($batch));
    }

    /**
     * $quantity random free numbers (6 digits like every new member), none held by a membership or reserved by a batch.
     *
     * @return array<int,string> stored numbers (prefix + digits), ascending
     */
    public static function randomNumbers(string $prefix, int $quantity, ?iterable $batches = null): array
    {
        $batches = $batches ?? static::batches();
        $picked = [];
        $guard = 0;
        while (count($picked) < $quantity && $guard++ < 50) {
            $cand = [];
            for ($i = 0; $i < ($quantity - count($picked)) * 2 + 20; $i++) {
                $cand[$prefix.random_int(100000, 999999)] = true;
            }
            $cand = array_keys($cand);
            $held = [];
            foreach (array_chunk($cand, 1000) as $chunk) {
                foreach (Membership::withTrashed()->whereIn('membership_number', $chunk)->pluck('membership_number') as $n) {
                    $held[$n] = true;
                }
            }
            foreach ($cand as $c) {
                if (count($picked) >= $quantity) {
                    break;
                }
                if (! isset($held[$c]) && ! isset($picked[$c]) && static::reservingBatch($c, $batches) === null) {
                    $picked[$c] = true;
                }
            }
        }
        $out = array_keys($picked);
        sort($out, SORT_NATURAL);

        return $out;
    }

    /** Which of these stored numbers are already held by a membership or reserved by a batch. */
    public static function heldAmong(array $numbers, ?iterable $batches = null): array
    {
        $batches = $batches ?? static::batches();
        $held = [];
        foreach (array_chunk($numbers, 1000) as $chunk) {
            foreach (Membership::withTrashed()->whereIn('membership_number', $chunk)->pluck('membership_number') as $n) {
                $held[$n] = true;
            }
        }
        foreach ($numbers as $c) {
            if (! isset($held[$c]) && static::reservingBatch($c, $batches) !== null) {
                $held[$c] = true;
            }
        }

        return array_keys($held);
    }

    /**
     * Which numbers a new batch would take. With $skipTaken the held ones are stepped over and the run goes on until
     * $quantity free numbers are found; without it the run is plain start..start+quantity-1 and `taken` says what clashes.
     *
     * @return array{numbers: array<int,string>, taken: array<int,string>, skipped: array<int,int>, end: int}
     */
    public static function plan(string $prefix, int $start, int $quantity, bool $skipTaken, ?iterable $batches = null): array
    {
        $batches = $batches ?? static::batches();
        $numbers = $taken = $skipped = [];
        $next = $start;
        $end = $start - 1;
        $scanned = 0;
        // Look a window ahead at a time (never the whole table); a skipping run widens it only when it came up short.
        while (count($numbers) < $quantity && $scanned < $quantity + 20000) {
            $size = $skipTaken ? max(50, ($quantity - count($numbers)) * 2) : $quantity;
            $window = [];
            for ($i = 0; $i < $size; $i++) {
                $window[] = $prefix.($next + $i);
            }
            $held = array_flip(static::heldAmong($window, $batches));
            foreach ($window as $i => $c) {
                if (count($numbers) >= $quantity) {
                    break;
                }
                $end = $next + $i;
                if (isset($held[$c])) {
                    $taken[] = $c;
                    if ($skipTaken) {
                        $skipped[] = $next + $i;
                        continue;
                    }
                }
                $numbers[] = $c;
            }
            $next += $size;
            $scanned += $size;
            if (! $skipTaken) {
                break;
            }
        }

        return ['numbers' => $numbers, 'taken' => $taken, 'skipped' => $skipped, 'end' => $end];
    }

    /** Every number a batch reserves, as stored. */
    public static function numbersOf(MembershipCard $batch): array
    {
        if (! empty($batch->number_list)) {
            return array_map(fn ($n) => ($batch->prefix ?? '').(int) $n, $batch->number_list);
        }

        $out = [];
        $skipped = static::skipped($batch);
        for ($n = (int) $batch->start_number; $n <= static::endNumber($batch); $n++) {
            if (! in_array($n, $skipped, true)) {
                $out[] = ($batch->prefix ?? '').$n;
            }
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
