<?php

namespace App\Services\FacilityMigration;

use App\Models\City;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityManager;
use App\Models\FacilityType;
use App\Models\Governorate;
use App\Models\Offer;
use App\Models\Sales;
use App\Support\PhoneNumbers;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use ZipArchive;

/**
 * Builds a portable, lossless "migration package" for facilities.
 *
 * Unlike the styled xlsx report produced by AdminFacilityExportController, this
 * package is meant to be eaten by another installation of this codebase: every
 * column is carried in its raw form (including all translation locales), the
 * related rows travel with their parent, and the actual image files are placed
 * inside the archive next to a manifest describing where each one belongs.
 *
 * Archive layout:
 *   facility-migration.xlsx  the thing an operator actually opens — the same
 *                            sheets a data-only export writes, plus an Images
 *                            sheet naming every picture below and where it sits
 *   manifest.json          package metadata, counts, source site
 *   data/facilities.json   the whole dataset (lookups + facilities + relations)
 *   data/media.csv         one row per image, for eyeballing / manual placement
 *   media/{media_id}/...   the image files, mirroring storage/app/public
 */
class FacilityMigrationExporter
{
    public const FORMAT = 'ibusiness-medical/facility-migration';

    public const FORMAT_VERSION = 1;

    /**
     * Marks a package this exporter built, as opposed to one converted from a
     * hand-written spreadsheet. Both share the format; only this one carries a
     * stable id and slug for every row, which is what lets the import screen
     * stop demanding that a human tell two same-named branches apart.
     */
    public const ORIGIN_SITE_EXPORT = 'site-export';

    /** Relative paths used inside the archive. */
    public const DATA_ENTRY = 'data/facilities.json';

    public const MANIFEST_ENTRY = 'manifest.json';

    /** The one file in the archive a human is actually meant to open. */
    public const WORKBOOK_ENTRY = 'facility-migration.xlsx';

    public const MEDIA_DIR = 'media';

    /** @var array<int, array<string, mixed>> */
    private array $mediaManifest = [];

    private int $mediaFilesBundled = 0;

    private int $mediaFilesMissing = 0;

    /**
     * Whether the bytes are travelling with this package.
     *
     * The data file only ever names an image the importer can actually put
     * back: a merge clears the collections a package names before refilling
     * them, so naming an image whose file is not in the archive would delete
     * the picture the other site already had and put nothing in its place.
     */
    private bool $bundlingMediaFiles = true;

