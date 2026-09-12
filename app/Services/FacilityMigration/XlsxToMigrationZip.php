<?php

namespace App\Services\FacilityMigration;

use App\Support\PhoneNumbers;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;

/**
 * Converts an xlsx/csv spreadsheet into the migration zip format
 * (manifest.json + data/facilities.json) that FacilityMigrationImporter expects.
 *
 * A workbook that came off a full site export also carries an Images sheet —
 * one row per picture, naming which facility/offer it belongs to and where its
 * bytes sit alongside the workbook (a `media/{id}/{file}` path). Those rows are
 * folded back into each facility's `media` array here, the same shape the
 * exporter would have written into a data/facilities.json, so the importer
 * never has to know its data ever passed through a spreadsheet at all — it is
 * FacilityMigrationImporter::locateMediaFile() that resolves that path against
 * the media/ folder extracted alongside this workbook.
 */
class XlsxToMigrationZip
{
    /**
     * Convert a spreadsheet file to a migration package zip.
     *
     * @return string path to the generated zip file
     */
    public function convert(string $spreadsheetPath): string
    {
        $extension = strtolower(pathinfo($spreadsheetPath, PATHINFO_EXTENSION));

        if ($extension === 'csv' || $extension === 'txt') {
            $reader = new CsvReader;
            $reader->setInputEncoding('UTF-8');
            $reader->setDelimiter(',');
        } else {
            $reader = IOFactory::createReaderForFile($spreadsheetPath);
        }
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($spreadsheetPath);

        $facilityColumns = [];
        $facilityRows = $this->parseFacilitySheet($spreadsheet, $facilityColumns);
        $hasBranchSheet = $spreadsheet->getSheetByName('Branches') !== null;
        $branchRows = $this->parseBranchSheet($spreadsheet);
        $hasManagerSheet = $spreadsheet->getSheetByName('Managers') !== null;
        $managerRows = $this->parseManagerSheet($spreadsheet);
        $hasOfferSheet = $spreadsheet->getSheetByName('Offers') !== null;
        $offerRows = $this->parseOfferSheet($spreadsheet);
        $imageRows = $this->parseImageSheet($spreadsheet);
        $package = $this->parsePackageSheet($spreadsheet);
        $sourceLookups = $this->parseLookupSheet($spreadsheet);

        $spreadsheet->disconnectWorksheets();

        // Every picture, grouped by the row it belongs to: a facility (keyed by
        // its own slug/name) or an offer (keyed by the offer's own slug — unique
        // whether it hangs off the facility directly or off one of its
        // branches, so one map serves both).
        $mediaByFacility = [];
        $mediaByOfferSlug = [];
        foreach ($imageRows as $image) {
            $entry = $this->imageMediaRow($image);
            if ($entry === null) {
                continue;
            }

            $owner = mb_strtolower(trim($image['owner'] ?? ''));
            if ($owner === 'facility') {
                $key = $this->facilityKey($image);
                if ($key !== '') {
                    $mediaByFacility[$key][] = $entry;
                }

                continue;
            }

            // "Offer" and "Branch Offer" both name the offer's own slug last in
            // the context cell ("branchslug / offerslug", or just the offer's
            // slug when it hangs off the facility directly).
            $segments = array_filter(array_map('trim', explode('/', (string) ($image['context'] ?? ''))));
            $offerSlug = mb_strtolower((string) end($segments));
            if ($offerSlug !== '') {
                $mediaByOfferSlug[$offerSlug][] = $entry;
            }
        }

        $branchesByFacility = [];
        foreach ($branchRows as $branch) {
            $key = $this->facilityKey($branch);
            if ($key === '') {
                continue;
            }
            $row = [
                'name' => [
                    'en' => $branch['name'] ?: null,
                    'ar' => $branch['name_ar'] ?: null,
                ],
                'address' => [
                    'en' => $branch['address'] ?: null,
                    'ar' => $branch['address_ar'] ?: null,
                ],
                'phone' => $this->branchPhoneList($branch['phone'] ?? null),
                'governorate' => $this->nameRef($branch['governorate'] ?? null),
                'city' => $this->nameRef($branch['city'] ?? null),
                'latitude' => $branch['latitude'] !== '' ? (float) $branch['latitude'] : null,
                'longitude' => $branch['longitude'] !== '' ? (float) $branch['longitude'] : null,
                'google_location_url' => $branch['google_location_url'] ?: null,
            ];
            // A workbook the site exported names each branch by the id and slug
            // it holds there. That is what the import matches on, so two
            // branches sharing a name still land on the two rows they came from.
            $branchesByFacility[$key][] = $row + $this->identity($branch) + $this->timestamps($branch);
        }

        // Managers hang off their facility the same way branches do.
        $managersByFacility = [];
        foreach ($managerRows as $manager) {
            $key = $this->facilityKey($manager);
            $name = trim($manager['name'] ?? '');
            if ($key === '' || $name === '') {
                continue;
            }
            $managersByFacility[$key][] = [
                'name' => $name,
                'position' => $manager['position'] ?: null,
                'phones' => $this->phoneList($manager['phones'] ?? null),
            ] + $this->identity($manager);
        }

        // Offers belong either to the facility or to one of its branches; the
        // branch slug column is what says which.
        $offersByFacility = [];
        $offersByBranch = [];
        foreach ($offerRows as $offer) {
            $facilityKey = mb_strtolower(trim($offer['facility_slug'] ?? ''));
            $branchKey = mb_strtolower(trim($offer['branch_slug'] ?? ''));
            if ($facilityKey === '' && $branchKey === '') {
                continue;
            }
            $offerSlug = mb_strtolower(trim($offer['slug'] ?? ''));

            $row = [
                'title' => $this->localeMap($offer['title'] ?? null, $offer['title_ar'] ?? null),
                'short_description' => $this->localeMap(
                    $offer['short_description'] ?? null,
                    $offer['short_description_ar'] ?? null
                ),
                'full_description' => $this->localeMap(
                    $offer['full_description'] ?? null,
                    $offer['full_description_ar'] ?? null
                ),
                'phone' => $offer['phone'] ?: null,
                'price' => is_numeric($offer['price'] ?? '') ? (float) $offer['price'] : null,
                'old_price' => is_numeric($offer['old_price'] ?? '') ? (float) $offer['old_price'] : null,
                'media' => $offerSlug !== '' ? ($mediaByOfferSlug[$offerSlug] ?? []) : [],
            ] + $this->identity($offer) + $this->timestamps($offer);

            if ($branchKey !== '') {
                $offersByBranch[$branchKey][] = $row;
            } else {
                $offersByFacility[$facilityKey][] = $row;
            }
        }

        $facilities = [];
        foreach ($facilityRows as $row) {
            $nameEn = $row['name'] ?? '';
            $nameAr = $row['name_ar'] ?? '';
            $slug = $row['slug'] ?: \Illuminate\Support\Str::slug($nameEn);

            // The rows underneath find their facility by slug when the sheet
            // carries one on both sides — two facilities can share an English
            // name, and a site export always writes the slug — and fall back to
            // the name a hand-typed sheet ties them together with.
            $slugKey = mb_strtolower(trim((string) ($row['slug'] ?? '')));
            $nameKey = mb_strtolower(trim($nameEn));
            $facilityKey = isset($branchesByFacility[$slugKey])
                || isset($managersByFacility[$slugKey])
                ? $slugKey
                : $nameKey;

            $facility = [
                'slug' => $slug,
                'name' => [
                    'en' => $nameEn ?: null,
                    'ar' => $nameAr ?: null,
                ],
                'facility_type' => $this->nameRef($row['facility_type'] ?? null),
                'branches' => $branchesByFacility[$facilityKey] ?? [],
                // Present only when the workbook actually carries an Images
                // sheet — an empty array either way, so a data-only workbook
                // never asks the importer to clear a picture it never named.
                'media' => $mediaByFacility[$slugKey] ?? ($mediaByFacility[$nameKey] ?? []),
            ];

            // A sheet written without these columns is asking for the rest of
            // the row to be merged, not for every facility's sales rep and
            // discount to be wiped — so the keys travel only when the sheet
            // actually has them. An empty cell in a column that IS there still
            // means "clear it".
            if (in_array('sales', $facilityColumns, true)) {
                $facility['sales'] = $this->salesRef($row['sales'] ?? null);
            }
            if (in_array('discount_percent', $facilityColumns, true)) {
                $facility['discount_percent'] = $this->percent($row['discount_percent'] ?? null);
            }

            // The columns a site export adds, each under the same rule: present
            // in the sheet, present in the payload — and nowhere else, so a
            // template that never mentions a facility's description cannot
            // erase the one the other site wrote.
            if (in_array('id', $facilityColumns, true) && ctype_digit((string) ($row['id'] ?? ''))) {
                $facility['id'] = (int) $row['id'];
            }
            foreach ([
                'description' => ['description', 'description_ar'],
                'meta_title' => ['meta_title', 'meta_title_ar'],
                'meta_description' => ['meta_description', 'meta_description_ar'],
                'meta_keywords' => ['meta_keywords', 'meta_keywords_ar'],
            ] as $field => [$en, $ar]) {
                if (in_array($en, $facilityColumns, true) || in_array($ar, $facilityColumns, true)) {
                    $facility[$field] = $this->localeMap($row[$en] ?? null, $row[$ar] ?? null);
                }
            }
            foreach (['governorate', 'city'] as $place) {
                if (in_array($place, $facilityColumns, true)) {
                    $facility[$place] = $this->nameRef($row[$place] ?? null);
                }
            }
            foreach (['latitude', 'longitude'] as $coordinate) {
                if (in_array($coordinate, $facilityColumns, true)) {
                    $facility[$coordinate] = is_numeric($row[$coordinate] ?? null) ? (float) $row[$coordinate] : null;
                }
            }
            if (in_array('canonical_url', $facilityColumns, true)) {
                $facility['canonical_url'] = $row['canonical_url'] ?: null;
            }
            if (in_array('tags', $facilityColumns, true)) {
                $facility['tags'] = $this->tagList($row['tags'] ?? null);
            }
            if (in_array('banner_config', $facilityColumns, true)) {
                $decoded = json_decode((string) ($row['banner_config'] ?? ''), true);
                $facility['banner_config'] = is_array($decoded) ? $decoded : null;
            }
            foreach (['created_at', 'updated_at'] as $stamp) {
                if (in_array($stamp, $facilityColumns, true) && ($row[$stamp] ?? '') !== '') {
                    $facility[$stamp] = $row[$stamp];
                }
            }

            // Same rule as the columns above: a workbook with no Managers sheet
            // is not saying "this facility has none".
            if ($hasManagerSheet) {
                $facility['managers'] = $managersByFacility[$facilityKey] ?? [];
            }

            if ($hasOfferSheet) {
                $facility['offers'] = $offersByFacility[$slugKey] ?? [];
                foreach ($facility['branches'] as $i => $branch) {
                    $branchKey = mb_strtolower(trim((string) ($branch['slug'] ?? '')));
                    $facility['branches'][$i]['offers'] = $branchKey === ''
                        ? []
                        : ($offersByBranch[$branchKey] ?? []);
                }
            }

            $facilities[] = $facility;
        }

        // A workbook the Export tab wrote says so on its Package sheet, and is
        // treated as the site package it is: the import screen then matches
        // rows by the slugs in it instead of asking somebody to tell same-named
        // branches apart by hand.
        $isSiteExport = ($package['origin'] ?? null) === FacilityMigrationExporter::ORIGIN_SITE_EXPORT;
        $mediaCount = array_sum(array_map('count', $mediaByFacility)) + array_sum(array_map('count', $mediaByOfferSlug));

        $payload = [
            'format' => 'ibusiness-medical/facility-migration',
            'format_version' => 1,
            'origin' => $isSiteExport ? FacilityMigrationExporter::ORIGIN_SITE_EXPORT : null,
            'generated_at' => $package['generated at'] ?? now()->toIso8601String(),
            'options' => [
                // True only when the workbook actually carries an Images sheet
                // naming pictures whose bytes sit next to it (a media/ folder
                // extracted from the same .zip) — otherwise no media row is
                // named anywhere in this payload, and the importing site keeps
                // the pictures it already has.
                'include_media_files' => $mediaCount > 0,
                'include_branches' => $hasBranchSheet,
                'include_managers' => $hasManagerSheet,
                'include_offers' => $hasOfferSheet,
            ],
            'source' => $isSiteExport
                ? [
                    'app_name' => $package['source site'] ?? null,
                    'app_url' => $package['source url'] ?? null,
                ]
                : [
                    'label' => 'Spreadsheet import',
                    'site_url' => config('app.url'),
                ],
            'lookups' => [
                'facility_types' => $this->getFacilityTypes(),
                'governorates' => $this->getGovernorates(),
                // The source site's cities first: a city this site does not have
                // yet can only be created under the right governorate if the
                // package says which one that is, and the rows below know only
                // about the places this site already holds.
                'cities' => array_merge($sourceLookups['cities'], $this->getCities()),
                'sales' => $this->getSales(),
                'tags' => [],
            ],
            'facilities' => $facilities,
            'counts' => [
                'facilities' => count($facilities),
                'branches' => count($branchRows),
                'managers' => count($managerRows),
                'offers' => count($offerRows),
                'media' => $mediaCount,
                'media_restorable' => $mediaCount,
            ],
        ];

        $tmpDir = sys_get_temp_dir().'/facility_import_'.uniqid('', true);
        mkdir($tmpDir, 0775, true);
        @mkdir($tmpDir.'/data', 0775, true);

        file_put_contents(
            $tmpDir.'/data/facilities.json',
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        $zipPath = $tmpDir.'/import.zip';
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFile($tmpDir.'/data/facilities.json', 'data/facilities.json');
        $zip->addFromString('manifest.json', json_encode([
            'format' => 'ibusiness-medical/facility-import',
            'format_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'counts' => $payload['counts'],
        ], JSON_PRETTY_PRINT));
        $zip->close();

        return $zipPath;
    }

    /**
     * A lookup column is a single cell of text, but the importer and the import
     * preview screen both read lookups as { slug, name: { en, ar } } — a bare
     * string blows up on both sides.
     *
     * @return array<string, mixed>|null
     */
    private function nameRef(?string $value): ?array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        // An Arabic cell must land in the ar slot or it will never match an
        // existing lookup row — and would create a duplicate named in Arabic
        // under the en key.
        $locale = preg_match('/\p{Arabic}/u', $value) ? 'ar' : 'en';

        return [
            'slug' => \Illuminate\Support\Str::slug($value) ?: null,
            'name' => [$locale => $value],
        ];
    }

