<?php

namespace App\Support;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Who a member may add as a relative, and how the NAME is built (owner decision 2026-11-17). The backend decides; clients only draw it.
 *
 *  - MALE primary: sons and daughters by FIRST name (his own name is appended), no limit on children; two women at most, each with the full
 *    name — one `wife` and one `mother`; his `father` is typed with the GRANDFATHER's name only (the father's first name is the
 *    primary's SECOND name).
 *  - FEMALE primary: her `father` the same way (her second name + the typed grandfather name); one woman only, her `mother`, full name;
 *    children only after her `husband` is on the card (typed with his full name) — a child then carries the HUSBAND's name.
 *
 * There is no limit on the number of relatives of any kind (2026-11-24); each one is paid for separately.
 */
final class RelativeRules
{
    /** @var list<string> */
    public const CHILD = ['son', 'daughter'];

    /** The husband of a female primary, if one is on the card (not refused). */
    public static function husbandOf(Membership $primary): ?Membership
    {
        return $primary->relatives()->with('user')->where('relationship', 'husband')->where('approval_status', '!=', 'rejected')->first();
    }

    /** @throws ValidationException when this relation is not allowed on this card right now */
    public static function assertMayAdd(Membership $primary, User $member, string $relationship): void
    {
        $female = $member->gender === 'female';

        if (! in_array($member->gender, ['male', 'female'], true)) {
            throw ValidationException::withMessages(['relationship' => ['Complete your profile (gender) before adding relatives.']]);
        }

        $has = fn (string $rel): bool => $primary->relatives()->where('relationship', $rel)->where('approval_status', '!=', 'rejected')->exists();

        $rule = match (true) {
            in_array($relationship, self::CHILD, true) => $female && self::husbandOf($primary) === null ? 'Add your husband first, then your children.' : null,
            $relationship === 'wife' => $female ? 'Only a male member can add a wife.' : ($has('wife') ? 'A wife is already on this card.' : null),
            $relationship === 'husband' => ! $female ? 'Only a female member can add a husband.' : ($has('husband') ? 'A husband is already on this card.' : null),
            $relationship === 'mother' => $has('mother') ? 'A mother is already on this card.' : null,
            $relationship === 'father' => $has('father') ? 'A father is already on this card.' : null,
            default => 'This relation is added by customer service.',
        };

        if ($rule !== null) {
            throw ValidationException::withMessages(['relationship' => [$rule]]);
        }
    }

    /** The father's full name: the primary's SECOND name + the grandfather's name the member typed. */
    public static function fatherName(User $member, string $typedGrandfather): string
    {
        $words = array_values(array_filter(explode(' ', preg_replace('/\s+/u', ' ', trim((string) $member->name)))));

        if (count($words) < 2) {
            throw ValidationException::withMessages(['name' => ['Your own name needs at least two names before you can add your father.']]);
        }

        return $words[1].' '.preg_replace('/\s+/u', ' ', trim($typedGrandfather));
    }

    /** The name a child carries: the father's (the member's own for a man, the husband's for a woman). */
    public static function childSurname(Membership $primary, User $member): string
    {
        if ($member->gender === 'female') {
            return trim((string) self::husbandOf($primary)?->user?->name);
        }

        return trim((string) $member->name);
    }
}
