<?php

namespace App\Services\FacilityMigration;

use App\Support\PhoneNumbers;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

/**
 * Writes a migration dataset as one .xlsx workbook.
 *
 * This is the shape a data-only export takes: with no image files to carry
 * there is nothing an archive would hold that a single spreadsheet cannot, and
 * a spreadsheet is a thing an operator can actually open, read and correct
 * before handing it to the Import tab.
 *
 * It is written from the very payload FacilityMigrationExporter builds for the
 * .zip, and every column here is one XlsxToMigrationZip reads back — the two
 * classes are the two halves of one format and have to be edited together.
 *
 * Sheets:
 *   Facilities  one row per facility, every locale in its own column
 *   Branches    tied to their facility by slug (name is only the readable label)
 *   Managers    the same, for the contact people
 *   Offers      tied to the facility or branch that owns them
 *   Images      only when the payload carries bundled image files — one row
 *               per picture, naming which facility/offer it belongs to and
 *               where its bytes sit in the archive alongside this workbook
 *   Package     what this file is and where it came from — read on import
 *   Export Info who exported it and exactly which filters they asked for —
 *               written for people, never read back on import
 */
class FacilityMigrationWorkbook
{
    /**
     * Marks the workbook as a site export rather than a hand-typed sheet, so
     * the import screen matches rows by the slugs in it instead of asking a
     * human to tell same-named branches apart.
     */
    public const PACKAGE_SHEET = 'Package';

    /**
     * The reference rows the sheets point at by name.
     *
     * Only one thing here cannot be worked out from the other sheets: which
     * governorate a city belongs to. A branch that names a city but no
     * governorate — and there are such rows — leaves an importing site that
     * does not have that city yet with nothing to attach a new one to, and the
     * city is dropped. The .zip has always carried this; the workbook now does
     * too.
     */
    public const LOOKUPS_SHEET = 'Lookups';

    /**
     * The report sheet: who built this file, when, and the filters that decided
     * which facilities are in it.
     *
     * Deliberately not part of the format. XlsxToMigrationZip reads sheets by
     * name and knows nothing of this one, so an operator can read it, edit it or
     * delete it and the workbook still imports exactly the same — which is the
     * whole reason the answer is a separate sheet rather than extra columns or
     * a header band on the Facilities sheet.
     */
    public const EXPORT_INFO_SHEET = 'Export Info';

    /**
     * Only present when the payload bundles image files — a data-only export
     * carries no media rows at all, so the sheet would be empty and, per the
     * rule every relation here follows, an empty sheet reads as "none exist"
     * rather than "not asked for".
     */
    public const IMAGES_SHEET = 'Images';

    /** @var array<string, array{label: string, width: int}> */
    private const FACILITY_COLUMNS = [
        'index' => ['label' => '#', 'width' => 6],
        'id' => ['label' => 'ID', 'width' => 8],
        'slug' => ['label' => 'Slug', 'width' => 28],
        'name' => ['label' => 'Name', 'width' => 30],
        'name_ar' => ['label' => 'Name (AR)', 'width' => 30],
        'facility_type' => ['label' => 'Facility Type', 'width' => 20],
        'sales' => ['label' => 'Sales', 'width' => 22],
        'discount_percent' => ['label' => 'Discount %', 'width' => 12],
        'governorate' => ['label' => 'Governorate', 'width' => 20],
        'city' => ['label' => 'City', 'width' => 20],
        'latitude' => ['label' => 'Latitude', 'width' => 14],
        'longitude' => ['label' => 'Longitude', 'width' => 14],
        'description' => ['label' => 'Description', 'width' => 40],
        'description_ar' => ['label' => 'Description (AR)', 'width' => 40],
        'meta_title' => ['label' => 'Meta Title', 'width' => 30],
        'meta_title_ar' => ['label' => 'Meta Title (AR)', 'width' => 30],
        'meta_description' => ['label' => 'Meta Description', 'width' => 40],
        'meta_description_ar' => ['label' => 'Meta Description (AR)', 'width' => 40],
        'meta_keywords' => ['label' => 'Meta Keywords', 'width' => 30],
        'meta_keywords_ar' => ['label' => 'Meta Keywords (AR)', 'width' => 30],
        'canonical_url' => ['label' => 'Canonical URL', 'width' => 34],
        'tags' => ['label' => 'Tags', 'width' => 26],
        'banner_config' => ['label' => 'Banner Config (JSON)', 'width' => 40],
        'created_at' => ['label' => 'Created At', 'width' => 22],
        'updated_at' => ['label' => 'Updated At', 'width' => 22],
    ];

