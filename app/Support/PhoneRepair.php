<?php

namespace App\Support;

use App\Models\FacilityBranch;

/**
 * Puts an Egyptian phone number back into the shape the directory expects.
 *
 * Two shapes are valid, and nothing else is:
 *   - mobile:   11 digits beginning "01"   (01208999581)
 *   - landline:  8 digits, no area code    (63222328)
 *
 * Numbers imported from spreadsheets arrive with the area code still attached
 * ("066 3222328"), with several numbers packed into one cell, with the country
 * code in front, or written in Arabic-Indic digits. The repair is deliberately
 * mechanical — the area code is dropped by keeping the last eight digits, which
 * is what "066 3222328" -> "63222328" and "0212345678" -> "12345678" both are —
 * and every suggestion is shown to an admin, who can edit it, before anything
 * is written.
 *
 * A branch keeps its numbers as typed entries ({number, type}); the type a
 * number carries is never changed by a repair, only the number itself.
 */
final class PhoneRepair
{
    public const MOBILE_LENGTH = 11;

    public const LANDLINE_LENGTH = 8;

    /** Already in shape — stored exactly as it should be. */
    public const STATUS_OK = 'ok';

    /** Wrong as stored, and the correct form can be worked out. */
    public const STATUS_FIXED = 'fixed';

    /** Wrong as stored, with too little to go on — a human has to retype it. */
    public const STATUS_REVIEW = 'review';

    /** Out of the kind being worked on — carried through exactly as stored. */
    public const STATUS_SKIPPED = 'skipped';

    /** The kinds a repair can be narrowed to. */
    public const KINDS = ['mobile', 'landline'];

    /**
     * Read one number and say what it should be.
     *
     * The kind is settled by the digits first — anything starting "01" is a
     * mobile, whatever it was filed as — and only then by the type the entry
     * declares, so a mobile that lost a digit is sent back for a human rather
     * than trimmed into a landline.
     *
     * @return array{original: string, suggestion: string|null, kind: string, status: string}
     */
    public static function inspect(string $number, ?string $type = null): array
    {
        $digits = self::national($number);

        if ($digits === '') {
            return self::entry($number, null, 'unknown');
        }

        if (str_starts_with($digits, '01')) {
            if (strlen($digits) === self::MOBILE_LENGTH) {
                return self::entry($number, $digits, 'mobile');
            }

            // Longer than a mobile: something is glued to the end of it.
            if (strlen($digits) > self::MOBILE_LENGTH) {
                return self::entry($number, substr($digits, 0, self::MOBILE_LENGTH), 'mobile');
            }

            // Shorter: a digit was lost, and there is no way to guess which.
            return self::entry($number, null, 'mobile');
        }

        // Filed as a mobile but not shaped like one — nothing safe to suggest.
        if ($type !== null && $type !== FacilityBranch::PHONE_LANDLINE) {
            return self::entry($number, null, 'mobile');
        }

        // Everything else is a landline, which is the last eight digits: the
        // area code in front of it is what makes it too long.
        if (strlen($digits) >= self::LANDLINE_LENGTH) {
            return self::entry($number, substr($digits, -self::LANDLINE_LENGTH), 'landline');
        }

        return self::entry($number, null, 'landline');
    }