    /**
     * The dataset every shape of this export is written from — the .zip and the
     * .xlsx alike, so the two can never describe the site differently.
     *
     * @param  array<string, mixed>  $options  the keys build() documents
     * @return array<string, mixed>
     */
    private function payload(array $options): array
    {
        $includeMediaFiles = $options['include_media_files'] ?? true;
        $includeOffers = $options['include_offers'] ?? true;
        // A package can be narrowed to the facilities themselves. What is left
        // out is not merely hidden: the relation is never loaded, so it costs
        // nothing, and the importer leaves the target site's own rows alone.
        $includeBranches = $options['include_branches'] ?? true;
        $includeManagers = $options['include_managers'] ?? true;
        $filters = $options['filters'] ?? [];
        $offset = isset($options['offset']) ? max(0, (int) $options['offset']) : null;
        $limit = isset($options['limit']) ? max(1, (int) $options['limit']) : null;

        $this->mediaManifest = [];
        $this->mediaFilesBundled = 0;
        $this->mediaFilesMissing = 0;
        $this->bundlingMediaFiles = (bool) $includeMediaFiles;

        $facilities = $this->queryFacilities(
            $filters,
            $includeOffers,
            $includeBranches,
            $includeManagers,
            $offset,
            $limit
        );

        $payload = [
            'format' => self::FORMAT,
            'format_version' => self::FORMAT_VERSION,
            'origin' => self::ORIGIN_SITE_EXPORT,
            'generated_at' => now()->toIso8601String(),
            'source' => [
                'app_name' => config('app.name'),
                'app_url' => config('app.url'),
                'media_disk' => config('media-library.disk_name', 'public'),
                'laravel_version' => app()->version(),
                'php_version' => PHP_VERSION,
            ],
            // Who pressed the button. A package that turns up months later on
            // another site is otherwise anonymous, and "which of us exported
            // this, and what did they ask for?" is the first question asked.
            'exported_by' => $this->exportedBy($options),
            'options' => [
                'include_media_files' => (bool) $includeMediaFiles,
                'include_offers' => (bool) $includeOffers,
                'include_branches' => (bool) $includeBranches,
                'include_managers' => (bool) $includeManagers,
                'filters' => $filters,
                // The same filters as sentences, with every id resolved to the
                // name it had here — ids mean nothing on the other site, and
                // nothing at all to a human reading the file next year.
                'filters_described' => $this->describeFilters($filters),
                'slice' => ($offset !== null || $limit !== null)
                    ? ['offset' => $offset ?? 0, 'limit' => $limit, 'total_matching' => $this->countMatching($filters)]
                    : null,
            ],
            'lookups' => $this->lookupPayload($facilities, $includeBranches),
            'facilities' => $facilities->map(
                fn (Facility $f) => $this->facilityPayload($f, $includeOffers, $includeBranches, $includeManagers)
            )->values()->all(),
        ];

        $payload['counts'] = [
            'facilities' => $facilities->count(),
            'branches' => $includeBranches ? $facilities->sum(fn (Facility $f) => $f->branches->count()) : 0,
            'managers' => $includeManagers ? $facilities->sum(fn (Facility $f) => $f->managers->count()) : 0,
            'tags' => $facilities->sum(fn (Facility $f) => $f->tags->count()),
            'offers' => collect($payload['facilities'])->sum(fn (array $f) => count($f['offers'] ?? [])
                + collect($f['branches'])->sum(fn (array $b) => count($b['offers'] ?? []))),
            'media' => count($this->mediaManifest),
            // How many of those the dataset asks the importer to restore. Lower
            // than `media` for a data-only package (none) or when a file has
            // gone astray on this server.
            'media_restorable' => collect($payload['facilities'])->sum(
                fn (array $f) => count($f['media'] ?? [])
                    + collect($f['offers'] ?? [])->sum(fn (array $o) => count($o['media'] ?? []))
                    + collect($f['branches'] ?? [])->sum(
                        fn (array $b) => collect($b['offers'] ?? [])->sum(fn (array $o) => count($o['media'] ?? []))
                    )
            ),
            'media_files_bundled' => 0,
            'media_files_missing' => 0,
        ];

        return $payload;
    }

    /**
     * Build the package and return the absolute path of the written .zip.
     *
     * @param  array<string, mixed>  $options
     *                                         - filters: array of the same filters the list screen uses
     *                                         - include_media_files: bool (default true)
     *                                         - include_offers: bool (default true)
     *                                         - include_branches: bool (default true) — leave the branch rows out
     *                                         - include_managers: bool (default true) — leave the contact people out
     *                                         - exported_by: ['id','name','email'] of whoever asked for it; defaults
     *                                         to the signed-in user
     *                                         - destination: absolute path for the .zip (defaults to a temp file)
     *                                         - offset / limit: export a slice, so a big site can be handed over in
     *                                         parts instead of one enormous download
     */
    public function build(array $options = []): string
    {
        $includeMediaFiles = $options['include_media_files'] ?? true;
        $payload = $this->payload($options);
        $destination = $options['destination'] ?? $this->defaultDestination();
        $this->ensureDirectory(dirname($destination));

        $zip = new ZipArchive;
        if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Unable to create the export archive at {$destination}.");
        }

        if ($includeMediaFiles) {
            $this->addMediaFiles($zip);
        }

        // Counts are only final once the files have actually been walked.
        $payload['counts']['media_files_bundled'] = $this->mediaFilesBundled;
        $payload['counts']['media_files_missing'] = $this->mediaFilesMissing;