    /** @var array<string, array{label: string, width: int}> */
    private const BRANCH_COLUMNS = [
        'index' => ['label' => '#', 'width' => 6],
        'facility_slug' => ['label' => 'Facility Slug', 'width' => 28],
        'facility_name' => ['label' => 'Facility Name', 'width' => 30],
        'id' => ['label' => 'ID', 'width' => 8],
        'slug' => ['label' => 'Branch Slug', 'width' => 30],
        'name' => ['label' => 'Branch Name', 'width' => 28],
        'name_ar' => ['label' => 'Branch Name (AR)', 'width' => 28],
        'address' => ['label' => 'Address', 'width' => 36],
        'address_ar' => ['label' => 'Address (AR)', 'width' => 36],
        'phone' => ['label' => 'Phone', 'width' => 24],
        'governorate' => ['label' => 'Governorate', 'width' => 20],
        'city' => ['label' => 'City', 'width' => 20],
        'latitude' => ['label' => 'Latitude', 'width' => 14],
        'longitude' => ['label' => 'Longitude', 'width' => 14],
        'google_location_url' => ['label' => 'Google Location URL', 'width' => 36],
        'created_at' => ['label' => 'Created At', 'width' => 22],
        'updated_at' => ['label' => 'Updated At', 'width' => 22],
    ];

    /** @var array<string, array{label: string, width: int}> */
    private const MANAGER_COLUMNS = [
        'index' => ['label' => '#', 'width' => 6],
        'facility_slug' => ['label' => 'Facility Slug', 'width' => 28],
        'facility_name' => ['label' => 'Facility Name', 'width' => 30],
        'id' => ['label' => 'ID', 'width' => 8],
        'name' => ['label' => 'Manager Name', 'width' => 28],
        'position' => ['label' => 'Position', 'width' => 24],
        'phones' => ['label' => 'Phones', 'width' => 30],
    ];

    /** @var array<string, array{label: string, width: int}> */
    private const LOOKUP_COLUMNS = [
        'index' => ['label' => '#', 'width' => 6],
        'kind' => ['label' => 'Kind', 'width' => 16],
        'slug' => ['label' => 'Slug', 'width' => 28],
        'name' => ['label' => 'Name', 'width' => 28],
        'name_ar' => ['label' => 'Name (AR)', 'width' => 28],
        'governorate_slug' => ['label' => 'Governorate Slug', 'width' => 28],
        'governorate' => ['label' => 'Governorate', 'width' => 24],
    ];

    /** @var array<string, array{label: string, width: int}> */
    private const OFFER_COLUMNS = [
        'index' => ['label' => '#', 'width' => 6],
        'facility_slug' => ['label' => 'Facility Slug', 'width' => 28],
        'branch_slug' => ['label' => 'Branch Slug', 'width' => 30],
        'id' => ['label' => 'ID', 'width' => 8],
        'slug' => ['label' => 'Offer Slug', 'width' => 28],
        'title' => ['label' => 'Title', 'width' => 30],
        'title_ar' => ['label' => 'Title (AR)', 'width' => 30],
        'short_description' => ['label' => 'Short Description', 'width' => 36],
        'short_description_ar' => ['label' => 'Short Description (AR)', 'width' => 36],
        'full_description' => ['label' => 'Full Description', 'width' => 44],
        'full_description_ar' => ['label' => 'Full Description (AR)', 'width' => 44],
        'phone' => ['label' => 'Phone', 'width' => 20],
        'price' => ['label' => 'Price', 'width' => 14],
        'old_price' => ['label' => 'Old Price', 'width' => 14],
        'created_at' => ['label' => 'Created At', 'width' => 22],
        'updated_at' => ['label' => 'Updated At', 'width' => 22],
    ];