    /**
     * Read a branch's whole phone list: split the packed entries apart, repair
     * each number, and hand back the list as it should be stored.
     *
     * Numbers that cannot be repaired are carried through unchanged so a
     * confirmed fix never silently drops one.
     *
     * $kinds narrows the work to mobiles or to landlines: a number of the other
     * kind is marked skipped and kept exactly as it is, so an admin fixing the
     * landlines cannot be made to rewrite a mobile in the same breath.
     *
     * @param  string|array<int|string, mixed>|null  $phones  as stored
     * @param  array<int, string>  $kinds
     * @return array{
     *     current: array<int, string>,
     *     suggested: array<int, array{number: string, type: string}>,
     *     entries: array<int, array{original: string, suggestion: string|null, kind: string, status: string, type: string, problem: bool}>,
     *     changed: bool,
     *     needs_review: bool,
     *     has_problem: bool
     * }
     */
    public static function repair(string|array|null $phones, array $kinds = self::KINDS): array
    {
        // What the column literally holds, packed cells and all. The model hands
        // back a tidied list, so the raw value is what the page has to show and
        // what "is this number stored on its own?" has to be asked against.
        $current = self::storedValues($phones);

        $entries = [];
        $suggested = [];
        $hasProblem = false;

        foreach (PhoneNumbers::entries($phones) as $stored) {
            $entry = self::inspect($stored['number'], $stored['type']);
            $entry['type'] = $stored['type'];

            // A real kind that is not being worked on right now stays untouched.
            if (in_array($entry['kind'], self::KINDS, true) && ! in_array($entry['kind'], $kinds, true)) {
                $entry['suggestion'] = null;
                $entry['status'] = self::STATUS_SKIPPED;
            }

            $number = $entry['suggestion'] ?? $entry['original'];

            // A number is wrong when it needs rewriting, and equally when it is
            // right but shares a cell with another number instead of standing
            // as its own entry.
            $entry['problem'] = $entry['status'] !== self::STATUS_SKIPPED
                && ($entry['status'] !== self::STATUS_OK || ! in_array($number, $current, true));

            $hasProblem = $hasProblem || $entry['problem'];
            $entries[] = $entry;

            if ($number !== '' && ! in_array($number, array_column($suggested, 'number'), true)) {
                $suggested[] = ['number' => $number, 'type' => $stored['type']];
            }
        }

        return [
            'current' => $current,
            'suggested' => $suggested,
            'entries' => $entries,
            'changed' => array_column($suggested, 'number') !== $current,
            'needs_review' => in_array(self::STATUS_REVIEW, array_column($entries, 'status'), true),
            // What puts the branch on the list: a number of the kind being
            // worked on that is not stored the way it should be.
            'has_problem' => $hasProblem,
        ];
    }

    /**
     * The phone column of a branch exactly as the database holds it, before the
     * model tidies it into typed entries.
     *
     * @return array<int, mixed>
     */
    public static function stored(FacilityBranch $branch): array
    {
        $raw = $branch->getRawOriginal('phone');

        if (is_array($raw)) {
            return $raw;
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * The stored value flattened to one string per column entry — a packed cell
     * stays packed, which is the point.
     *
     * @param  string|array<int|string, mixed>|null  $phones
     * @return array<int, string>
     */
    private static function storedValues(string|array|null $phones): array
    {
        $values = is_array($phones) ? $phones : [$phones];
        $out = [];

        foreach ($values as $value) {
            if (is_array($value)) {
                $value = $value['number'] ?? $value['phone'] ?? '';
            }

            $value = trim((string) (is_scalar($value) ? $value : ''));

            if ($value !== '') {
                $out[] = $value;
            }
        }

        return $out;
    }

    /**
     * The number as dialled inside Egypt: digits only, with the country code
     * and a missing leading zero sorted out.
     */
    private static function national(string $number): string
    {
        $digits = preg_replace('/\D+/', '', PhoneNumbers::foldDigits($number)) ?? '';

        if (str_starts_with($digits, '0020')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '20') && strlen($digits) >= self::MOBILE_LENGTH) {
            $digits = substr($digits, 2);
        }

        // A mobile typed without its leading zero, which is how it comes back
        // from anything that stored it internationally.
        if (preg_match('/^1[0-9]{9}$/', $digits) === 1) {
            $digits = '0'.$digits;
        }

        return $digits;
    }

    /**
     * @return array{original: string, suggestion: string|null, kind: string, status: string}
     */
    private static function entry(string $original, ?string $suggestion, string $kind): array
    {
        return [
            'original' => $original,
            'suggestion' => $suggestion,
            'kind' => $kind,
            'status' => match (true) {
                $suggestion === null => self::STATUS_REVIEW,
                $suggestion === $original => self::STATUS_OK,
                default => self::STATUS_FIXED,
            },
        ];
    }
}