    /**
     * A sales rep is a person, not a slugged lookup: the cell holds the name
     * and nothing else. It is written under both locales because the column it
     * lands in stores one translation blob, and the half left blank would match
     * nothing the next time the same name is imported.
     *
     * @return array<string, mixed>|null
     */
    private function salesRef(?string $value): ?array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return ['name' => ['en' => $value, 'ar' => $value]];
    }

    /**
     * The discount column, as a percentage. "15", "15%" and "15 %" are one
     * number; anything that is not a number at all is no discount.
     */
    private function percent(?string $value): ?float
    {
        $value = trim(str_replace(['%', ',', ' '], '', (string) $value));

        return $value === '' || ! is_numeric($value)
            ? null
            : round(min(100, max(0, (float) $value)), 2);
    }

    /**
     * A branch holds a list of phones; one cell can carry several, separated by
     * a newline, comma, semicolon, pipe or slash. Grouping spaces inside a
     * number are stripped so every entry is one dialable number.
     *
     * @return array<int, string>|null
     */
    private function phoneList(?string $value): ?array
    {
        return PhoneNumbers::split((string) $value) ?: null;
    }

    /**
     * The same, for a branch. The exporter writes each number with its kind in
     * brackets — "0663400006 (landline)" — for whoever reads the sheet; the
     * bracket is stripped here because the package itself carries plain
     * numbers and the importer types them on the way in.
     *
     * @return list<string>|null
     */
    private function branchPhoneList(?string $value): ?array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $entries = [];

        // Split on the separators the exporter and hand-typed cells use, but
        // not on anything inside the brackets.
        foreach (preg_split('/\R+|[;,|]/u', $value) ?: [] as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            // Drop the "(landline)" the export adds for readability.
            $entries[] = preg_replace('/\s*\([a-z_]+\)$/i', '', $part) ?? $part;
        }

        // split() folds Arabic digits and pulls apart anything still packed.
        return PhoneNumbers::split($entries) ?: null;
    }

    /**
     * @param  array<int, string>|null  $present  filled with the keys whose column the sheet actually has
     */
    private function parseFacilitySheet($spreadsheet, ?array &$present = null): array
    {
        $sheet = $spreadsheet->getSheetByName('Facilities') ?? $spreadsheet->getActiveSheet();

        // Everything from `id` down is written by the site export and simply
        // absent from a hand-typed sheet — which is why every one of them is
        // carried only when its column is actually there.
        return $this->extractRows($sheet, [
            'name' => ['name'],
            'name_ar' => ['name (ar)', 'name_ar', 'arabic name'],
            'slug' => ['slug'],
            'facility_type' => ['facility type', 'facility_type', 'type'],
            'sales' => ['sales', 'sales rep', 'sales_rep', 'sales person', 'salesperson', 'sales name', 'مندوب'],
            'discount_percent' => [
                'discount', 'discount %', 'discount percent', 'discount_percent',
                'discount percentage', 'خصم',
            ],
            'id' => ['id'],
            'governorate' => ['governorate'],
            'city' => ['city'],
            'latitude' => ['latitude', 'lat'],
            'longitude' => ['longitude', 'lng', 'long'],
            'description' => ['description'],
            'description_ar' => ['description (ar)', 'description_ar'],
            'meta_title' => ['meta title', 'meta_title'],
            'meta_title_ar' => ['meta title (ar)', 'meta_title_ar'],
            'meta_description' => ['meta description', 'meta_description'],
            'meta_description_ar' => ['meta description (ar)', 'meta_description_ar'],
            'meta_keywords' => ['meta keywords', 'meta_keywords'],
            'meta_keywords_ar' => ['meta keywords (ar)', 'meta_keywords_ar'],
            'canonical_url' => ['canonical url', 'canonical_url'],
            'tags' => ['tags', 'tag'],
            'banner_config' => ['banner config (json)', 'banner config', 'banner_config'],
            'created_at' => ['created at', 'created_at'],
            'updated_at' => ['updated at', 'updated_at'],
        ], $present);
    }

    private function parseManagerSheet($spreadsheet): array
    {
        $sheet = $spreadsheet->getSheetByName('Managers');
        if (! $sheet) {
            return [];
        }

        return $this->extractRows($sheet, [
            'facility_slug' => ['facility slug', 'facility_slug'],
            'facility_name' => ['facility name', 'facility'],
            'id' => ['id'],
            'name' => ['manager name', 'name', 'المسؤول'],
            'position' => ['position', 'title', 'role', 'job title', 'الوظيفة'],
            'phones' => ['phones', 'phone', 'mobile', 'telephone', 'الهاتف'],
        ]);
    }

    /**
     * The offers sheet a site export writes. A hand-typed workbook has none, and
     * then the facilities keep whatever offers the target site already holds.
     */
    private function parseOfferSheet($spreadsheet): array
    {
        $sheet = $spreadsheet->getSheetByName('Offers');
        if (! $sheet) {
            return [];
        }

        return $this->extractRows($sheet, [
            'facility_slug' => ['facility slug', 'facility_slug'],
            'branch_slug' => ['branch slug', 'branch_slug'],
            'id' => ['id'],
            'slug' => ['offer slug', 'offer_slug', 'slug'],
            'title' => ['title'],
            'title_ar' => ['title (ar)', 'title_ar'],
            'short_description' => ['short description', 'short_description'],
            'short_description_ar' => ['short description (ar)', 'short_description_ar'],
            'full_description' => ['full description', 'full_description'],
            'full_description_ar' => ['full description (ar)', 'full_description_ar'],
            'phone' => ['phone', 'phones'],
            'price' => ['price'],
            'old_price' => ['old price', 'old_price'],
            'created_at' => ['created at', 'created_at'],
            'updated_at' => ['updated at', 'updated_at'],
        ]);
    }

    /**
     * The Images sheet a site export writes when it bundles picture files — one
     * row per picture, naming which facility/offer it belongs to and where its
     * bytes sit next to this workbook. Absent entirely from a hand-typed sheet
     * or a data-only export, which is exactly the case that must leave every
     * facility's pictures untouched.
     */
    private function parseImageSheet($spreadsheet): array
    {
        $sheet = $spreadsheet->getSheetByName(FacilityMigrationWorkbook::IMAGES_SHEET);
        if (! $sheet) {
            return [];
        }

        return $this->extractRows($sheet, [
            'facility_slug' => ['facility slug', 'facility_slug'],
            'facility_name' => ['facility name', 'facility'],
            'owner' => ['belongs to', 'owner'],
            'context' => ['offer / branch', 'context'],
            'collection' => ['collection'],
            'file_name' => ['file name', 'file_name'],
            'mime_type' => ['mime type', 'mime_type'],
            'size_bytes' => ['size (bytes)', 'size_bytes'],
            'path' => ['path in archive', 'path'],
        ]);
    }

    /**
     * One Images-sheet row, as the media entry FacilityMigrationImporter reads:
     * enough to locate the file under the media/ folder extracted alongside
     * this workbook (source_relative_path — package_path with the leading
     * "media/" dropped) and to name the collection it is restored into. Null
     * when the row names no file at all, which a stray blank line can do.
     *
     * @param  array<string, string>  $image
     * @return array<string, mixed>|null
     */
    private function imageMediaRow(array $image): ?array
    {
        $fileName = trim($image['file_name'] ?? '');
        $path = trim($image['path'] ?? '');
        if ($fileName === '' || $path === '') {
            return null;
        }

        $mediaDir = FacilityMigrationExporter::MEDIA_DIR.'/';
        $relative = str_starts_with($path, $mediaDir) ? substr($path, strlen($mediaDir)) : $path;

        return [
            'collection_name' => trim($image['collection'] ?? '') ?: 'default',
            'file_name' => $fileName,
            'mime_type' => trim($image['mime_type'] ?? '') ?: null,
            'size' => is_numeric($image['size_bytes'] ?? null) ? (int) $image['size_bytes'] : null,
            'package_path' => $path,
            'source_relative_path' => $relative,
        ];
    }

    /**
     * The reference rows a site export ships alongside its sheets.
     *
     * Only the city rows carry anything the rest of the workbook cannot say:
     * the governorate each city belongs to. Without it, a branch naming a city
     * but no governorate loses that city on any site that does not already
     * have it — there is nothing to attach a new row to.
     *
     * @return array{cities: array<int, array<string, mixed>>}
     */
    private function parseLookupSheet($spreadsheet): array
    {
        $sheet = $spreadsheet->getSheetByName(FacilityMigrationWorkbook::LOOKUPS_SHEET);
        if (! $sheet) {
            return ['cities' => []];
        }

        $cities = [];
        foreach ($this->extractRows($sheet, [
            'kind' => ['kind'],
            'slug' => ['slug'],
            'name' => ['name'],
            'name_ar' => ['name (ar)', 'name_ar'],
            'governorate_slug' => ['governorate slug', 'governorate_slug'],
            'governorate' => ['governorate'],
        ]) as $row) {
            if (mb_strtolower(trim($row['kind'] ?? '')) !== 'city' || trim($row['slug'] ?? '') === '') {
                continue;
            }

            $cities[] = [
                'slug' => trim($row['slug']),
                'name' => $this->localeMap($row['name'] ?? null, $row['name_ar'] ?? null),
                'governorate_slug' => trim($row['governorate_slug'] ?? '') ?: null,
            ];
        }

        return ['cities' => $cities];
    }

    /**
     * What the workbook says about itself, off the sheet the exporter writes.
     * A hand-typed sheet has none, and stays a spreadsheet as far as the rest
     * of the import is concerned.
     *
     * @return array<string, string>
     */
    private function parsePackageSheet($spreadsheet): array
    {
        $sheet = $spreadsheet->getSheetByName(FacilityMigrationWorkbook::PACKAGE_SHEET);
        if (! $sheet) {
            return [];
        }

        $out = [];
        for ($row = 1, $last = min($sheet->getHighestDataRow(), 60); $row <= $last; $row++) {
            $label = $this->headerKey((string) $sheet->getCell("A{$row}")->getValue());
            $value = trim((string) $sheet->getCell("B{$row}")->getValue());
            if ($label !== '' && $value !== '') {
                $out[$label] = $value;
            }
        }

        return $out;
    }

    private function parseBranchSheet($spreadsheet): array
    {
        $sheet = $spreadsheet->getSheetByName('Branches');
        if (! $sheet) {
            return [];
        }

        return $this->extractRows($sheet, [
            'facility_slug' => ['facility slug', 'facility_slug'],
            'facility_name' => ['facility name', 'facility'],
            'id' => ['id'],
            'slug' => ['branch slug', 'branch_slug'],
            'name' => ['branch name', 'name'],
            'name_ar' => ['branch name (ar)', 'name_ar', 'arabic name'],
            'address' => ['address'],
            'address_ar' => ['address (ar)', 'address_ar'],
            'phone' => ['phone', 'phones'],
            'governorate' => ['governorate'],
            'city' => ['city'],
            'latitude' => ['latitude', 'lat'],
            'longitude' => ['longitude', 'lng', 'long'],
            'google_location_url' => [
                'google location url', 'google_location_url', 'google url', 'location url',
                'map url', 'google maps url', 'google map url', 'google maps link', 'map link',
                'google maps', 'google map', 'google location',
            ],
        ]);
    }

    /**
     * One column heading, reduced to the words in it. A heading and the alias
     * it is meant to match are often the same words dressed differently — the
     * template writes "Google Location URL" while the alias reads
     * "google_location_url" — and comparing the raw labels means a filled-in
     * column is read as empty. Spaces, underscores, dashes and brackets all
     * become one separator, so every dressing of the same heading lands here.
     */
    private function headerKey(string $label): string
    {
        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower(trim($label))));
    }

    /**
     * @param  array<int, string>|null  $present  filled with the keys whose column the sheet actually has
     */
    private function extractRows($sheet, array $columnAliases, ?array &$present = null): array
    {
        $present = [];

        $highestRow = $sheet->getHighestDataRow();
        $highestCol = $sheet->getHighestDataColumn();

        // Build a flat set of known header labels from the alias map
        $knownHeaders = [];
        foreach ($columnAliases as $candidates) {
            foreach ($candidates as $c) {
                $knownHeaders[$this->headerKey($c)] = true;
            }
        }

        // Find header row — match any known alias or '#'
        $headerRow = null;
        for ($r = 1; $r <= min($highestRow, 10); $r++) {
            for ($col = 'A'; $col <= $highestCol; $col++) {
                $val = trim((string) $sheet->getCell("{$col}{$r}")->getValue());
                if ($val === '#' || isset($knownHeaders[$this->headerKey($val)])) {
                    $headerRow = $r;
                    break 2;
                }
            }
        }

        if ($headerRow === null) {
            return [];
        }

        // Build column map
        $headerMap = [];
        for ($col = 'A'; $col <= $highestCol; $col++) {
            $val = trim((string) $sheet->getCell("{$col}{$headerRow}")->getValue());
            if ($val !== '') {
                $headerMap[$this->headerKey($val)] = $col;
            }
        }

        // Which of the asked-for columns this sheet actually carries: an absent
        // column and an empty cell mean different things to the caller.
        foreach ($columnAliases as $key => $candidates) {
            foreach ($candidates as $candidate) {
                if (isset($headerMap[$this->headerKey($candidate)])) {
                    $present[] = $key;
                    break;
                }
            }
        }

        $rows = [];
        for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
            $first = trim((string) $sheet->getCell("A{$r}")->getValue());
            if ($first === '' || str_starts_with(mb_strtoupper($first), 'END')) {
                continue;
            }

            $row = [];
            foreach ($columnAliases as $key => $candidates) {
                $row[$key] = '';
                foreach ($candidates as $candidate) {
                    $column = $headerMap[$this->headerKey($candidate)] ?? null;
                    if ($column !== null) {
                        $v = $sheet->getCell("{$column}{$r}")->getValue();
                        $row[$key] = trim((string) ($v ?? ''));
                        break;
                    }
                }
            }

            if (collect($row)->filter(fn ($v) => $v !== '')->isEmpty()) {
                continue;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * The sales reps this site already has, so the preview screen can offer
     * them rather than treating every name in the sheet as a new person.
     */
    private function getSales(): array
    {
        return \App\Models\Sales::all()->map(function (\App\Models\Sales $sales) {
            // The column is a plain varchar holding either a translation blob
            // or a bare name — both are unwrapped to the same locale map here.
            $name = array_filter(
                $sales->getTranslations('name'),
                fn ($value) => trim((string) $value) !== ''
            );

            if ($name === []) {
                $raw = trim((string) $sales->getRawOriginal('name'));
                $name = $raw === '' ? [] : ['en' => $raw, 'ar' => $raw];
            }

            return ['id' => $sales->id, 'name' => $name];
        })->toArray();
    }

    private function getFacilityTypes(): array
    {
        return \App\Models\FacilityType::all()->map(fn ($t) => [
            'id' => $t->id,
            'slug' => $t->slug,
            'name' => $t->getTranslations('name'),
        ])->toArray();
    }

    private function getGovernorates(): array
    {
        return \App\Models\Governorate::all()->map(fn ($g) => [
            'id' => $g->id,
            'slug' => $g->slug,
            'name' => $g->getTranslations('name'),
        ])->toArray();
    }

    private function getCities(): array
    {
        $governorateSlugs = \App\Models\Governorate::pluck('slug', 'id');

        return \App\Models\City::all()->map(fn ($c) => [
            'id' => $c->id,
            'slug' => $c->slug,
            'name' => $c->getTranslations('name'),
            'governorate_id' => $c->governorate_id,
            // The importer attaches a newly created city to the governorate
            // named here before falling back to the branch's own.
            'governorate_slug' => $governorateSlugs[$c->governorate_id] ?? null,
        ])->toArray();
    }

    /**
     * Which facility a branch, manager or offer row belongs to. The slug is the
     * answer whenever the sheet carries one — names repeat, slugs do not — and
     * the facility name is the fallback a hand-typed sheet ties rows with.
     *
     * @param  array<string, string>  $row
     */
    private function facilityKey(array $row): string
    {
        $slug = mb_strtolower(trim($row['facility_slug'] ?? ''));

        return $slug !== '' ? $slug : mb_strtolower(trim($row['facility_name'] ?? ''));
    }

    /**
     * The id and slug a site export writes for a row, so the import updates the
     * row it came from rather than guessing from its name. A hand-typed sheet
     * has neither, and nothing is added.
     *
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function identity(array $row): array
    {
        $out = [];

        if (ctype_digit((string) ($row['id'] ?? ''))) {
            $out['id'] = (int) $row['id'];
        }
        if (trim((string) ($row['slug'] ?? '')) !== '') {
            $out['slug'] = trim($row['slug']);
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, string>
     */
    private function timestamps(array $row): array
    {
        $out = [];
        foreach (['created_at', 'updated_at'] as $key) {
            if (trim((string) ($row[$key] ?? '')) !== '') {
                $out[$key] = trim($row[$key]);
            }
        }

        return $out;
    }

    /**
     * The two language columns of one translatable field, as the locale map the
     * importer reads. An empty pair stays an empty map, which is how the
     * importer is told the field has no value rather than no column.
     *
     * @return array<string, string>
     */
    private function localeMap(?string $en, ?string $ar): array
    {
        $out = [];
        $en = trim((string) $en);
        $ar = trim((string) $ar);

        if ($en !== '') {
            $out['en'] = $en;
        }
        if ($ar !== '') {
            $out['ar'] = $ar;
        }

        return $out;
    }

    /**
     * One cell of comma-separated tag names, as the rows the importer links.
     *
     * @return array<int, array<string, string>>
     */
    private function tagList(?string $value): array
    {
        return collect(preg_split('/\R+|[,;|]/u', (string) $value) ?: [])
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->unique()
            ->map(fn ($tag) => ['name' => $tag])
            ->values()
            ->all();
    }
}
