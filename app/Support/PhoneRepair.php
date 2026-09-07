<?php

namespace App\Support;

use App\Models\FacilityBranch;

/**
 * Puts an Egyptian phone number back into the shape the directory expects.
 *
 * Three shapes are valid, and nothing else is:
 *   - mobile:   11 digits beginning "01"   (01208999581)
 *   - landline: 10 digits, area code and all (0663222328)
 *   - hotline:  shorter than a landline, dialled as it stands (16064, 19011)
 *
 * Numbers imported from spreadsheets arrive with the country code in front,
 * with several numbers packed into one cell, or written in Arabic-Indic digits.
 * The repair is deliberately mechanical — a landline keeps its last ten digits,
 * which is what "0020663222328" -> "0663222328" is — and every suggestion is
 * shown to an admin, who can edit it, before anything is written.
 *
 * A hotline is never trimmed: it is short because that is what it is, so the
 * only thing done to one is to split it out of a packed cell.
 *
 * A branch keeps its numbers as typed entries ({number, type}); the type a
 * number carries is never changed by a repair, only the number itself.
 */
final class PhoneRepair
{
    public const MOBILE_LENGTH = 11;

    public const LANDLINE_LENGTH = 10;

    /** Shorter than a landline and dialled as it stands. */
    public const HOTLINE_MAX_LENGTH = self::LANDLINE_LENGTH - 1;

    /** Already in shape — stored exactly as it should be. */
    public const STATUS_OK = 'ok';

    /** Wrong as stored, and the correct form can be worked out. */
    public const STATUS_FIXED = 'fixed';

    /** Wrong as stored, with too little to go on — a human has to retype it. */
    public const STATUS_REVIEW = 'review';

    /** Out of the kind being worked on — carried through exactly as stored. */
    public const STATUS_SKIPPED = 'skipped';

    /** The kinds a repair can be narrowed to. */
    public const KINDS = ['mobile', 'landline', 'hotline'];

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

        // A short national number — 16064, 19011. It has no area code to strip
        // and nothing is missing from it, so it is already right as it stands.
        if (strlen($digits) <= self::HOTLINE_MAX_LENGTH) {
            return self::entry($number, $digits, 'hotline');
        }

        // Filed as a mobile but not shaped like one — nothing safe to suggest.
        if ($type !== null && ! in_array($type, [FacilityBranch::PHONE_LANDLINE, FacilityBranch::PHONE_HOTLINE], true)) {
            return self::entry($number, null, 'mobile');
        }

        // Everything else is a landline, which is the last ten digits: the
        // country code in front of it is what makes it too long.
        return self::entry($number, substr($digits, -self::LANDLINE_LENGTH), 'landline');
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
     *     entries: array<int, array{original: string, suggestion: string|null, kind: string, status: string, type: string, suggested_type: string|null, type_changed: bool, problem: bool}>,
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
            // What kind of line the digits say it is. A hotline filed as a
            // landline is as wrong as a mistyped number — the type predates the
            // hotline entirely, so most of them are — and it is just as fixable.
            $entry['suggested_type'] = self::typeFor($entry['kind'], $stored['type']);

            // A real kind that is not being worked on right now stays untouched.
            if (in_array($entry['kind'], self::KINDS, true) && ! in_array($entry['kind'], $kinds, true)) {
                $entry['suggestion'] = null;
                $entry['suggested_type'] = $stored['type'];
                $entry['status'] = self::STATUS_SKIPPED;
            }

            $entry['type_changed'] = $entry['suggested_type'] !== $stored['type'];
            $number = $entry['suggestion'] ?? $entry['original'];

            // A number is wrong when it needs rewriting, when it is filed as the
            // wrong kind of line, and equally when it is right but shares a cell
            // with another number instead of standing as its own entry.
            $entry['problem'] = $entry['status'] !== self::STATUS_SKIPPED
                && ($entry['status'] !== self::STATUS_OK
                    || $entry['type_changed']
                    || ! in_array($number, $current, true));

            $hasProblem = $hasProblem || $entry['problem'];
            $entries[] = $entry;

            if ($number !== '' && ! in_array($number, array_column($suggested, 'number'), true)) {
                $suggested[] = ['number' => $number, 'type' => $entry['suggested_type']];
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
     * The type a number of this shape should be filed under.
     *
     * WhatsApp is not a shape — it says how the number is reached, and only a
     * person knows that — so a number already filed under one of those keeps it.
     * Everything else follows the digits.
     */
    private static function typeFor(string $kind, ?string $current): ?string
    {
        if (in_array($current, [FacilityBranch::PHONE_WHATSAPP, FacilityBranch::PHONE_MOBILE_WHATSAPP], true)) {
            return $current;
        }

        return match ($kind) {
            'mobile' => FacilityBranch::PHONE_MOBILE,
            'landline' => FacilityBranch::PHONE_LANDLINE,
            'hotline' => FacilityBranch::PHONE_HOTLINE,
            default => $current,
        };
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

        $hadCountryCode = false;
        if (str_starts_with($digits, '0020')) {
            $digits = substr($digits, 4);
            $hadCountryCode = true;
        } elseif (str_starts_with($digits, '20') && strlen($digits) >= self::MOBILE_LENGTH) {
            $digits = substr($digits, 2);
            $hadCountryCode = true;
        }

        // An Egyptian number written internationally drops the leading zero the
        // national form carries, so taking the country code off leaves it a
        // digit short — "+20 66 3222328" is the landline "0663222328". Only a
        // number that actually had a country code gets the zero back: a hotline
        // is short because that is what it is, not because a zero went missing.
        if ($hadCountryCode && $digits !== '' && ! str_starts_with($digits, '0')) {
            $digits = '0'.$digits;
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
