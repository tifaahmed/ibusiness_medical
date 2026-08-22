<?php

namespace App\Services\FacilityMigration;

use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityManager;
use App\Models\Offer;
use App\Models\Sales;
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
 *   manifest.json          package metadata, counts, source site
 *   data/facilities.json   the whole dataset (lookups + facilities + relations)
 *   data/media.csv         one row per image, for eyeballing / manual placement
 *   media/{media_id}/...   the image files, mirroring storage/app/public
 *   README-IMPORT.md       step-by-step restore instructions
 */
class FacilityMigrationExporter
{
    public const FORMAT = 'ibusiness-medical/facility-migration';

    public const FORMAT_VERSION = 1;

    /** Relative paths used inside the archive. */
    public const DATA_ENTRY = 'data/facilities.json';

    public const MANIFEST_ENTRY = 'manifest.json';

    public const MEDIA_DIR = 'media';

    /** @var array<int, array<string, mixed>> */
    private array $mediaManifest = [];

    private int $mediaFilesBundled = 0;

    private int $mediaFilesMissing = 0;

    /**
     * Build the package and return the absolute path of the written .zip.
     *
     * @param  array<string, mixed>  $options
     *                                         - filters: array of the same filters the list screen uses
     *                                         - include_media_files: bool (default true)
     *                                         - include_offers: bool (default true)
     *                                         - include_branches: bool (default true) — leave the branch rows out
     *                                         - include_managers: bool (default true) — leave the contact people out
     *                                         - destination: absolute path for the .zip (defaults to a temp file)
     *                                         - offset / limit: export a slice, so a big site can be handed over in
     *                                         parts instead of one enormous download
     */
    public function build(array $options = []): string
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
            'generated_at' => now()->toIso8601String(),
            'source' => [
                'app_name' => config('app.name'),
                'app_url' => config('app.url'),
                'media_disk' => config('media-library.disk_name', 'public'),
                'laravel_version' => app()->version(),
                'php_version' => PHP_VERSION,
            ],
            'options' => [
                'include_media_files' => (bool) $includeMediaFiles,
                'include_offers' => (bool) $includeOffers,
                'include_branches' => (bool) $includeBranches,
                'include_managers' => (bool) $includeManagers,
                'filters' => $filters,
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
            'media_files_bundled' => 0,
            'media_files_missing' => 0,
        ];

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
            'generated_at' => $payload['generated_at'],
            'source' => $payload['source'],
            'options' => $payload['options'],
            'counts' => $payload['counts'],
            'entries' => [
                'data' => self::DATA_ENTRY,
                'media_csv' => 'data/media.csv',
                'media_dir' => self::MEDIA_DIR.'/',
            ],
        ]));
        $zip->addFromString('data/media.csv', $this->mediaCsv());
        $zip->addFromString('README-IMPORT.md', $this->readme($payload));

        $zip->close();

        return $destination;
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

        return sprintf(
            'facility-migration-%s%s%s-%s.zip',
            $includeMediaFiles ? 'full' : 'data-only',
            $without,
            $slice,
            now()->format('Y-m-d_His')
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
     * @param  array<string, mixed>  $filters
     */
    private function baseQuery(array $filters)
    {
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
            ->when(! empty($filters['governorate_id']), fn ($q) => $q->where('governorate_id', $filters['governorate_id']))
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
            'phones' => array_values(array_filter(
                array_map(fn ($p) => trim((string) $p), (array) ($manager->phones ?? [])),
                fn ($p) => $p !== ''
            )),
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
            'phone' => $branch->phone,
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

            $out[] = $entry;

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

    private function defaultDestination(): string
    {
        $dir = storage_path('app/facility-migration');
        $this->ensureDirectory($dir);

        return $dir.'/'.uniqid('package_', true).'.zip';
    }

    private function ensureDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function readme(array $payload): string
    {
        $counts = $payload['counts'];
        $source = $payload['source']['app_url'] ?? 'unknown';
        $generated = $payload['generated_at'];
        $withFiles = $payload['options']['include_media_files'];

        $imagesSection = $withFiles
            ? <<<'MD'
The image files are already inside this archive, under `media/`, laid out as
`media/{old_media_id}/{file_name}`. The importer copies them into place for you —
you do **not** need to touch `storage/` by hand.
MD
            : <<<'MD'
This package carries **data only** — no image bytes. Put the old site's images
next to the package before importing:

1. Take the images zip from the old host (`storage/app/public/`).
2. Unzip it so you get folders named after the old media ids: `1/`, `2/`, `17/` …
3. Either drop that folder tree into a directory called `media/` inside this
   package and re-zip it, or pass its path to the importer with
   `--media=/absolute/path/to/unzipped/storage/app/public`.

`data/media.csv` lists every image: which facility it belongs to, which
collection (`logo`, `image`, `gallery`, …), and the path it had on the old host.
MD;

        return <<<MD
# Facility migration package

- Format: `{$payload['format']}` v{$payload['format_version']}
- Generated: {$generated}
- Source site: {$source}
- Facilities: {$counts['facilities']} | Branches: {$counts['branches']} | Managers: {$counts['managers']} | Offers: {$counts['offers']}
- Tags links: {$counts['tags']} | Media rows: {$counts['media']} | Image files bundled: {$counts['media_files_bundled']} (missing: {$counts['media_files_missing']})

## What is inside

| Path | What it is |
| --- | --- |
| `manifest.json` | Package metadata and counts. Read this first. |
| `data/facilities.json` | The whole dataset: lookups, facilities, branches, managers, tags, offers, media metadata. |
| `data/media.csv` | One row per image — owner, collection, filename, target path, sha256. |
| `media/{id}/{file}` | The image files themselves (mirrors `storage/app/public`). |

Every translatable column (`name`, `description`, `address`, `meta_*`, …) is
stored as a full locale map, e.g. `{"en": "...", "ar": "..."}` — nothing is
flattened to a single language.

## Images

{$imagesSection}

## How to restore on the new site

The new site must be running this same codebase (it ships the importer).

**From the admin UI** — Facilities → *Migration* → upload this .zip → pick a mode
→ Import. Best for smaller packages; large archives can trip PHP's
`upload_max_filesize` / `post_max_size`.

**From the command line** (recommended for big packages):

```bash
php artisan facility:migration-import /absolute/path/to/this-package.zip --mode=fresh
```

Modes:

- `--mode=fresh` — wipes existing facilities, branches and their images first,
  then imports everything. Use this on a brand-new site.
- `--mode=merge` — matches by slug and updates in place, inserting whatever is
  missing. Existing facilities not present in the package are left alone.

Add `--dry-run` to see exactly what would happen without writing anything.

Missing facility types, governorates, cities, sales reps and tags are created
automatically from the `lookups` block, matched first by slug and then by name
in any locale — so IDs do not need to line up between the two databases.

## Verifying afterwards

```bash
php artisan facility:migration-import /path/to/package.zip --dry-run
php artisan storage:link      # if /storage/... URLs 404
```

Media rows get **new** ids on the target site; the importer rewrites the file
paths to match. Do not expect the old `/storage/12/logo.png` URLs to survive —
the new URLs are generated fresh from the new ids.
MD;
    }
}
