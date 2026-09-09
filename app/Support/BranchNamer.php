<?php

namespace App\Support;

use App\Models\Facility;
use App\Models\FacilityBranch;
use Illuminate\Support\Collection;

/**
 * Builds the canonical name for every branch of one facility.
 *
 * The house style is "<facility> - <city>", in each language, which is what an
 * admin types by hand anyway (it is what the "Add city to name" button on the
 * branch form appends). Two branches of one facility in the same city would
 * then read identically, so the second and later ones are numbered: "… 2",
 * "… 3". That is exactly the rule {@see BranchUniqueness} enforces on save, so
 * a facility renamed by this class can always be saved again afterwards.
 *
 * Comparison is borrowed from that class in spirit — trimmed, whitespace
 * collapsed, case folded — because a name that only differs by spacing is a
 * duplicate as far as the validator is concerned.
 *
 * A branch is numbered as a whole, not per language: the suffix is chosen so
 * that *every* language is free at that number, which keeps the Arabic and the
 * English name of one branch carrying the same number. Numbering each language
 * on its own would produce "Clinic - Cairo 2" sitting beside an unnumbered
 * Arabic name, which reads as a mistake.
 */
final class BranchNamer
{
    /** The languages a branch name is written in. */
    public const LOCALES = ['ar', 'en'];

    /** Nobody wants "Clinic - Cairo 300"; a facility that deep is a data problem. */
    private const MAX_SUFFIX = 500;

    /**
     * Work out what each branch of a facility should be called.
     *
     * @param  Collection<int, FacilityBranch>  $branches  every branch of the facility, oldest first
     * @param  array<int, int>  $writableIds  the branches this admin may rename; the rest keep
     *                                        their names and merely reserve them
     * @param  bool  $onlyProblems  true to leave a branch alone when its name is already
     *                              filled in and unique, false to give every branch the
     *                              canonical name
     * @return array<int, array<string, string>> branch id => [locale => name], only for
     *                                           branches whose name should change
     */
    public static function nameBranches(
        Facility $facility,
        Collection $branches,
        array $writableIds,
        bool $onlyProblems = false,
    ): array {
        $writable = array_flip($writableIds);
        $taken = [];
        $changes = [];

        // Branches this admin cannot touch are immovable: their names are
        // reserved first so nothing we write can collide with one of them.
        foreach ($branches as $branch) {
            if (! isset($writable[$branch->id])) {
                self::reserve($taken, $branch->getTranslations('name'));
            }
        }

        foreach ($branches as $branch) {
            if (! isset($writable[$branch->id])) {
                continue;
            }

            $current = $branch->getTranslations('name');

            // "Only fix duplicates and blanks": a name that is filled in for
            // every language and clashes with nothing is somebody's considered
            // choice, so it stands. Anything else is rewritten in full — a
            // branch half-named in one language is not worth preserving.
            if ($onlyProblems && self::isComplete($current) && self::isFree($taken, $current)) {
                self::reserve($taken, $current);

                continue;
            }

            $name = self::firstFreeName($facility, $branch, $taken);
            self::reserve($taken, $name);

            if (self::keys($name) !== self::keys($current)) {
                $changes[$branch->id] = $name;
            }
        }

        return $changes;
    }

    /**
     * The canonical name at the lowest number that is free in every language.
     *
     * @param  array<string, array<string, true>>  $taken
     * @return array<string, string>
     */
    private static function firstFreeName(Facility $facility, FacilityBranch $branch, array $taken): array
    {
        $base = self::baseName($facility, $branch);

        for ($suffix = 1; $suffix <= self::MAX_SUFFIX; $suffix++) {
            $name = [];

            foreach ($base as $locale => $value) {
                $name[$locale] = $suffix === 1 ? $value : $value.' '.$suffix;
            }

            if (self::isFree($taken, $name)) {
                return $name;
            }
        }

        // Past the ceiling, fall back to something that cannot collide at all
        // rather than looping or handing back a duplicate.
        return array_map(fn (string $value) => $value.' #'.$branch->id, $base);
    }

    /**
     * "<facility> - <city>" per language, or the facility alone when the branch
     * has no city yet. A language the facility itself does not carry borrows
     * the other one, so a name is never left empty.
     *
     * @return array<string, string>
     */
    private static function baseName(Facility $facility, FacilityBranch $branch): array
    {
        $names = [];

        foreach (self::LOCALES as $locale) {
            $facilityName = self::translation($facility->getTranslations('name'), $locale);
            $cityName = $branch->city
                ? self::translation($branch->city->getTranslations('name'), $locale)
                : '';

            $names[$locale] = $cityName === ''
                ? $facilityName
                : trim($facilityName.' - '.$cityName);
        }

        return $names;
    }

    /**
     * One language of a translatable value, falling back to the other so a row
     * that was only ever filled in once still produces a name.
     *
     * @param  array<string, mixed>  $translations
     */
    private static function translation(array $translations, string $locale): string
    {
        $value = trim((string) ($translations[$locale] ?? ''));

        if ($value !== '') {
            return $value;
        }

        foreach ($translations as $other) {
            $other = trim((string) $other);

            if ($other !== '') {
                return $other;
            }
        }

        return '';
    }

    /** A name filled in for every language this application writes. */
    private static function isComplete(array $name): bool
    {
        foreach (self::LOCALES as $locale) {
            if (trim((string) ($name[$locale] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, array<string, true>>  $taken
     * @param  array<string, string>  $name
     */
    private static function isFree(array $taken, array $name): bool
    {
        foreach (self::keys($name) as $locale => $key) {
            if (isset($taken[$locale][$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, array<string, true>>  $taken
     * @param  array<string, string>  $name
     */
    private static function reserve(array &$taken, array $name): void
    {
        foreach (self::keys($name) as $locale => $key) {
            $taken[$locale][$key] = true;
        }
    }

    /**
     * Comparison keys per filled-in language, the same loose reading the
     * validator uses: trimmed, whitespace collapsed, case folded. A blank
     * language reserves nothing — two branches with no Arabic name are not
     * duplicates of each other.
     *
     * @param  array<string, mixed>  $name
     * @return array<string, string>
     */
    private static function keys(array $name): array
    {
        $keys = [];

        foreach ($name as $locale => $value) {
            if (! is_string($value)) {
                continue;
            }

            $value = trim((string) preg_replace('/\s+/u', ' ', $value));

            if ($value !== '') {
                $keys[(string) $locale] = mb_strtolower($value);
            }
        }

        return $keys;
    }
}
