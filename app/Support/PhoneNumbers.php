<?php

namespace App\Support;

use App\Models\FacilityBranch;

/**
 * Turns a raw phone value into a clean list of individual numbers.
 *
 * Imported spreadsheets have historically packed several numbers into one
 * cell with whatever separator the branch happened to use — a slash, a
 * comma, or a hyphen sitting between two spaces. Stored as a single array
 * entry, those strings blow past the 20-character limit on a branch phone
 * and make the facility impossible to save from the admin form. This
 * splits them back apart so every entry is one number, folds Arabic-Indic
 * digits to ASCII, and strips the spaces some numbers were typed with
 * ("066 3400006" -> "0663400006").
 */
final class PhoneNumbers
{
    /** Longest a single normalised number is allowed to be. */
    public const MAX_LENGTH = 20;

    /**
     * @param  string|array<int, string|null>|null  $raw
     * @return array<int, string> one trimmed number per entry, de-duplicated, order preserved
     */
    public static function split(string|array|null $raw): array
    {
        $values = is_array($raw) ? $raw : [$raw];
        $out = [];

        foreach ($values as $value) {
            $value = self::foldDigits(trim((string) ($value ?? '')));
            if ($value === '') {
                continue;
            }

            // Split on: slash / backslash, Latin or Arabic comma, semicolon,
            // pipe, newlines, and a hyphen or dash flanked by whitespace. A
            // bare hyphen inside a number ("02-33046378") is left alone here.
            $parts = preg_split('#\s*[/\\\\،,;|]\s*|\s+[-–—]\s+|\R+#u', $value) ?: [$value];

            foreach ($parts as $part) {
                // A single number never contains a space — the ones that do are
                // just typed with digit grouping ("066 3400006").
                $part = preg_replace('/\s+/u', '', trim($part)) ?? '';

                foreach (self::breakOverlong($part) as $number) {
                    if ($number !== '') {
                        $out[] = $number;
                    }
                }
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Last resort for a part that is still too long after the separator pass:
     * two full numbers glued together with no clear delimiter (e.g.
     * "02-33046378-01210541111"). Peel 11-digit Egyptian mobiles off the end
     * of the digit run and keep whatever is left as the landline.
     *
     * @return array<int, string>
     */
    private static function breakOverlong(string $part): array
    {
        if ($part === '' || mb_strlen($part) <= self::MAX_LENGTH) {
            return [$part];
        }

        $digits = preg_replace('/\D+/', '', $part) ?? '';
        if (strlen($digits) < 20 || strlen($digits) > 33) {
            // Not obviously a run of glued numbers — hand it back untouched and
            // let validation reject it so a human can sort it out.
            return [$part];
        }

        $chunks = [];
        while (strlen($digits) > 11) {
            $chunks[] = substr($digits, -11);
            $digits = substr($digits, 0, -11);
        }
        if ($digits !== '') {
            $chunks[] = $digits;
        }

        return array_reverse($chunks);
    }

    /**
     * Normalise any stored or submitted phone value into the shape a branch
     * keeps: one entry per number, each carrying the kind of line it is.
     *
     * Everything is accepted — a textarea string, a flat list of numbers from
     * an old row or a spreadsheet import, or the {number, type} entries the
     * admin form posts — because all three still arrive from somewhere. A
     * number given without a usable type is typed by {@see guessType()} rather
     * than guessed at by the caller.
     *
     * @param  string|array<int|string, mixed>|null  $raw
     * @return list<array{number: string, type: string}>
     */
    public static function entries(string|array|null $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $values = is_array($raw) ? $raw : [$raw];
        $out = [];
        $seen = [];

        foreach ($values as $value) {
            [$number, $type] = self::readEntry($value);

            // One entry can still carry several numbers ("011.../022..."), and
            // they all inherit the type the entry declared.
            foreach (self::split($number) as $single) {
                if (isset($seen[$single])) {
                    continue;
                }

                $seen[$single] = true;
                $out[] = [
                    'number' => $single,
                    'type' => $type ?? self::guessType($single),
                ];
            }
        }

        return $out;
    }

    /**
     * Just the numbers, in order — what a search index, a spreadsheet cell or
     * an API consumer that predates the types wants.
     *
     * @param  string|array<int|string, mixed>|null  $raw
     * @return list<string>
     */
    public static function numbers(string|array|null $raw): array
    {
        return array_map(fn (array $entry) => $entry['number'], self::entries($raw));
    }

    /**
     * What kind of line a number looks like, for values that arrive without a
     * type: rows written before types existed, and every spreadsheet import.
     *
     * Egyptian mobiles are 11 digits starting 01 (or the same with a country
     * code); anything else is taken for a landline. It is a starting point an
     * admin can correct in the form, not a fact about the number.
     */
    public static function guessType(string $number): string
    {
        $digits = preg_replace('/\D+/', '', self::foldDigits($number)) ?? '';

        // Drop a leading 20 / 0020 country code before measuring.
        $digits = preg_replace('/^(?:00)?20/', '0', $digits) ?? $digits;

        return strlen($digits) === 11 && str_starts_with($digits, '01')
            ? FacilityBranch::PHONE_MOBILE
            : FacilityBranch::PHONE_LANDLINE;
    }

    /**
     * Read one submitted entry into a number and, when it declared a usable
     * one, its type.
     *
     * @return array{0: string, 1: string|null}
     */
    private static function readEntry(mixed $value): array
    {
        if (is_array($value)) {
            $number = $value['number'] ?? $value['phone'] ?? '';
            $type = $value['type'] ?? null;

            return [
                is_scalar($number) ? (string) $number : '',
                is_string($type) && in_array($type, FacilityBranch::PHONE_TYPES, true) ? $type : null,
            ];
        }

        return [is_scalar($value) ? (string) $value : '', null];
    }

    /**
     * Fold Arabic-Indic (٠-٩) and Persian (۰-۹) digits to ASCII.
     *
     * Public because searching for a phone number has to fold the typed term
     * the same way the stored one was folded — see `DirectorySearch::digits()`.
     */
    public static function foldDigits(string $value): string
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            for ($i = 0; $i <= 9; $i++) {
                $map[mb_chr(0x0660 + $i)] = (string) $i;
                $map[mb_chr(0x06F0 + $i)] = (string) $i;
            }
        }

        return strtr($value, $map);
    }
}
