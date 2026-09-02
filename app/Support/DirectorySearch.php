<?php

namespace App\Support;

/**
 * Matching what somebody typed against the directory, in either language.
 *
 * Two jobs, and they have to agree exactly or a search finds nothing: the term
 * is folded here in PHP, and the columns it is compared against are folded by
 * the SQL these helpers build. A letter folded on one side and not the other is
 * a query that silently misses.
 *
 * The fold exists because the same place is written الاسماعيلية and
 * الإسماعيلية, ابو and أبو, مدينه and مدينة. Nobody reaches for the hamza on a
 * phone keyboard, so a search that insists on it is a search that fails.
 *
 * The set folded here is deliberately the same one the storefront's own
 * highlighter folds (`resources/js/marketing/lib/match.tsx` over in deilar):
 * the browser marks the letters it believes matched, so a letter this side
 * treats as equal and the browser does not is a row that lights up nowhere.
 */
final class DirectorySearch
{
    /**
     * Letter forms that are the same letter to somebody searching.
     *
     * One character to one character, always: `replace()` chains are applied to
     * the term and to the column, and a fold that changed a string's length
     * would make the two disagree about what a substring is.
     */
    private const FOLD = [
        'أ' => 'ا',
        'إ' => 'ا',
        'آ' => 'ا',
        'ٱ' => 'ا',
        'ى' => 'ي',
        'ئ' => 'ي',
        'ة' => 'ه',
        'ؤ' => 'و',
    ];

    /** Harakat and the dagger alef: written by some, typed by almost nobody. */
    private const MARKS = '/[\x{064B}-\x{065F}\x{0670}]/u';

    /**
     * The typed term, reduced to what it is actually being matched on.
     *
     * Lowercased for the Latin half, folded for the Arabic half, stripped of
     * harakat, and collapsed to single spaces so a double space between two
     * words is not a word of its own.
     */
    public static function normalise(string $term): string
    {
        $term = mb_strtolower(trim($term));
        $term = strtr($term, self::FOLD);
        $term = preg_replace(self::MARKS, '', $term) ?? $term;

        return trim(preg_replace('/\s+/u', ' ', $term) ?? $term);
    }

    /**
     * The term split into the words worth matching separately.
     *
     * Every word has to match somewhere for a row to count, which is what makes
     * "مركز الشرقية" narrower than either word alone. Single characters are
     * dropped — one letter matches most of the directory — unless that is all
     * there is, in which case the whole term is used as typed.
     *
     * @return list<string>
     */
    public static function words(string $term): array
    {
        $normalised = self::normalise($term);

        if ($normalised === '') {
            return [];
        }

        $words = array_values(array_filter(
            preg_split('/\s+/u', $normalised) ?: [],
            fn (string $word): bool => mb_strlen($word) > 1,
        ));

        return $words === [] ? [$normalised] : $words;
    }

    /**
     * Just the digits, for matching a phone number.
     *
     * A visitor copies a number with whatever spacing it was printed with, and
     * branches store them equally inconsistently — comparing digit runs is the
     * only version of this that works both ways round.
     */
    public static function digits(string $term): string
    {
        return preg_replace('/\D+/', '', PhoneNumbers::foldDigits($term)) ?? '';
    }

    /**
     * SQL, as a string for `whereRaw()`, for one translation of a translatable JSON column, folded.
     *
     * `json_unquote(json_extract(...))` comes back as a binary string in MySQL,
     * so its comparisons are case SENSITIVE however the table is collated —
     * hence the explicit `lower()` rather than trusting the column's collation.
     *
     * @param  string  $column  a bare column name, never user input
     */
    public static function translated(string $column, string $locale): string
    {
        $path = '$."'.$locale.'"';

        return self::fold("json_unquote(json_extract(`{$column}`, '{$path}'))");
    }

    /**
     * SQL for a plain (non-translatable) column, folded the same way.
     *
     * @param  string  $column  a bare column name, never user input
     */
    public static function plain(string $column): string
    {
        return self::fold("`{$column}`");
    }

    /**
     * SQL for a JSON column matched as raw text, with every separator a phone
     * number might have been typed with removed.
     *
     * Deliberately not parsed out of the JSON: `phone` holds a list, and a
     * `like` over the whole stored array finds a number in any position of it
     * without a join or a JSON table function.
     *
     * @param  string  $column  a bare column name, never user input
     */
    public static function digitsOf(string $column): string
    {
        $expression = "`{$column}`";

        foreach ([' ', '-', '+', '(', ')', '/', '.'] as $separator) {
            $expression = "replace({$expression}, '{$separator}', '')";
        }

        return $expression;
    }

    /** Wraps a SQL string expression in the same fold `normalise()` applies. */
    private static function fold(string $expression): string
    {
        $folded = "lower({$expression})";

        foreach (self::FOLD as $from => $to) {
            $folded = "replace({$folded}, '{$from}', '{$to}')";
        }

        return $folded;
    }
}