    /** @var array<string, array{label: string, width: int}> */
    private const IMAGE_COLUMNS = [
        'index' => ['label' => '#', 'width' => 6],
        'facility_slug' => ['label' => 'Facility Slug', 'width' => 28],
        'facility_name' => ['label' => 'Facility Name', 'width' => 30],
        'owner' => ['label' => 'Belongs To', 'width' => 16],
        'context' => ['label' => 'Offer / Branch', 'width' => 30],
        'collection' => ['label' => 'Collection', 'width' => 16],
        'file_name' => ['label' => 'File Name', 'width' => 28],
        'mime_type' => ['label' => 'Mime Type', 'width' => 16],
        'size_bytes' => ['label' => 'Size (bytes)', 'width' => 14],
        'path' => ['label' => 'Path In Archive', 'width' => 44],
    ];

    /**
     * Write the dataset to $destination and return that path.
     *
     * @param  array<string, mixed>  $payload  as built by FacilityMigrationExporter
     */
    public function write(array $payload, string $destination): string
    {
        $spreadsheet = new Spreadsheet;

        $facilities = $payload['facilities'] ?? [];
        $options = $payload['options'] ?? [];

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Facilities');
        $this->fill($sheet, self::FACILITY_COLUMNS, $this->facilityRows($facilities), '1F2937');

        // A relation the export left out gets no sheet at all: an empty sheet
        // would read as "this site has none", and the import would take that
        // literally on a pruning merge.
        if ($options['include_branches'] ?? true) {
            $branches = $spreadsheet->createSheet();
            $branches->setTitle('Branches');
            $this->fill($branches, self::BRANCH_COLUMNS, $this->branchRows($facilities), '7C3AED');
        }

        if ($options['include_managers'] ?? true) {
            $managers = $spreadsheet->createSheet();
            $managers->setTitle('Managers');
            $this->fill($managers, self::MANAGER_COLUMNS, $this->managerRows($facilities), '0284C7');
        }

        if ($options['include_offers'] ?? true) {
            $offers = $spreadsheet->createSheet();
            $offers->setTitle('Offers');
            $this->fill($offers, self::OFFER_COLUMNS, $this->offerRows($facilities), '059669');
        }

        $imageRows = $this->imageRows($facilities);
        if ($imageRows !== []) {
            $images = $spreadsheet->createSheet();
            $images->setTitle(self::IMAGES_SHEET);
            $this->fill($images, self::IMAGE_COLUMNS, $imageRows, 'DB2777');
        }

        $lookups = $spreadsheet->createSheet();
        $lookups->setTitle(self::LOOKUPS_SHEET);
        $this->fill($lookups, self::LOOKUP_COLUMNS, $this->lookupRows($payload['lookups'] ?? []), '4F46E5');

        $this->buildPackageSheet($spreadsheet, $payload);
        $this->buildExportInfoSheet($spreadsheet, $payload);
        $spreadsheet->setActiveSheetIndex(0);

        (new XlsxWriter($spreadsheet))->save($destination);
        $spreadsheet->disconnectWorksheets();

        return $destination;
    }