        $zip->addFromString(self::DATA_ENTRY, $this->encode($payload));
        $zip->addFromString(self::MANIFEST_ENTRY, $this->encode([
            'format' => self::FORMAT,
            'format_version' => self::FORMAT_VERSION,
            'origin' => self::ORIGIN_SITE_EXPORT,
            'generated_at' => $payload['generated_at'],
            'source' => $payload['source'],
            'exported_by' => $payload['exported_by'],
            'options' => $payload['options'],
            'counts' => $payload['counts'],
            'entries' => [
                'data' => self::DATA_ENTRY,
                'media_csv' => 'data/media.csv',
                'media_dir' => self::MEDIA_DIR.'/',
                'workbook' => self::WORKBOOK_ENTRY,
            ],
        ]));
        $zip->addFromString('data/media.csv', $this->mediaCsv());

        // The other files above exist for the importer; this is for the person
        // who downloaded the .zip — the same sheets buildSpreadsheet() writes,
        // plus an Images sheet naming every picture this archive carries.
        $workbookTmp = $this->defaultDestination('xlsx');
        (new FacilityMigrationWorkbook)->write($payload, $workbookTmp);
        $zip->addFile($workbookTmp, self::WORKBOOK_ENTRY);

        $zip->close();
        @unlink($workbookTmp);

