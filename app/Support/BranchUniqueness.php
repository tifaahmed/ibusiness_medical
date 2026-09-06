<?php

namespace App\Support;

use App\Models\FacilityBranch;

/**
 * Keeps two branches of the same facility from carrying the same name or the
 * same address.
 *
 * The check is scoped to one facility on purpose: "Nasr City" is a perfectly
 * good branch name for every facility that has one, it just may not appear
 * twice under the same facility. Names and addresses are translatable, so the
 * comparison runs per locale — a repeated Arabic name is a duplicate even when
 * the English ones differ.
 *
 * Values are compared loosely (trimmed, whitespace collapsed, case folded) so
 * "Main Branch" and "main  branch " count as the same entry.
 */
final class BranchUniqueness
{
    /** Translatable fields that must not repeat inside one facility. */
    public const FIELDS = ['name', 'address'];

    /**
     * Duplicates inside the branch list posted by the facility form.
     *
     * That form submits the facility's complete branch list — anything missing
     * from it is deleted on save — so comparing the list against itself covers
     * the whole facility.
     *
     * @param  array<int, mixed>  $branches
     * @return array<int, array{key: string, message: string}> keyed for the validator, e.g. "branches.2.name.ar"
     */
    public static function duplicatesInList(array $branches, string $prefix = 'branches'): array
    {
        $errors = [];

        foreach (self::FIELDS as $field) {
            $seen = [];

            foreach ($branches as $index => $branch) {
                if (! is_array($branch)) {
                    continue;
                }

                foreach (self::comparisonKeys($branch[$field] ?? null) as $locale => $key) {
                    if (isset($seen[$locale][$key])) {
                        $errors[] = [
                            'key' => "{$prefix}.{$index}.{$field}.{$locale}",
                            'message' => self::message($field, $locale),
                        ];

                        continue;
                    }

                    $seen[$locale][$key] = $index;
                }
            }
        }

        return $errors;
    }

    /**
     * Duplicates between one submitted branch and the branches already stored
     * for its facility — the standalone branch create/edit pages, where the
     * facility's other branches are not part of the payload.
     *
     * The keys stay at field level ("name") because that is what the standalone
     * form binds its inputs to; the locale is named in the message instead.
     *
     * @param  array<string, mixed>  $input  the submitted branch fields
     * @return array<int, array{key: string, message: string}>
     */
    public static function duplicatesInFacility(array $input, mixed $facilityId, ?int $ignoreBranchId = null): array
    {
        if (empty($facilityId)) {
            return [];
        }

        $siblings = FacilityBranch::query()
            ->where('facility_id', $facilityId)
            ->when($ignoreBranchId, fn ($query) => $query->whereKeyNot($ignoreBranchId))
            ->get(['id', 'name', 'address']);

        $errors = [];

        foreach (self::FIELDS as $field) {
            $submitted = self::comparisonKeys($input[$field] ?? null);

            if ($submitted === []) {
                continue;
            }

            $clashed = [];

            foreach ($siblings as $sibling) {
                $taken = self::comparisonKeys($sibling->getTranslations($field));

                foreach ($submitted as $locale => $key) {
                    if (($taken[$locale] ?? null) === $key && ! isset($clashed[$locale])) {
                        $clashed[$locale] = true;
                        $errors[] = [
                            'key' => $field,
                            'message' => self::message($field, $locale),
                        ];
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * One comparison key per filled-in locale; blank translations are skipped so
     * branches that simply have no English address don't collide with each other.
     *
     * @return array<string, string> locale => key
     */
    private static function comparisonKeys(mixed $translations): array
    {
        if (! is_array($translations)) {
            return [];
        }

        $keys = [];

        foreach ($translations as $locale => $value) {
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

    private static function message(string $field, string $locale): string
    {
        $language = match ($locale) {
            'ar' => 'Arabic ',
            'en' => 'English ',
            default => '',
        };

        return $field === 'address'
            ? "Another branch of this facility already uses this {$language}address."
            : "Another branch of this facility already uses this {$language}name.";
    }
}