    /**
     * @param  array<int, array<string, mixed>>  $facilities
     * @return array<int, array<string, mixed>>
     */
    private function facilityRows(array $facilities): array
    {
        $rows = [];
        foreach ($facilities as $facility) {
            $rows[] = [
                'id' => $facility['id'] ?? null,
                'slug' => $facility['slug'] ?? null,
                'name' => $this->locale($facility['name'] ?? [], 'en'),
                'name_ar' => $this->locale($facility['name'] ?? [], 'ar'),
                'facility_type' => $this->refLabel($facility['facility_type'] ?? null),
                'sales' => $this->refLabel($facility['sales'] ?? null),
                'discount_percent' => $facility['discount_percent'] ?? null,
                'governorate' => $this->refLabel($facility['governorate'] ?? null),
                'city' => $this->refLabel($facility['city'] ?? null),
                'latitude' => $facility['latitude'] ?? null,
                'longitude' => $facility['longitude'] ?? null,
                'description' => $this->locale($facility['description'] ?? [], 'en'),
                'description_ar' => $this->locale($facility['description'] ?? [], 'ar'),
                'meta_title' => $this->locale($facility['meta_title'] ?? [], 'en'),
                'meta_title_ar' => $this->locale($facility['meta_title'] ?? [], 'ar'),
                'meta_description' => $this->locale($facility['meta_description'] ?? [], 'en'),
                'meta_description_ar' => $this->locale($facility['meta_description'] ?? [], 'ar'),
                'meta_keywords' => $this->locale($facility['meta_keywords'] ?? [], 'en'),
                'meta_keywords_ar' => $this->locale($facility['meta_keywords'] ?? [], 'ar'),
                'canonical_url' => $facility['canonical_url'] ?? null,
                // One cell, one tag per comma — the import splits it back.
                'tags' => collect($facility['tags'] ?? [])
                    ->map(fn ($tag) => $this->tagLabel($tag))
                    ->filter()
                    ->implode(', '),
                'banner_config' => ! empty($facility['banner_config'])
                    ? json_encode($facility['banner_config'], JSON_UNESCAPED_UNICODE)
                    : null,
                'created_at' => $facility['created_at'] ?? null,
                'updated_at' => $facility['updated_at'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $facilities
     * @return array<int, array<string, mixed>>
     */
    private function branchRows(array $facilities): array
    {
        $rows = [];
        foreach ($facilities as $facility) {
            foreach ($facility['branches'] ?? [] as $branch) {
                $rows[] = [
                    'facility_slug' => $facility['slug'] ?? null,
                    'facility_name' => $this->locale($facility['name'] ?? [], 'en')
                        ?: $this->locale($facility['name'] ?? [], 'ar'),
                    'id' => $branch['id'] ?? null,
                    'slug' => $branch['slug'] ?? null,
                    'name' => $this->locale($branch['name'] ?? [], 'en'),
                    'name_ar' => $this->locale($branch['name'] ?? [], 'ar'),
                    'address' => $this->locale($branch['address'] ?? [], 'en'),
                    'address_ar' => $this->locale($branch['address'] ?? [], 'ar'),
                    'phone' => collect($branch['phone'] ?? [])->implode(', '),
                    'governorate' => $this->refLabel($branch['governorate'] ?? null),
                    'city' => $this->refLabel($branch['city'] ?? null),
                    'latitude' => $branch['latitude'] ?? null,
                    'longitude' => $branch['longitude'] ?? null,
                    'google_location_url' => $branch['google_location_url'] ?? null,
                    'created_at' => $branch['created_at'] ?? null,
                    'updated_at' => $branch['updated_at'] ?? null,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $facilities
     * @return array<int, array<string, mixed>>
     */
    private function managerRows(array $facilities): array
    {
        $rows = [];
        foreach ($facilities as $facility) {
            foreach ($facility['managers'] ?? [] as $manager) {
                $rows[] = [
                    'facility_slug' => $facility['slug'] ?? null,
                    'facility_name' => $this->locale($facility['name'] ?? [], 'en')
                        ?: $this->locale($facility['name'] ?? [], 'ar'),
                    'id' => $manager['id'] ?? null,
                    'name' => $manager['name'] ?? null,
                    'position' => $manager['position'] ?? null,
                    'phones' => collect(PhoneNumbers::numbers($manager['phones'] ?? []))->implode(', '),
                ];
            }
        }

        return $rows;
    }

    /**
     * Offers, whether they hang off the facility or one of its branches. The
     * branch slug column is what tells the two apart on the way back in.
     *
     * @param  array<int, array<string, mixed>>  $facilities
     * @return array<int, array<string, mixed>>
     */
    private function offerRows(array $facilities): array
    {
        $rows = [];
        foreach ($facilities as $facility) {
            foreach ($facility['offers'] ?? [] as $offer) {
                $rows[] = $this->offerRow($offer, $facility['slug'] ?? null, null);
            }
            foreach ($facility['branches'] ?? [] as $branch) {
                foreach ($branch['offers'] ?? [] as $offer) {
                    $rows[] = $this->offerRow($offer, $facility['slug'] ?? null, $branch['slug'] ?? null);
                }
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>
     */
    private function offerRow(array $offer, ?string $facilitySlug, ?string $branchSlug): array
    {
        return [
            'facility_slug' => $facilitySlug,
            'branch_slug' => $branchSlug,
            'id' => $offer['id'] ?? null,
            'slug' => $offer['slug'] ?? null,
            'title' => $this->locale($offer['title'] ?? [], 'en'),
            'title_ar' => $this->locale($offer['title'] ?? [], 'ar'),
            'short_description' => $this->locale($offer['short_description'] ?? [], 'en'),
            'short_description_ar' => $this->locale($offer['short_description'] ?? [], 'ar'),
            'full_description' => $this->locale($offer['full_description'] ?? [], 'en'),
            'full_description_ar' => $this->locale($offer['full_description'] ?? [], 'ar'),
            'phone' => $offer['phone'] ?? null,
            'price' => $offer['price'] ?? null,
            'old_price' => $offer['old_price'] ?? null,
            'created_at' => $offer['created_at'] ?? null,
            'updated_at' => $offer['updated_at'] ?? null,
        ];
    }

    /**
     * Every image the payload bundles, wherever it hangs — the facility
     * itself, one of its own offers, or an offer belonging to a branch — as
     * one flat list. This is what turns a folder of image files into
     * something a person can actually find their way around: which facility
     * a picture belongs to, and exactly where its bytes sit in the archive.
     *
     * @param  array<int, array<string, mixed>>  $facilities
     * @return array<int, array<string, mixed>>
     */
    private function imageRows(array $facilities): array
    {
        $rows = [];
        foreach ($facilities as $facility) {
            $facilitySlug = $facility['slug'] ?? null;
            $facilityName = $this->locale($facility['name'] ?? [], 'en')
                ?: $this->locale($facility['name'] ?? [], 'ar');

            foreach ($facility['media'] ?? [] as $media) {
                $rows[] = $this->imageRow($media, $facilitySlug, $facilityName, 'Facility', null);
            }

            foreach ($facility['offers'] ?? [] as $offer) {
                foreach ($offer['media'] ?? [] as $media) {
                    $rows[] = $this->imageRow($media, $facilitySlug, $facilityName, 'Offer', $offer['slug'] ?? null);
                }
            }

            foreach ($facility['branches'] ?? [] as $branch) {
                foreach ($branch['offers'] ?? [] as $offer) {
                    foreach ($offer['media'] ?? [] as $media) {
                        $rows[] = $this->imageRow(
                            $media,
                            $facilitySlug,
                            $facilityName,
                            'Branch Offer',
                            trim(($branch['slug'] ?? '').' / '.($offer['slug'] ?? ''), ' /')
                        );
                    }
                }
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $media
     * @return array<string, mixed>
     */
    private function imageRow(array $media, ?string $facilitySlug, ?string $facilityName, string $owner, ?string $context): array
    {
        return [
            'facility_slug' => $facilitySlug,
            'facility_name' => $facilityName,
            'owner' => $owner,
            'context' => $context,
            'collection' => $media['collection_name'] ?? null,
            'file_name' => $media['file_name'] ?? null,
            'mime_type' => $media['mime_type'] ?? null,
            'size_bytes' => $media['size'] ?? null,
            'path' => $media['package_path'] ?? null,
        ];
    }

    /**
     * The governorates and cities the sheets name, and — the part that cannot
     * be guessed anywhere else — which governorate each city sits in.
     *
     * @param  array<string, mixed>  $lookups
     * @return array<int, array<string, mixed>>
     */
    private function lookupRows(array $lookups): array
    {
        $governorates = collect($lookups['governorates'] ?? [])->keyBy('slug');

        $rows = [];
        foreach ($lookups['governorates'] ?? [] as $governorate) {
            $rows[] = [
                'kind' => 'governorate',
                'slug' => $governorate['slug'] ?? null,
                'name' => $this->locale($governorate['name'] ?? [], 'en'),
                'name_ar' => $this->locale($governorate['name'] ?? [], 'ar'),
            ];
        }

        foreach ($lookups['cities'] ?? [] as $city) {
            $parent = $governorates[$city['governorate_slug'] ?? ''] ?? null;
            $rows[] = [
                'kind' => 'city',
                'slug' => $city['slug'] ?? null,
                'name' => $this->locale($city['name'] ?? [], 'en'),
                'name_ar' => $this->locale($city['name'] ?? [], 'ar'),
                'governorate_slug' => $city['governorate_slug'] ?? null,
                'governorate' => $parent ? $this->refLabel($parent) : null,
            ];
        }

        return $rows;
    }

    /**
     * What this file is, in a sheet rather than a sidecar: an .xlsx travels as
     * one file, so anything the import needs to know about it has to be inside.
     *
     * @param  array<string, mixed>  $payload
     */
    private function buildPackageSheet(Spreadsheet $spreadsheet, array $payload): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(self::PACKAGE_SHEET);

        $counts = $payload['counts'] ?? [];
        $options = $payload['options'] ?? [];
        $source = $payload['source'] ?? [];

        $pairs = [
            ['Format', $payload['format'] ?? ''],
            ['Format version', (string) ($payload['format_version'] ?? '')],
            ['Origin', $payload['origin'] ?? ''],
            ['Generated at', $payload['generated_at'] ?? ''],
            ['Source site', $source['app_name'] ?? ''],
            ['Source URL', $source['app_url'] ?? ''],
            ['Facilities', (string) ($counts['facilities'] ?? 0)],
            ['Branches', (string) ($counts['branches'] ?? 0)],
            ['Managers', (string) ($counts['managers'] ?? 0)],
            ['Offers', (string) ($counts['offers'] ?? 0)],
            ['Includes images', ($options['include_media_files'] ?? false) ? 'yes' : 'no'],
            ['Includes branches', ($options['include_branches'] ?? true) ? 'yes' : 'no'],
            ['Includes managers', ($options['include_managers'] ?? true) ? 'yes' : 'no'],
            ['Includes offers', ($options['include_offers'] ?? true) ? 'yes' : 'no'],
        ];

        $sheet->setCellValue('A1', 'MIGRATION PACKAGE');
        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F2937']],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $row = 3;
        foreach ($pairs as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValueExplicit("B{$row}", (string) $value, DataType::TYPE_STRING);
            $row++;
        }
        $sheet->getStyle('A3:A'.($row - 1))->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(24);
        $sheet->getColumnDimension('B')->setWidth(52);

        $note = $row + 1;
        $sheet->setCellValue("A{$note}", 'Do not rename or delete this sheet.');
        $sheet->setCellValue('A'.($note + 1), 'The Import tab reads it to know this file came from a site export, '
            .'which is what lets it match rows by their slug instead of by name.');
        $sheet->getStyle("A{$note}:A".($note + 1))->getFont()->setItalic(true);
    }

    /**
     * The human's sheet: who exported this and what they asked for.
     *
     * The Package sheet above is machine-facing and stays that way; this one
     * answers the questions a person has when a file turns up — who made it,
     * when, from which site, and which facilities it was narrowed to. Nothing
     * reads it back, so it can say things in full sentences.
     *
     * @param  array<string, mixed>  $payload
     */
    private function buildExportInfoSheet(Spreadsheet $spreadsheet, array $payload): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(self::EXPORT_INFO_SHEET);

        $counts = $payload['counts'] ?? [];
        $options = $payload['options'] ?? [];
        $source = $payload['source'] ?? [];
        $author = $payload['exported_by'] ?? [];
        $slice = $options['slice'] ?? null;
        $filters = $options['filters_described'] ?? [];

        $sections = [];

        $sections[] = ['Export', [
            ['Exported by', $this->trimmed($author['name'] ?? null) ?? '—'],
            ['Email', $this->trimmed($author['email'] ?? null) ?? '—'],
            ['Exported at', $payload['generated_at'] ?? ''],
            ['Source site', $source['app_name'] ?? ''],
            ['Source URL', $source['app_url'] ?? ''],
            ['File', 'Excel workbook (data only — no image files)'],
        ]];

        // The point of the sheet. An empty filter set is a statement in its own
        // right: this is the whole site, not a slice somebody forgot to label.
        $sections[] = ['Filters applied', $filters === []
            ? [['No filters', 'Every facility on the source site was exported.']]
            : array_map(fn (array $row) => [$row['label'], $row['value']], $filters)];

        $sections[] = ['What was exported', [
            ['Facilities', (string) ($counts['facilities'] ?? 0)],
            ['Branches', ($options['include_branches'] ?? true)
                ? (string) ($counts['branches'] ?? 0)
                : 'not included'],
            ['Managers', ($options['include_managers'] ?? true)
                ? (string) ($counts['managers'] ?? 0)
                : 'not included'],
            ['Offers', ($options['include_offers'] ?? true)
                ? (string) ($counts['offers'] ?? 0)
                : 'not included'],
            ['Tag links', (string) ($counts['tags'] ?? 0)],
            ['Images', 'not included — importing this file leaves the pictures on the '
                .'other site exactly as they are'],
        ]];

        if (is_array($slice)) {
            $perPart = $slice['limit'] ?? null;
            $offset = $slice['offset'] ?? 0;
            $total = $slice['total_matching'] ?? null;
            $sections[] = ['This part', [
                ['Part', $perPart ? (string) ((int) floor($offset / $perPart) + 1) : '1'],
                ['Parts in total', ($perPart && $total !== null)
                    ? (string) max(1, (int) ceil($total / $perPart))
                    : '—'],
                ['Facilities per part', $perPart !== null ? (string) $perPart : '—'],
                ['Facilities skipped before this part', (string) $offset],
                ['Facilities matching the filters', $total !== null ? (string) $total : '—'],
            ]];
        }

        $sheet->setCellValue('A1', 'EXPORT REPORT');
        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B45309']],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $row = 3;
        foreach ($sections as [$heading, $pairs]) {
            $sheet->setCellValue("A{$row}", $heading);
            $sheet->mergeCells("A{$row}:B{$row}");
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '78350F']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FCD34D']]],
            ]);
            $sheet->getRowDimension($row)->setRowHeight(20);
            $row++;

            foreach ($pairs as [$label, $value]) {
                $sheet->setCellValueExplicit("A{$row}", (string) $label, DataType::TYPE_STRING);
                $sheet->setCellValueExplicit("B{$row}", (string) $value, DataType::TYPE_STRING);
                $sheet->getStyle("A{$row}")->getFont()->setBold(true);
                $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
                    'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
                ]);
                $row++;
            }

            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(34);
        $sheet->getColumnDimension('B')->setWidth(64);

        $sheet->setCellValue("A{$row}", 'This sheet is a report for people, not part of the import.');
        $sheet->setCellValue('A'.($row + 1), 'The Import tab never reads it — editing or deleting it changes nothing '
            .'about what gets imported.');
        $sheet->getStyle("A{$row}:A".($row + 1))->getFont()->setItalic(true);
    }

    /**
     * Header row, widths and striped body — one look for every sheet.
     *
     * @param  array<string, array{label: string, width: int}>  $columns
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function fill(Worksheet $sheet, array $columns, array $rows, string $headerColour): void
    {
        $letters = [];
        $letter = 'A';
        foreach ($columns as $key => $spec) {
            $letters[$key] = $letter;
            $sheet->setCellValue($letter.'1', $spec['label']);
            $sheet->getColumnDimension($letter)->setWidth($spec['width']);
            $letter++;
        }
        $lastCol = end($letters);

        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $headerColour]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '111827']]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);
        $sheet->freezePane('A2');

        $line = 2;
        foreach ($rows as $i => $row) {
            $sheet->setCellValue($letters['index'].$line, $i + 1);
            foreach ($columns as $key => $spec) {
                if ($key === 'index') {
                    continue;
                }
                $value = $row[$key] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                // Everything but the money and map columns is written as text:
                // a slug like "0100" or a phone number must survive the trip
                // through Excel without being read as a number and reshaped.
                if (in_array($key, ['discount_percent', 'price', 'old_price', 'latitude', 'longitude', 'size_bytes'], true)
                    && is_numeric($value)) {
                    $sheet->setCellValue($letters[$key].$line, $value + 0);
                } else {
                    $sheet->setCellValueExplicit($letters[$key].$line, (string) $value, DataType::TYPE_STRING);
                }
            }

            $line++;
        }

        if ($rows !== []) {
            $lastLine = $line - 1;

            // One style call for the whole body instead of one per row. A site
            // with hundreds of facilities and thousands of images was pushing
            // this past PHP's execution time limit — PhpSpreadsheet's style
            // registry pays a fixed cost on every applyFromArray() call, so a
            // few thousand of them add up to real seconds, not milliseconds.
            $sheet->getStyle("A2:{$lastCol}{$lastLine}")->applyFromArray([
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);

            // The zebra stripe as one conditional-formatting rule over the whole
            // range rather than a fill colour set row by row.
            $stripe = new Conditional;
            $stripe->setConditionType(Conditional::CONDITION_EXPRESSION);
            $stripe->addCondition('MOD(ROW(),2)=0');
            $stripe->getStyle()->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('F9FAFB');
            $sheet->getStyle("A2:{$lastCol}{$lastLine}")->setConditionalStyles([$stripe]);

            $sheet->setAutoFilter("A1:{$lastCol}1");
        }
    }

    /**
     * @param  array<string, mixed>|null  $ref
     */
    private function refLabel(?array $ref): ?string
    {
        if (! $ref) {
            return null;
        }

        // English first because that is what the pickers on the other side are
        // labelled with, but an Arabic-only lookup must still name itself.
        $name = $ref['name'] ?? [];

        return $this->trimmed($name['en'] ?? null)
            ?? $this->trimmed($name['ar'] ?? null)
            ?? $this->trimmed($ref['slug'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $tag
     */
    private function tagLabel(array $tag): ?string
    {
        $name = $tag['name'] ?? null;
        if (is_array($name)) {
            return $this->trimmed($name['en'] ?? null) ?? $this->trimmed($name['ar'] ?? null);
        }

        return $this->trimmed($name);
    }

    /**
     * @param  array<string, mixed>|string|null  $map
     */
    private function locale($map, string $locale): ?string
    {
        return is_array($map) ? $this->trimmed($map[$locale] ?? null) : ($locale === 'en' ? $this->trimmed($map) : null);
    }

    private function trimmed(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
    }
}