        return $destination;
    }

    /**
     * Build the same dataset as a single .xlsx workbook and return its path.
     *
     * This is what a data-only export hands over. It carries no images by
     * definition — so it never asks the importing site to clear a picture — and
     * every row keeps the id and slug it has here, which is what lets the
     * Import tab treat it as a site package rather than a hand-typed sheet.
     *
     * @param  array<string, mixed>  $options  the keys build() documents, minus include_media_files
     */
    public function buildSpreadsheet(array $options = []): string
    {
        $payload = $this->payload(['include_media_files' => false] + $options);

        $destination = $options['destination'] ?? $this->defaultDestination('xlsx');
        $this->ensureDirectory(dirname($destination));

        return (new FacilityMigrationWorkbook)->write($payload, $destination);
    }

    /**
     * Suggested download filename for a package built with these options.
     */
    public function filename(
        bool $includeMediaFiles = true,
        ?int $part = null,
        ?int $totalParts = null,
        bool $includeBranches = true,
        bool $includeManagers = true
    ): string {
        $slice = ($part !== null && $totalParts !== null)
            ? sprintf('-part%02d-of-%02d', $part, $totalParts)
            : '';

        // Packages pile up in the server's library; what a file holds should be
        // readable off its name rather than only out of its manifest.
        $without = ($includeBranches ? '' : '-nobranches').($includeManagers ? '' : '-nomanagers');

        // With images there are files to carry, so the package is an archive.
        // Without them there is nothing an archive would add over the workbook
        // itself, and a .xlsx is a thing the operator can open and correct.
        return sprintf(
            'facility-migration-%s%s%s-%s.%s',
            $includeMediaFiles ? 'full' : 'data-only',
            $without,
            $slice,
            now()->format('Y-m-d_His'),
            $includeMediaFiles ? 'zip' : 'xlsx'
        );
    }

    /**
     * How many facilities match these filters — used to plan how many parts a
     * stepped export needs.
     *
     * @param  array<string, mixed>  $filters
     */
    public function countMatching(array $filters = []): int
    {
        return $this->baseQuery($filters)->count();
    }

    /**
     * Who is building this package.
     *
     * Explicit when the caller says so, otherwise the signed-in admin; a
     * console run has neither and says so rather than naming nobody.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function exportedBy(array $options): array
    {
        if (isset($options['exported_by']) && is_array($options['exported_by'])) {
            return $options['exported_by'];
        }

        $user = auth()->user();

        if (! $user) {
            return ['id' => null, 'name' => 'Console (artisan)', 'email' => null];
        }

        return [
            'id' => $user->getKey(),
            'name' => (string) ($user->name ?? ''),
            'email' => (string) ($user->email ?? ''),
        ];
    }

    /**
     * The filters as label/value pairs a person can read, ids resolved to the
     * names they carry on this site.
     *
     * Empty when nothing was narrowed — the caller is the one that decides how
     * to say "everything", because the wording differs per shape of package.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array{key: string, label: string, value: string}>
     */
    public function describeFilters(array $filters): array
    {
        $rows = [];
        $add = function (string $key, string $label, ?string $value) use (&$rows) {
            if ($value !== null && trim($value) !== '') {
                $rows[] = ['key' => $key, 'label' => $label, 'value' => trim($value)];
            }
        };

        $add('search', 'Search (name or slug)', $this->text($filters['search'] ?? null));
        $add('slug', 'Facility slug', $this->text($filters['slug'] ?? null));

        if (! empty($filters['facility_ids'])) {
            $ids = (array) $filters['facility_ids'];
            $add('facility_ids', 'Hand-picked facilities', count($ids).' selected (#'.implode(', #', $ids).')');
        }

        $add('facility_type_id', 'Facility type', $this->lookupName(
            FacilityType::class, $filters['facility_type_id'] ?? null
        ));

        $add('sales_id', 'Sales rep', $this->salesName($filters['sales_id'] ?? null));

        $add('sales_presence', 'Sales assignment', match ($filters['sales_presence'] ?? '') {
            'with' => 'Only facilities that have a sales rep',
            'without' => 'Only facilities with no sales rep',
            default => null,
        });

        $add('governorate_id', 'Governorate (of a branch)', $this->lookupName(
            Governorate::class, $filters['governorate_id'] ?? null
        ));

        $add('city_id', 'City (of a branch)', $this->lookupName(
            City::class, $filters['city_id'] ?? null
        ));

        $add('branches_missing', 'Branches missing location', match ($filters['branches_missing'] ?? '') {
            'governorate' => 'Has a branch with no governorate',
            'city' => 'Has a branch with no city',
            'either' => 'Has a branch missing a governorate or a city',
            'both' => 'Has a branch missing both governorate and city',
            default => null,
        });

        $add('created_from', 'Created from', $this->text($filters['created_from'] ?? null));
        $add('created_to', 'Created to', $this->text($filters['created_to'] ?? null));

        return $rows;
    }

    /**
     * The name a translatable lookup row carries here, for the filter summary.
     *
     * @param  class-string<Model>  $model
     */
    private function lookupName(string $model, mixed $id): ?string
    {
        if ($id === null || $id === '') {
            return null;
        }

        $row = $model::find($id);

        if (! $row) {
            // The row was deleted between picking it and pressing export. The
            // id still says what was asked for, which is the point of the sheet.
            return "#{$id} (no longer on this site)";
        }

        $name = $this->text($row->getTranslation('name', 'en'))
            ?? $this->text($row->getTranslation('name', 'ar'))
            ?? "#{$id}";

        return "{$name} (#{$id})";
    }

    private function salesName(mixed $id): ?string
    {
        if ($id === null || $id === '') {
            return null;
        }

        $sales = Sales::find($id);

        return $sales ? $sales->displayName()." (#{$id})" : "#{$id} (no longer on this site)";
    }

    private function text(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return EloquentCollection<int, Facility>
     */
    private function queryFacilities(
        array $filters,
        bool $includeOffers,
        bool $includeBranches = true,
        bool $includeManagers = true,
        ?int $offset = null,
        ?int $limit = null
    ): EloquentCollection {
        $with = [
            'facilityType',
            'sales',
            'governorate',
            'city',
            'creator:id,name,email',
            'tags',
            'media',
        ];

        if ($includeBranches) {
            $with['branches'] = fn ($q) => $q->with(['governorate', 'city', 'creator:id,name,email'])->orderBy('id');
        }

        if ($includeManagers) {
            $with['managers'] = fn ($q) => $q->with('creator:id,name,email')->orderBy('id');
        }

        if ($includeOffers) {
            $with['offers'] = fn ($q) => $q->with('media')->orderBy('id');
            if ($includeBranches) {
                $with['branches.offers'] = fn ($q) => $q->with('media')->orderBy('id');
            }
        }

        return $this->baseQuery($filters)
            ->with($with)
            ->when($offset !== null, fn ($q) => $q->skip($offset))
            ->when($limit !== null, fn ($q) => $q->take($limit))
            ->get();
    }

    /**
     * The filter clauses, shared by the export query and the planning count so
     * the two can never drift apart.
     *
     * These are the facility list screen's filters, clause for clause: an
     * operator who narrows the list and then exports has to get the very rows
     * that were on screen, so place is asked of the branches here too rather
     * than of the facility's own column.
     *
     * @param  array<string, mixed>  $filters
     */
    private function baseQuery(array $filters)
    {
        $salesPresence = $filters['sales_presence'] ?? '';
        $branchesMissing = $filters['branches_missing'] ?? '';

        return Facility::query()
            ->when(! empty($filters['facility_ids']), fn ($q) => $q->whereIn('id', (array) $filters['facility_ids']))
            ->when(! empty($filters['slug']), fn ($q) => $q->where('slug', $filters['slug']))
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $needle = '%'.$filters['search'].'%';
                $q->where(function ($w) use ($needle) {
                    $w->where('name->en', 'like', $needle)
                        ->orWhere('name->ar', 'like', $needle)
                        ->orWhere('slug', 'like', $needle);
                });
            })
            ->when(! empty($filters['facility_type_id']), fn ($q) => $q->where('facility_type_id', $filters['facility_type_id']))
            ->when(! empty($filters['sales_id']), fn ($q) => $q->where('sales_id', $filters['sales_id']))
            // Who is selling and who is nobody's — the question the rep filter
            // above cannot answer, because it can only name a rep that exists.
            ->when($salesPresence === 'with', fn ($q) => $q->whereNotNull('sales_id'))
            ->when($salesPresence === 'without', fn ($q) => $q->whereNull('sales_id'))
            // Asked of the branches, as the list screen asks it: a facility is
            // in a governorate because one of its branches stands there.
            ->when(! empty($filters['governorate_id']), fn ($q) => $q->whereHas(
                'branches', fn ($bq) => $bq->where('governorate_id', $filters['governorate_id'])
            ))
            ->when(! empty($filters['city_id']), fn ($q) => $q->whereHas(
                'branches', fn ($bq) => $bq->where('city_id', $filters['city_id'])
            ))
            // The facilities holding a branch nobody can place on a map. They
            // are invisible to the two filters above, and they are exactly the
            // rows an operator exports in order to go and fix them.
            ->when($branchesMissing === 'governorate', fn ($q) => $q->whereHas(
                'branches', fn ($bq) => $bq->whereNull('governorate_id')
            ))
            ->when($branchesMissing === 'city', fn ($q) => $q->whereHas(
                'branches', fn ($bq) => $bq->whereNull('city_id')
            ))
            ->when($branchesMissing === 'either', fn ($q) => $q->whereHas(
                'branches', fn ($bq) => $bq->whereNull('governorate_id')->orWhereNull('city_id')
            ))
            ->when($branchesMissing === 'both', fn ($q) => $q->whereHas(
                'branches', fn ($bq) => $bq->whereNull('governorate_id')->whereNull('city_id')
            ))
            ->when(! empty($filters['created_from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['created_from']))
            ->when(! empty($filters['created_to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['created_to']))
            // A stable order is what makes offset/limit slicing safe across parts.
            ->orderBy('id');
    }

    /**
     * The reference rows the facilities point at. Shipped in full so the target
     * site can rebuild any type/governorate/city/tag it is missing.
     *
     * @param  EloquentCollection<int, Facility>  $facilities
     * @return array<string, mixed>
     */
    private function lookupPayload(EloquentCollection $facilities, bool $includeBranches = true): array
    {
        // Without the branches there is nothing to pluck a branch place from —
        // and touching the relation here would lazy-load what build() chose not
        // to fetch, one query per facility.
        $branchPlaces = $includeBranches
            ? $facilities->flatMap->branches
            : collect();

        $types = $facilities->pluck('facilityType')->filter()->unique('id')->values();
        $governorates = $facilities->pluck('governorate')
            ->merge($branchPlaces->pluck('governorate'))
            ->filter()->unique('id')->values();
        $cities = $facilities->pluck('city')
            ->merge($branchPlaces->pluck('city'))
            ->filter()->unique('id')->values();
        $sales = $facilities->pluck('sales')->filter()->unique('id')->values();
        $tags = $facilities->flatMap->tags->filter()->unique('id')->values();

        return [
            'facility_types' => $types->map(fn ($t) => [
                'id' => $t->id,
                'slug' => $t->slug,
                'name' => $this->translations($t, 'name'),
            ])->all(),
            'governorates' => $governorates->map(fn ($g) => [
                'id' => $g->id,
                'slug' => $g->slug,
                'name' => $this->translations($g, 'name'),
            ])->all(),
            'cities' => $cities->map(fn ($c) => [
                'id' => $c->id,
                'slug' => $c->slug,
                'name' => $this->translations($c, 'name'),
                'governorate_id' => $c->governorate_id,
                'governorate_slug' => $governorates->firstWhere('id', $c->governorate_id)?->slug
                    ?? $c->governorate?->slug,
            ])->all(),
            'sales' => $sales->map(fn (Sales $s) => $this->salesRef($s))->all(),
            'tags' => $tags->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'icon' => $t->icon,
                'color' => $t->color,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function facilityPayload(
        Facility $facility,
        bool $includeOffers,
        bool $includeBranches = true,
        bool $includeManagers = true
    ): array {
        return [
            'id' => $facility->id,
            'slug' => $facility->slug,
            'name' => $this->translations($facility, 'name'),
            'description' => $this->translations($facility, 'description'),
            'meta_title' => $this->translations($facility, 'meta_title'),
            'meta_description' => $this->translations($facility, 'meta_description'),
            'meta_keywords' => $this->translations($facility, 'meta_keywords'),
            'canonical_url' => $facility->canonical_url,
            'discount_percent' => $facility->discount_percent,
            // The facility's own place and map pin, and the home-page banner
            // block. They are columns on the facility row like any other; a
            // package that left them out handed the other site a facility
            // sitting in no governorate at all.
            'governorate' => $this->slugRef($facility->governorate),
            'city' => $this->slugRef($facility->city),
            'latitude' => $facility->latitude,
            'longitude' => $facility->longitude,
            'banner_config' => $facility->banner_config,
            'created_at' => $facility->created_at?->toIso8601String(),
            'updated_at' => $facility->updated_at?->toIso8601String(),
            'facility_type' => $this->slugRef($facility->facilityType),
            'sales' => $this->salesRef($facility->sales),
            'created_by' => $facility->creator ? [
                'id' => $facility->creator->id,
                'name' => $facility->creator->name,
                'email' => $facility->creator->email,
            ] : null,
            'tags' => $facility->tags->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'icon' => $t->icon,
                'color' => $t->color,
            ])->values()->all(),
            'media' => $this->mediaPayload($facility, 'facility', $facility->slug),
            'offers' => $includeOffers
                ? $facility->offers->map(fn (Offer $o) => $this->offerPayload($o, 'facility', $facility->slug))->values()->all()
                : [],
            'branches' => $includeBranches
                ? $facility->branches->map(
                    fn (FacilityBranch $b) => $this->branchPayload($b, $facility, $includeOffers)
                )->values()->all()
                : [],
            'managers' => $includeManagers
                ? $facility->managers->map(
                    fn (FacilityManager $m) => $this->managerPayload($m)
                )->values()->all()
                : [],
        ];
    }

    /**
     * A facility's contact people. They carry no translations and no images —
     * a name, what they do, and the numbers to reach them on.
     *
     * @return array<string, mixed>
     */
    private function managerPayload(FacilityManager $manager): array
    {
        return [
            'id' => $manager->id,
            'name' => $manager->name,
            'position' => $manager->position,
            // Typed entries, so a move between sites keeps "mobile" /
            // "WhatsApp" rather than flattening every number to a digit string.
            'phones' => PhoneNumbers::entries($manager->phones),
            'created_at' => $manager->created_at?->toIso8601String(),
            'updated_at' => $manager->updated_at?->toIso8601String(),
            'created_by' => $manager->creator ? [
                'id' => $manager->creator->id,
                'name' => $manager->creator->name,
                'email' => $manager->creator->email,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function branchPayload(FacilityBranch $branch, Facility $facility, bool $includeOffers): array
    {
        return [
            'id' => $branch->id,
            'slug' => $branch->slug,
            'name' => $this->translations($branch, 'name'),
            'address' => $this->translations($branch, 'address'),
            // Flat numbers. The package format, and the import preview built
            // on it, predate the phone types; the importer re-types what it
            // reads. A number explicitly marked WhatsApp does not survive a
            // site-to-site move — see PhoneNumbers::guessType().
            'phone' => $branch->phoneNumbers(),
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'google_location_url' => $branch->google_location_url,
            'created_at' => $branch->created_at?->toIso8601String(),
            'updated_at' => $branch->updated_at?->toIso8601String(),
            'governorate' => $this->slugRef($branch->governorate),
            'city' => $this->slugRef($branch->city),
            'created_by' => $branch->creator ? [
                'id' => $branch->creator->id,
                'name' => $branch->creator->name,
                'email' => $branch->creator->email,
            ] : null,
            'offers' => $includeOffers
                ? $branch->offers->map(fn (Offer $o) => $this->offerPayload($o, 'branch', $branch->slug))->values()->all()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function offerPayload(Offer $offer, string $ownerKind, ?string $ownerSlug): array
    {
        return [
            'id' => $offer->id,
            'slug' => $offer->slug,
            'title' => $this->translations($offer, 'title'),
            'short_description' => $this->translations($offer, 'short_description'),
            'full_description' => $this->translations($offer, 'full_description'),
            'phone' => $offer->phone,
            'price' => $offer->price,
            'old_price' => $offer->old_price,
            'created_at' => $offer->created_at?->toIso8601String(),
            'updated_at' => $offer->updated_at?->toIso8601String(),
            'media' => $this->mediaPayload($offer, $ownerKind.'-offer', $ownerSlug),
        ];
    }

    /**
     * Describe every media row on the model and remember where its bytes live so
     * addMediaFiles() can pull them into the archive afterwards.
     *
     * @return array<int, array<string, mixed>>
     */
    private function mediaPayload(Model $model, string $ownerKind, ?string $ownerSlug): array
    {
        if (! method_exists($model, 'getMedia')) {
            return [];
        }

        $out = [];
        foreach ($model->media as $media) {
            /** @var Media $media */
            $sourcePath = $this->mediaSourcePath($media);
            $exists = $sourcePath !== null && is_file($sourcePath);
            $packagePath = self::MEDIA_DIR.'/'.$media->id.'/'.$media->file_name;

            $entry = [
                'id' => $media->id,
                'uuid' => $media->uuid,
                'collection_name' => $media->collection_name,
                'name' => $media->name,
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'order_column' => $media->order_column,
                'disk' => $media->disk,
                'conversions_disk' => $media->conversions_disk,
                'manipulations' => $media->manipulations,
                'custom_properties' => $media->custom_properties,
                'generated_conversions' => $media->generated_conversions,
                'responsive_images' => $media->responsive_images,
                // Where the file sat on the OLD host, relative to the disk root.
                'source_relative_path' => $media->id.'/'.$media->file_name,
                // Where the file sits inside THIS archive.
                'package_path' => $packagePath,
                'public_url' => $exists ? $media->getUrl() : null,
                'sha256' => $exists ? hash_file('sha256', $sourcePath) : null,
                'file_available' => $exists,
            ];

            // Named in the dataset only when its bytes are in this archive.
            // data/media.csv still lists every row, so a data-only package is
            // as auditable as a full one — it simply does not ask the importer
            // to restore a file it was never given.
            if ($this->bundlingMediaFiles && $exists) {
                $out[] = $entry;
            }

            $this->mediaManifest[] = $entry + [
                'owner_kind' => $ownerKind,
                'owner_slug' => $ownerSlug,
                'model_type' => $media->model_type,
                'model_id' => $media->model_id,
                'absolute_source_path' => $sourcePath,
            ];
        }

        return $out;
    }

    /**
     * Copy the bytes of every collected media row into the archive. The whole
     * per-media directory is taken when the disk layout is the default
     * "{media_id}/{file}" one, so conversions and responsive images ride along.
     */
    private function addMediaFiles(ZipArchive $zip): void
    {
        foreach ($this->mediaManifest as $entry) {
            $source = $entry['absolute_source_path'];
            if (! $source || ! is_file($source)) {
                $this->mediaFilesMissing++;

                continue;
            }

            $dir = dirname($source);
            if (basename($dir) === (string) $entry['id']) {
                $this->addDirectory($zip, $dir, self::MEDIA_DIR.'/'.$entry['id']);
            } else {
                $zip->addFile($source, $entry['package_path']);
            }
            $this->mediaFilesBundled++;
        }
    }

    private function addDirectory(ZipArchive $zip, string $directory, string $entryPrefix): void
    {
        $items = scandir($directory) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory.DIRECTORY_SEPARATOR.$item;
            $entry = $entryPrefix.'/'.$item;
            if (is_dir($path)) {
                $zip->addEmptyDir($entry);
                $this->addDirectory($zip, $path, $entry);
            } elseif (is_file($path)) {
                $zip->addFile($path, $entry);
            }
        }
    }

    private function mediaSourcePath(Media $media): ?string
    {
        try {
            return $media->getPath();
        } catch (\Throwable) {
            return null;
        }
    }

    private function mediaCsv(): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, [
            'media_id', 'owner_kind', 'owner_slug', 'model_type', 'model_id',
            'collection', 'file_name', 'mime_type', 'size_bytes',
            'path_inside_zip', 'restore_to (relative to storage/app/public)',
            'sha256', 'file_available',
        ]);
        foreach ($this->mediaManifest as $e) {
            fputcsv($handle, [
                $e['id'], $e['owner_kind'], $e['owner_slug'], $e['model_type'], $e['model_id'],
                $e['collection_name'], $e['file_name'], $e['mime_type'], $e['size'],
                $e['package_path'], $e['source_relative_path'],
                $e['sha256'], $e['file_available'] ? 'yes' : 'MISSING',
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * All locales for a translatable attribute, empty strings dropped.
     *
     * @return array<string, string>
     */
    private function translations(Model $model, string $attribute): array
    {
        if (! method_exists($model, 'getTranslations')) {
            return [];
        }

        return array_filter(
            $model->getTranslations($attribute),
            fn ($v) => $v !== null && $v !== ''
        );
    }

    /**
     * A sales rep, written the way every other lookup in this package is: the
     * id it had here, plus the name under each locale.
     *
     * `sales.name` is a plain varchar the model declares translatable — rows
     * made through the admin hold a `{"en": …, "ar": …}` blob, older ones hold
     * the bare name. Shipping the raw column would hand the other site a JSON
     * string to match a person's name against, so it is unwrapped here.
     *
     * @return array<string, mixed>|null
     */
    private function salesRef(?Sales $sales): ?array
    {
        if (! $sales) {
            return null;
        }

        $name = $this->translations($sales, 'name');
        if ($name === []) {
            $raw = trim((string) $sales->getRawOriginal('name'));
            $name = $raw === '' ? [] : ['en' => $raw, 'ar' => $raw];
        }

        return ['id' => $sales->id, 'name' => $name];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function slugRef(?Model $model): ?array
    {
        if (! $model) {
            return null;
        }

        return [
            'id' => $model->id,
            'slug' => $model->slug ?? null,
            'name' => $this->translations($model, 'name'),
        ];
    }

    private function encode(array $payload): string
    {
        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function defaultDestination(string $extension = 'zip'): string
    {
        $dir = storage_path('app/facility-migration');
        $this->ensureDirectory($dir);

        return $dir.'/'.uniqid('package_', true).'.'.$extension;
    }

    private function ensureDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

}
