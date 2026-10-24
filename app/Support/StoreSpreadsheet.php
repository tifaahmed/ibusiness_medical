<?php

namespace App\Support;

use App\Models\StoreBranch;

/**
 * The one description of the store spreadsheet — sheet names, column headers
 * and how a phone list is written into (and read back out of) a cell. The
 * export writes with it and the import reads with it, so a file the export
 * produces is always one the import understands.
 */
class StoreSpreadsheet
{
    public const SHEET_STORES = 'Stores';

    public const SHEET_BRANCHES = 'Branches';

    public const SHEET_MANAGERS = 'Managers';

    /**
     * Key => label + width for the Stores sheet. The export's column picker and
     * the headers the importer looks for both come from here.
     *
     * @return array<string, array{label: string, width: int}>
     */
    public static function storeColumns(): array
    {
        return [
            'index' => ['label' => '#', 'width' => 8],
            'title' => ['label' => 'Title', 'width' => 36],
            'title_ar' => ['label' => 'Title (AR)', 'width' => 36],
            'slug' => ['label' => 'Slug', 'width' => 30],
            'categories' => ['label' => 'Categories', 'width' => 34],
            'sales' => ['label' => 'Sales rep', 'width' => 26],
            'contracted_at' => ['label' => 'Contracted date', 'width' => 16],
            'online_only' => ['label' => 'Online only', 'width' => 12],
            'branches' => ['label' => 'Branches', 'width' => 10],
            'managers' => ['label' => 'Managers', 'width' => 10],
            'products' => ['label' => 'Products', 'width' => 10],
            'created_at' => ['label' => 'Created at', 'width' => 18],
        ];
    }

    /** @return list<string> */
    public static function storeHeaders(): array
    {
        return array_column(self::storeColumns(), 'label');
    }

    /** @return list<string> */
    public static function branchHeaders(): array
    {
        return ['#', 'Store title', 'Store slug', 'Branch name', 'Branch name (AR)', 'Address', 'Address (AR)', 'Phone', 'Governorate', 'City', 'Latitude', 'Longitude'];
    }

    /** @return list<string> */
    public static function managerHeaders(): array
    {
        return ['#', 'Store title', 'Store slug', 'Manager name', 'Position', 'Phones'];
    }

    /**
     * "01012345678 (phone), 0226741234 (landline)" — the type travels with the
     * number, so a round trip through the spreadsheet does not lose it.
     *
     * @param  array<int, array{number: string, type: string}>|null  $entries
     */
    public static function writePhones(?array $entries): string
    {
        return collect(PhoneNumbers::entries($entries))
            ->map(fn (array $e) => $e['number'].' ('.$e['type'].')')
            ->implode(', ');
    }

    /**
     * Read a phone cell. A number may carry its type in brackets; one that does
     * not (a hand-typed file) is typed by {@see PhoneNumbers::guessType()}.
     *
     * @return list<array{number: string, type: string}>
     */
    public static function readPhones(?string $cell): array
    {
        $cell = trim((string) $cell);
        if ($cell === '') {
            return [];
        }

        $items = [];
        foreach (preg_split('/\s*[,;|،]\s*|\R+/u', $cell) ?: [] as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $type = null;
            if (preg_match('/^(.*?)\s*\(\s*([a-z_]+)\s*\)$/iu', $part, $m)) {
                $part = $m[1];
                $type = strtolower($m[2]);
            }

            $items[] = ['number' => $part, 'type' => in_array($type, StoreBranch::PHONE_TYPES, true) ? $type : null];
        }

        return PhoneNumbers::entries($items);
    }
}
