<?php

namespace App\Services\FacilityMigration;

use App\Models\City;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityType;
use App\Models\Governorate;
use App\Models\Offer;
use App\Models\Sales;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Restores a package produced by FacilityMigrationExporter.
 *
 * Nothing is matched on primary keys: facility types, governorates, cities,
 * sales reps and tags are looked up by slug then by name in any locale, and
 * created when the target database has never heard of them. That is what makes
 * the package portable between two installations with unrelated id sequences.
 */
class FacilityMigrationImporter
{
    public const MODE_FRESH = 'fresh';

    public const MODE_MERGE = 'merge';

    /** @var array<string, int> */
    private array $stats = [];

    /** @var array<int, string> */
    private array $warnings = [];

    /** @var array<string, int> */
    private array $typeCache = [];

    /** @var array<string, int> */
    private array $governorateCache = [];

    /** @var array<string, int> */
    private array $cityCache = [];

    /** @var array<string, int|null> */
    private array $salesCache = [];

    /** @var array<string, int> */
    private array $tagCache = [];

    /** @var array<string, int|null> */
    private array $userCache = [];

    /** @var array<string, array<string, mixed>> cities lookup block, keyed by slug */
    private array $cityLookup = [];

    private ?string $mediaRoot = null;

    private bool $dryRun = false;

    private bool $skipMedia = false;

    /**
     * @param  array<string, mixed>  $options
     *                                         - mode: "fresh" | "merge" (default "merge")
     *                                         - dry_run: bool — parse and report, write nothing
     *                                         - media_path: absolute path to an unzipped storage/app/public
     *                                         to pull image files from when the package has no media/ dir
     *                                         - skip_media: bool — restore rows but no image files
     * @return array<string, mixed>
     */
    public function import(string $packagePath, array $options = []): array
    {
        $session = $this->beginSession($packagePath, $options + ['stop_on_error' => true]);

        try {
            do {
                $progress = $this->processChunk($session['token'], (int) ($options['chunk_size'] ?? 25));
                if (is_callable($options['on_progress'] ?? null)) {
                    $options['on_progress']($progress);
                }
            } while (! $progress['done']);
        } finally {
            $summary = $this->summarise($session['token']);
            $this->endSession($session['token']);
        }

        return $summary;
    }

    // ---------------------------------------------------------- step-by-step
    //
    // A package holding thousands of facilities and gigabytes of images cannot
    // be restored inside one request — the browser gives up long before PHP
    // does. So the work is split: the package is unpacked once into a session
    // directory with one file per facility, and the caller then walks through
    // it a few facilities at a time, each step its own short request.

    /**
     * Unpack a package and prepare it for stepped importing.
     *
     * @param  array<string, mixed>  $options  same keys as import(), plus:
     *                                         - stop_on_error: bool — abort the whole run on the first bad facility
     *                                         instead of recording it and moving on (default false)
     * @return array<string, mixed>
     */
    public function beginSession(string $packagePath, array $options = []): array
    {
        $mode = $options['mode'] ?? self::MODE_MERGE;
        if (! in_array($mode, [self::MODE_FRESH, self::MODE_MERGE], true)) {
            throw new RuntimeException("Unknown import mode \"{$mode}\". Use \"fresh\" or \"merge\".");
        }

        $token = bin2hex(random_bytes(16));
        $dir = $this->sessionDir($token);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        try {
            [$payload, $extractedTo] = $this->readPackage($packagePath, $dir.'/package');

            // One file per facility, so a step only ever decodes what it needs.
            $facilityDir = $dir.'/facilities';
            mkdir($facilityDir, 0775, true);
            $total = 0;
            foreach ($payload['facilities'] ?? [] as $facility) {
                file_put_contents(
                    sprintf('%s/%06d.json', $facilityDir, $total),
                    json_encode($facility, JSON_UNESCAPED_UNICODE)
                );
                $total++;
            }
            file_put_contents($dir.'/lookups.json', json_encode($payload['lookups'] ?? [], JSON_UNESCAPED_UNICODE));

            $state = [
                'token' => $token,
                'mode' => $mode,
                'dry_run' => (bool) ($options['dry_run'] ?? false),
                'skip_media' => (bool) ($options['skip_media'] ?? false),
                'stop_on_error' => (bool) ($options['stop_on_error'] ?? false),
                'media_path' => $options['media_path'] ?? null,
                'extracted_to' => $extractedTo,
                'total' => $total,
                'processed' => 0,
                'wiped' => false,
                'stats' => [],
                'warnings' => [],
                'errors' => [],
                'source' => $payload['source'] ?? [],
                'generated_at' => $payload['generated_at'] ?? null,
                'counts' => $payload['counts'] ?? [],
                'started_at' => now()->toIso8601String(),
            ];
            $this->saveState($token, $state);

            unset($payload);

            return [
                'token' => $token,
                'total' => $total,
                'mode' => $mode,
                'dry_run' => $state['dry_run'],
                'source' => $state['source'],
                'generated_at' => $state['generated_at'],
                'counts' => $state['counts'],
                'has_bundled_media' => is_dir($extractedTo.'/'.FacilityMigrationExporter::MEDIA_DIR),
            ];
        } catch (\Throwable $e) {
            $this->deleteDirectory($dir);

            throw $e;
        }
    }

    /**
     * Import the next slice of facilities. Each facility is committed on its
     * own, so an interrupted run leaves the facilities already done intact and
     * the next call simply picks up where this one stopped.
     *
     * @return array<string, mixed>
     */
    public function processChunk(string $token, int $limit = 5): array
    {
        $limit = max(1, min(200, $limit));
        $state = $this->loadState($token);

        $this->dryRun = $state['dry_run'];
        $this->skipMedia = $state['skip_media'];
        $this->resetState();
        $this->stats = $state['stats'];

        $lookups = json_decode(file_get_contents($this->sessionDir($token).'/lookups.json'), true) ?: [];
        $this->cityLookup = collect($lookups['cities'] ?? [])
            ->filter(fn ($c) => ! empty($c['slug']))
            ->keyBy('slug')
            ->all();
        $this->mediaRoot = $this->resolveMediaRoot($state['extracted_to'], $state['media_path']);

        // The wipe belongs to the very first step, before anything is written.
        if ($state['mode'] === self::MODE_FRESH && ! $state['wiped']) {
            DB::transaction(fn () => $this->wipeExisting());
            $state['wiped'] = true;
        }

        $stepWarnings = [];
        $processedThisStep = 0;

        for ($i = 0; $i < $limit && $state['processed'] < $state['total']; $i++) {
            $index = $state['processed'];
            $file = sprintf('%s/facilities/%06d.json', $this->sessionDir($token), $index);
            $data = is_file($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];

            $addedMediaPaths = [];
            $before = $this->warnings;

            try {
                DB::beginTransaction();
                $this->importFacility($data, $state['mode'], $addedMediaPaths);

                if ($this->dryRun) {
                    DB::rollBack();
                    $this->cleanupFiles($addedMediaPaths);
                    $this->resetLookupCaches();
                } else {
                    DB::commit();
                }
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->cleanupFiles($addedMediaPaths);
                $this->resetLookupCaches();

                $label = $data['slug'] ?? ($data['name']['en'] ?? "#{$index}");
                $state['errors'][] = ['facility' => $label, 'message' => $e->getMessage()];
                Log::error('Facility migration: facility failed', ['facility' => $label, 'error' => $e->getMessage()]);

                if ($state['stop_on_error']) {
                    $state['stats'] = $this->stats;
                    $state['warnings'] = array_merge($state['warnings'], array_slice($this->warnings, count($before)));
                    $this->saveState($token, $state);

                    throw $e;
                }
            }

            $stepWarnings = array_merge($stepWarnings, array_slice($this->warnings, count($before)));
            $state['processed']++;
            $processedThisStep++;
        }

        $state['stats'] = $this->stats;
        // Keep the tail of the warning list bounded — a big migration can emit
        // thousands and nobody reads past the first few hundred.
        $state['warnings'] = array_slice(array_merge($state['warnings'], $stepWarnings), -500);
        $this->saveState($token, $state);

        return [
            'token' => $token,
            'mode' => $state['mode'],
            'dry_run' => $state['dry_run'],
            'processed' => $state['processed'],
            'processed_this_step' => $processedThisStep,
            'total' => $state['total'],
            'done' => $state['processed'] >= $state['total'],
            'percent' => $state['total'] > 0 ? (int) round($state['processed'] / $state['total'] * 100) : 100,
            'stats' => $state['stats'],
            'errors' => $state['errors'],
            'warnings' => $stepWarnings,
        ];
    }

    /**
     * Final tally for a session.
     *
     * @return array<string, mixed>
     */
    public function summarise(string $token): array
    {
        $state = $this->loadState($token);

        return [
            'mode' => $state['mode'],
            'dry_run' => $state['dry_run'],
            'source' => $state['source'],
            'generated_at' => $state['generated_at'],
            'processed' => $state['processed'],
            'total' => $state['total'],
            'stats' => $state['stats'],
            'warnings' => $state['warnings'],
            'errors' => $state['errors'],
        ];
    }

    /**
     * Drop the unpacked package once the run is finished or abandoned.
     */
    public function endSession(string $token): void
    {
        $dir = $this->sessionDir($token);
        if (is_dir($dir)) {
            $this->deleteDirectory($dir);
        }
    }

    /**
     * Overwrite a single facility's JSON file inside an open session.
     * Used by the preview/edit flow so the operator can fix data before importing.
     */
    public function writeFacilityFile(string $token, int $index, array $facilityData): void
    {
        $dir = $this->sessionDir($token);
        $file = sprintf('%s/facilities/%06d.json', $dir, $index);
        if (! is_file($file)) {
            throw new RuntimeException("Facility file not found: {$index}");
        }
        file_put_contents($file, json_encode($facilityData, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Remove session directories left behind by abandoned runs.
     */
    public function pruneStaleSessions(int $olderThanHours = 24): int
    {
        $root = storage_path('app/facility-migration/sessions');
        if (! is_dir($root)) {
            return 0;
        }

        $cutoff = now()->subHours($olderThanHours)->getTimestamp();
        $removed = 0;
        foreach (scandir($root) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $root.'/'.$item;
            if (is_dir($path) && filemtime($path) < $cutoff) {
                $this->deleteDirectory($path);
                $removed++;
            }
        }

        return $removed;
    }

    private function sessionDir(string $token): string
    {
        if (! preg_match('/^[a-f0-9]{32}$/', $token)) {
            throw new RuntimeException('Invalid import session token.');
        }

        return storage_path('app/facility-migration/sessions/'.$token);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadState(string $token): array
    {
        $file = $this->sessionDir($token).'/state.json';
        if (! is_file($file)) {
            throw new RuntimeException('That import session has expired or was already finished. Upload the package again.');
        }

        return json_decode(file_get_contents($file), true) ?: [];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function saveState(string $token, array $state): void
    {
        file_put_contents(
            $this->sessionDir($token).'/state.json',
            json_encode($state, JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * Read manifest + dataset without touching the database. Handy for showing
     * the operator what a package contains before they commit to importing it.
     *
     * @return array<string, mixed>
     */
    public function inspect(string $packagePath): array
    {
        [$payload, $extractedTo] = $this->readPackage($packagePath);
        $this->cleanupExtraction($extractedTo, $packagePath);

        $facilities = collect($payload['facilities'] ?? []);

        return [
            'format' => $payload['format'] ?? null,
            'format_version' => $payload['format_version'] ?? null,
            'generated_at' => $payload['generated_at'] ?? null,
            'source' => $payload['source'] ?? [],
            'options' => $payload['options'] ?? [],
            'counts' => $payload['counts'] ?? [],
            'sample' => $facilities->take(10)->map(fn ($f) => [
                'slug' => $f['slug'] ?? null,
                'name' => $f['name']['en'] ?? ($f['name']['ar'] ?? null),
                'branches' => count($f['branches'] ?? []),
                'media' => count($f['media'] ?? []),
                'offers' => count($f['offers'] ?? []),
            ])->values()->all(),
        ];
    }

    // ---------------------------------------------------------------- package

    /**
     * @return array{0: array<string, mixed>, 1: string} decoded payload + extraction dir ('' when a raw .json was given)
     */
    private function readPackage(string $packagePath, ?string $extractTo = null): array
    {
        if (! is_file($packagePath)) {
            throw new RuntimeException("Package not found: {$packagePath}");
        }

        // A bare facilities.json is accepted too — useful when images are handled
        // completely out of band.
        if (Str::endsWith(Str::lower($packagePath), '.json')) {
            return [$this->decode(file_get_contents($packagePath)), ''];
        }

        $zip = new ZipArchive;
        if ($zip->open($packagePath) !== true) {
            throw new RuntimeException("Unable to open the package archive: {$packagePath}");
        }

        $extractedTo = $extractTo ?: storage_path('app/facility-migration/import_'.uniqid('', true));
        if (! is_dir($extractedTo)) {
            mkdir($extractedTo, 0775, true);
        }
        $zip->extractTo($extractedTo);
        $zip->close();

        $dataFile = $extractedTo.'/'.FacilityMigrationExporter::DATA_ENTRY;
        if (! is_file($dataFile)) {
            $this->cleanupExtraction($extractedTo, $packagePath);
            throw new RuntimeException(
                'The archive does not look like a facility migration package — '
                .FacilityMigrationExporter::DATA_ENTRY.' is missing.'
            );
        }

        return [$this->decode(file_get_contents($dataFile)), $extractedTo];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $json): array
    {
        $payload = json_decode($json, true);
        if (! is_array($payload)) {
            throw new RuntimeException('The package dataset is not valid JSON.');
        }
        if (($payload['format'] ?? null) !== FacilityMigrationExporter::FORMAT) {
            throw new RuntimeException('Unrecognised package format: '.($payload['format'] ?? 'none').'.');
        }
        if ((int) ($payload['format_version'] ?? 0) > FacilityMigrationExporter::FORMAT_VERSION) {
            throw new RuntimeException(
                'This package was made by a newer version of the exporter (v'
                .$payload['format_version'].'). Update the site code first.'
            );
        }

        return $payload;
    }

    private function resolveMediaRoot(string $extractedTo, ?string $override): ?string
    {
        if ($override) {
            if (! is_dir($override)) {
                throw new RuntimeException("The media path does not exist: {$override}");
            }

            return rtrim($override, '/\\');
        }

        $bundled = $extractedTo !== '' ? $extractedTo.'/'.FacilityMigrationExporter::MEDIA_DIR : null;

        return ($bundled && is_dir($bundled)) ? $bundled : null;
    }

    // ------------------------------------------------------------------ write

    private function wipeExisting(): void
    {
        $facilities = Facility::with(['branches', 'offers', 'branches.offers'])->get();
        $this->stats['facilities_deleted'] = $facilities->count();

        // Deleting a model deletes its image files immediately, and a rolled-back
        // transaction cannot bring those back — so a dry run only ever counts.
        if ($this->dryRun) {
            return;
        }

        foreach ($facilities as $facility) {
            foreach ($facility->branches as $branch) {
                foreach ($branch->offers as $offer) {
                    $offer->delete();
                }
            }
            foreach ($facility->offers as $offer) {
                $offer->delete();
            }
            // Deletes the facility's media files too; branches go by FK cascade.
            $facility->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $addedMediaPaths
     */
    private function importFacility(array $data, string $mode, array &$addedMediaPaths): void
    {
        $slug = $data['slug'] ?? null;
        $existing = null;

        if ($mode === self::MODE_MERGE) {
            $existing = $this->findExistingFacility($data);
        }

        $facility = $existing ?: new Facility;
        $nulls = $this->applyTranslatables($facility, [
            'name' => $this->translatable($data['name'] ?? [], $slug ?: 'facility'),
            'description' => $this->translatableOrNull($data['description'] ?? []),
            'meta_title' => $this->translatableOrNull($data['meta_title'] ?? []),
            'meta_description' => $this->translatableOrNull($data['meta_description'] ?? []),
            'meta_keywords' => $this->translatableOrNull($data['meta_keywords'] ?? []),
        ]);
        $fill = [
            'canonical_url' => $data['canonical_url'] ?? null,
            'discount_percent' => $data['discount_percent'] ?? null,
            'facility_type_id' => $this->resolveFacilityType($data['facility_type'] ?? null),
            'sales_id' => $this->resolveSales($data['sales'] ?? null),
            'governorate_id' => $this->resolveGovernorate($data['governorate'] ?? null),
            'city_id' => $this->resolveCity($data['city'] ?? null, $data['governorate'] ?? null),
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'created_by' => $this->resolveUser($data['created_by'] ?? null),
        ];
        if (! $existing && ! empty($data['id'])) {
            // Only fresh rows take the source id; an existing row must never
            // have its primary key rewritten (child rows reference it).
            $fill['id'] = $data['id'];
        }
        $facility->forceFill($fill);
        $facility->save();

        $this->stampRow($facility, $slug, $data['created_at'] ?? null, $data['updated_at'] ?? null, $nulls);
        $this->bump($existing ? 'facilities_updated' : 'facilities_created');

        $this->syncTags($facility, $data['tags'] ?? []);
        $this->restoreMedia($facility, $data['media'] ?? [], $addedMediaPaths);
        $this->restoreOffers($facility, $data['offers'] ?? [], $addedMediaPaths);

        foreach ($data['branches'] ?? [] as $branchData) {
            $this->importBranch($facility, $branchData, $mode, $addedMediaPaths);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $addedMediaPaths
     */
    private function importBranch(Facility $facility, array $data, string $mode, array &$addedMediaPaths): void
    {
        $slug = $data['slug'] ?? null;
        $existing = null;

        if ($mode === self::MODE_MERGE) {
            $existing = ! empty($data['id']) ? FacilityBranch::where('id', $data['id'])->first() : null;
            if (! $existing) {
                $nameEn = $data['name']['en'] ?? null;
                if ($nameEn) {
                    $existing = $facility->branches()->where('name->en', $nameEn)->first();
                }
            }
            // A branch found under a different facility still belongs to this one.
            if ($existing && $existing->facility_id !== $facility->id) {
                $existing->facility_id = $facility->id;
            }
        }

        $branch = $existing ?: new FacilityBranch;
        $nulls = $this->applyTranslatables($branch, [
            'name' => $this->translatableOrNull($data['name'] ?? []),
            'address' => $this->translatableOrNull($data['address'] ?? []),
        ]);
        $fill = [
            'facility_id' => $facility->id,
            'phone' => $this->normalizePhone($data['phone'] ?? null),
            'governorate_id' => $this->resolveGovernorate($data['governorate'] ?? null),
            'city_id' => $this->resolveCity($data['city'] ?? null, $data['governorate'] ?? null),
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'created_by' => $this->resolveUser($data['created_by'] ?? null),
        ];
        if (! $existing && ! empty($data['id'])) {
            $fill['id'] = $data['id'];
        }
        $branch->forceFill($fill);
        $branch->setRelation('facility', $facility); // slug generation reads it
        $branch->save();

        $this->stampRow($branch, $slug, $data['created_at'] ?? null, $data['updated_at'] ?? null, $nulls);
        $this->bump($existing ? 'branches_updated' : 'branches_created');

        $this->restoreOffers($branch, $data['offers'] ?? [], $addedMediaPaths);
    }

    /**
     * @param  array<int, array<string, mixed>>  $offers
     * @param  array<int, string>  $addedMediaPaths
     */
    private function restoreOffers(Model $owner, array $offers, array &$addedMediaPaths): void
    {
        foreach ($offers as $data) {
            $slug = $data['slug'] ?? null;
            $existing = ! empty($data['id']) ? Offer::where('id', $data['id'])->first() : null;

            $offer = $existing ?: new Offer;
            $nulls = $this->applyTranslatables($offer, [
                'title' => $this->translatable($data['title'] ?? [], $slug ?: 'offer'),
                'short_description' => $this->translatableOrNull($data['short_description'] ?? []),
                'full_description' => $this->translatableOrNull($data['full_description'] ?? []),
            ]);
            $fill = [
                'offerable_id' => $owner->getKey(),
                'offerable_type' => $owner->getMorphClass(),
                'phone' => $data['phone'] ?? null,
                'price' => $data['price'] ?? null,
                'old_price' => $data['old_price'] ?? null,
            ];
            if (! $existing && ! empty($data['id'])) {
                $fill['id'] = $data['id'];
            }
            $offer->forceFill($fill);
            $offer->save();

            $this->stampRow($offer, $slug, $data['created_at'] ?? null, $data['updated_at'] ?? null, $nulls);
            $this->bump($existing ? 'offers_updated' : 'offers_created');

            $this->restoreMedia($offer, $data['media'] ?? [], $addedMediaPaths);
        }
    }

    /**
     * Re-attach image files. Media rows always get fresh ids on this site, so
     * the files are re-added through the media library rather than copied to a
     * hard-coded path — whatever path generator is configured here wins.
     *
     * @param  array<int, array<string, mixed>>  $mediaRows
     * @param  array<int, string>  $addedMediaPaths
     */
    private function restoreMedia(Model $model, array $mediaRows, array &$addedMediaPaths): void
    {
        if (empty($mediaRows) || $this->skipMedia || ! method_exists($model, 'addMedia')) {
            if (! empty($mediaRows) && $this->skipMedia) {
                $this->stats['media_skipped'] = ($this->stats['media_skipped'] ?? 0) + count($mediaRows);
            }

            return;
        }

        // Re-importing the same package must not stack duplicates: clear only the
        // collections this package actually carries, leaving others untouched.
        // Skipped on a dry run — clearing deletes files off disk for real.
        if (! $this->dryRun) {
            foreach (collect($mediaRows)->pluck('collection_name')->filter()->unique() as $collection) {
                $model->clearMediaCollection($collection);
            }
        }

        foreach ($mediaRows as $row) {
            $source = $this->locateMediaFile($row);
            if ($source === null) {
                $this->bump('media_files_missing');
                $this->warnings[] = sprintf(
                    'Image not found in package: %s (%s / %s)',
                    $row['file_name'] ?? '?',
                    $row['collection_name'] ?? '?',
                    $row['package_path'] ?? '?'
                );

                continue;
            }

            if ($this->dryRun) {
                $this->bump('media_restored');

                continue;
            }

            $media = $model->addMedia($source)
                ->preservingOriginal()
                ->usingName($row['name'] ?? pathinfo($row['file_name'] ?? 'image', PATHINFO_FILENAME))
                ->usingFileName($row['file_name'] ?? basename($source))
                ->withCustomProperties($this->arrayOrEmpty($row['custom_properties'] ?? []))
                ->toMediaCollection($row['collection_name'] ?? 'default', $this->targetDisk($row));

            if (isset($row['order_column'])) {
                $media->order_column = (int) $row['order_column'];
                $media->saveQuietly();
            }

            $addedMediaPaths[] = $media->getPath();
            $this->bump('media_restored');
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function locateMediaFile(array $row): ?string
    {
        if (! $this->mediaRoot) {
            return null;
        }

        $candidates = [];
        // media/{id}/{file} as written by the exporter, minus the leading "media/"
        // so an externally supplied storage/app/public root works identically.
        if (! empty($row['source_relative_path'])) {
            $candidates[] = $this->mediaRoot.'/'.$row['source_relative_path'];
        }
        if (! empty($row['id']) && ! empty($row['file_name'])) {
            $candidates[] = $this->mediaRoot.'/'.$row['id'].'/'.$row['file_name'];
        }
        if (! empty($row['file_name'])) {
            $candidates[] = $this->mediaRoot.'/'.$row['file_name'];
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function targetDisk(array $row): string
    {
        $default = config('media-library.disk_name') ?: 'public';
        $sourceDisk = $row['disk'] ?? null;

        return ($sourceDisk && config("filesystems.disks.{$sourceDisk}")) ? $sourceDisk : $default;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tags
     */
    private function syncTags(Facility $facility, array $tags): void
    {
        if (empty($tags)) {
            return;
        }

        $ids = [];
        foreach ($tags as $tag) {
            $name = trim((string) ($tag['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $key = Str::lower($name);
            if (! isset($this->tagCache[$key])) {
                $found = Tag::whereRaw('LOWER(name) = ?', [$key])->first()
                    ?: Tag::create([
                        'name' => $name,
                        'icon' => $tag['icon'] ?? null,
                        'color' => $tag['color'] ?? null,
                    ]);
                if ($found->wasRecentlyCreated) {
                    $this->bump('tags_created');
                }
                $this->tagCache[$key] = $found->id;
            }
            $ids[] = $this->tagCache[$key];
        }

        $facility->tags()->sync(array_unique($ids));
        $this->stats['tag_links'] = ($this->stats['tag_links'] ?? 0) + count(array_unique($ids));
    }

    // ------------------------------------------------------------ resolvers

    /**
     * @param  array<string, mixed>|null  $ref  Accepts: { id: N }, { slug: "..." }, or { name: { en: "...", ar: "..." } }
     */
    private function resolveFacilityType(?array $ref): ?int
    {
        if (! $ref) {
            return $this->fallbackFacilityTypeId();
        }

        // Accept a plain numeric ID directly
        if (! empty($ref['id']) && is_numeric($ref['id'])) {
            return (int) $ref['id'];
        }

        $key = $this->refKey($ref);
        if (isset($this->typeCache[$key])) {
            return $this->typeCache[$key];
        }

        $model = $this->matchBySlugOrName(FacilityType::query(), $ref)
            ?: $this->createLookup(FacilityType::class, $ref, 'facility_types_created');

        return $this->typeCache[$key] = $model->id;
    }

    /**
     * facility_type_id is NOT NULL, so a package with a missing type still needs
     * something to point at rather than blowing up mid-import.
     */
    private function fallbackFacilityTypeId(): int
    {
        $first = FacilityType::orderBy('id')->first();
        if ($first) {
            return $first->id;
        }
        $this->warnings[] = 'A facility had no type; created a placeholder "Uncategorised" type.';

        return FacilityType::create(['name' => ['en' => 'Uncategorised', 'ar' => 'غير مصنف']])->id;
    }

    /**
     * @param  array<string, mixed>|null  $ref  Accepts: { id: N }, { slug: "..." }, or { name: { en: "...", ar: "..." } }
     */
    private function resolveGovernorate(?array $ref): ?int
    {
        if (! $ref) {
            return null;
        }

        if (! empty($ref['id']) && is_numeric($ref['id'])) {
            return (int) $ref['id'];
        }

        $key = $this->refKey($ref);
        if (isset($this->governorateCache[$key])) {
            return $this->governorateCache[$key];
        }

        $model = $this->matchBySlugOrName(Governorate::query(), $ref)
            ?: $this->createLookup(Governorate::class, $ref, 'governorates_created');

        return $this->governorateCache[$key] = $model->id;
    }

    /**
     * @param  array<string, mixed>|null  $ref  Accepts: { id: N }, { slug: "..." }, or { name: { en: "...", ar: "..." } }
     * @param  array<string, mixed>|null  $governorateRef
     */
    private function resolveCity(?array $ref, ?array $governorateRef): ?int
    {
        if (! $ref) {
            return null;
        }

        if (! empty($ref['id']) && is_numeric($ref['id'])) {
            return (int) $ref['id'];
        }

        $key = $this->refKey($ref);
        if (isset($this->cityCache[$key])) {
            return $this->cityCache[$key];
        }

        $model = $this->matchBySlugOrName(City::query(), $ref);
        if (! $model) {
            // A city row needs a governorate: prefer the one recorded in the
            // package's lookups block, fall back to the parent's governorate.
            $lookupGovSlug = $this->cityLookup[$ref['slug'] ?? '']['governorate_slug'] ?? null;
            $governorateId = $lookupGovSlug
                ? $this->resolveGovernorate(['slug' => $lookupGovSlug, 'name' => []])
                : $this->resolveGovernorate($governorateRef);

            if (! $governorateId) {
                $this->warnings[] = sprintf(
                    'City "%s" skipped — no governorate to attach it to.',
                    $ref['slug'] ?? ($ref['name']['en'] ?? '?')
                );

                return $this->cityCache[$key] = null;
            }

            $model = City::create([
                'governorate_id' => $governorateId,
                'name' => $this->translatable($ref['name'] ?? [], $ref['slug'] ?? 'city'),
            ]);
            $this->forceSlug($model, $ref['slug'] ?? null);
            $this->bump('cities_created');
        }

        return $this->cityCache[$key] = $model->id;
    }

    /**
     * @param  array<string, mixed>|null  $ref  Accepts: { id: N } or { name: "..." }
     */
    private function resolveSales(?array $ref): ?int
    {
        if (! $ref) {
            return null;
        }

        if (! empty($ref['id']) && is_numeric($ref['id'])) {
            return (int) $ref['id'];
        }

        $name = trim((string) ($ref['name'] ?? ''));
        if ($name === '') {
            return null;
        }
        $key = Str::lower($name);
        if (array_key_exists($key, $this->salesCache)) {
            return $this->salesCache[$key];
        }

        $id = Sales::whereRaw('LOWER(name) = ?', [$key])->value('id');
        if (! $id) {
            // sales.name is a plain varchar, but the model declares it
            // translatable — assigning through the model would wrap the value in
            // {"en": "..."} and, for a name that is already a JSON blob, nest it
            // twice. Insert straight past the model to keep the bytes intact.
            $id = Sales::query()->insertGetId([
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->bump('sales_created');
        }

        return $this->salesCache[$key] = $id;
    }

    /**
     * @param  array<string, mixed>|null  $ref
     */
    private function resolveUser(?array $ref): ?int
    {
        $email = $ref['email'] ?? null;
        if (! $email) {
            return null;
        }
        if (array_key_exists($email, $this->userCache)) {
            return $this->userCache[$email];
        }

        return $this->userCache[$email] = User::where('email', $email)->value('id');
    }

    /**
     * Match a lookup row by slug first, then by its name in any locale.
     *
     * @param  array<string, mixed>  $ref
     */
    private function matchBySlugOrName($query, array $ref): ?Model
    {
        if (! empty($ref['slug'])) {
            $bySlug = (clone $query)->where('slug', $ref['slug'])->first();
            if ($bySlug) {
                return $bySlug;
            }
        }

        foreach (($ref['name'] ?? []) as $locale => $value) {
            $match = $this->whereTranslationEquals(clone $query, $locale, $value)?->first();
            if ($match) {
                return $match;
            }
        }

        return null;
    }

    /**
     * Case-insensitive "this translatable column equals this string in this
     * locale". Returns null when the locale/value pair is unusable.
     */
    private function whereTranslationEquals($query, $locale, $value, string $column = 'name')
    {
        $value = trim((string) $value);
        // The locale becomes part of a JSON path, which cannot be bound — so it
        // is whitelisted to the shape a locale key is actually allowed to take.
        $locale = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $locale);

        if ($value === '' || $locale === '') {
            return null;
        }

        return $query->whereRaw(
            "LOWER(JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.\"{$locale}\"'))) = ?",
            [Str::lower($value)]
        );
    }

    /**
     * @param  class-string<Model>  $class
     * @param  array<string, mixed>  $ref
     */
    private function createLookup(string $class, array $ref, string $statKey): Model
    {
        /** @var Model $model */
        $model = $class::create([
            'name' => $this->translatable($ref['name'] ?? [], $ref['slug'] ?? 'item'),
        ]);
        $this->forceSlug($model, $ref['slug'] ?? null);
        $this->bump($statKey);

        return $model;
    }

    // ------------------------------------------------------------- utilities

    /**
     * Spatie's HasTranslations turns a null into `{"en": null}` rather than a
     * NULL column, so translatable values are set here only when they carry
     * content; the columns that should be NULL are returned for stampRow() to
     * blank out with a plain query-builder update.
     *
     * @param  array<string, array<string, string>|null>  $map
     * @return array<int, string> columns that must be set to NULL
     */
    private function applyTranslatables(Model $model, array $map): array
    {
        $nulls = [];
        foreach ($map as $column => $value) {
            if ($value === null) {
                $nulls[] = $column;

                continue;
            }
            $model->setAttribute($column, $value);
        }

        return $nulls;
    }

    /**
     * Write the original slug and timestamps back over whatever the model's
     * own slug generator and timestamps produced, without firing events.
     *
     * @param  array<int, string>  $nullColumns
     */
    private function stampRow(Model $model, ?string $slug, ?string $createdAt, ?string $updatedAt, array $nullColumns = []): void
    {
        $updates = [];

        foreach ($nullColumns as $column) {
            $updates[$column] = null;
        }

        if ($slug && $model->slug !== $slug) {
            $taken = $model->newQuery()->where('slug', $slug)->where($model->getKeyName(), '!=', $model->getKey())->exists();
            if ($taken) {
                $this->warnings[] = sprintf(
                    '%s slug "%s" is already used by another row; kept the generated "%s".',
                    class_basename($model),
                    $slug,
                    $model->slug
                );
            } else {
                $updates['slug'] = $slug;
            }
        }

        $createdAt = $this->normalizeDateTime($createdAt);
        $updatedAt = $this->normalizeDateTime($updatedAt);

        if ($createdAt) {
            $updates['created_at'] = $this->normalizeTimestamp($createdAt);
        }
        if ($updatedAt) {
            $updates['updated_at'] = $this->normalizeTimestamp($updatedAt);
        }

        if ($updates === []) {
            return;
        }

        $model->newQuery()->whereKey($model->getKey())->update($updates);

        // Re-sync the in-memory model, minus the nulled translatable columns —
        // pushing null back through HasTranslations would rebuild {"en": null}.
        $model->forceFill(array_diff_key($updates, array_flip($nullColumns)))->syncOriginal();
    }

    private function normalizeTimestamp(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        // '2026-01-09T22:51:26+02:00' or '2026-01-09T22:51:26.123+02:00'
        // -> '2026-01-09 22:51:26' so strict-mode MySQL DATETIME accepts it.
        $value = preg_replace('/[T](\d{2}:\d{2}:\d{2}).*$/', ' $1', trim($value)) ?? $value;

        return $value === '' ? null : $value;
    }

    private function forceSlug(Model $model, ?string $slug): void
    {
        $this->stampRow($model, $slug, null, null);
    }

    /**
     * Package timestamps arrive as ISO-8601 strings carrying an offset
     * ("2026-01-09T22:51:26+02:00"). MySQL datetime columns accept no offset,
     * so convert to the app timezone and format for the column. The instant is
     * preserved exactly — only the representation changes.
     */
    private function normalizeDateTime(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)
                ->setTimezone(config('app.timezone', 'UTC'))
                ->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            $this->warnings[] = sprintf('Unparseable timestamp "%s" skipped.', $value);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $map
     * @return array<string, string>
     */
    private function translatable(array $map, string $fallback): array
    {
        $clean = $this->translatableOrNull($map);
        if ($clean !== null) {
            return $clean;
        }
        $label = Str::title(str_replace('-', ' ', $fallback));

        return ['en' => $label, 'ar' => $label];
    }

    /**
     * @param  mixed  $map
     * @return array<string, string>|null
     */
    private function translatableOrNull($map): ?array
    {
        if (is_string($map)) {
            $map = ['en' => $map];
        }
        if (! is_array($map)) {
            return null;
        }
        $clean = array_filter($map, fn ($v) => is_scalar($v) && trim((string) $v) !== '');

        return $clean === [] ? null : array_map(fn ($v) => (string) $v, $clean);
    }

    /**
     * @return array<int, string>|null
     */
    private function normalizePhone($raw): ?array
    {
        if (is_array($raw)) {
            $values = array_values(array_filter(array_map('trim', $raw), fn ($p) => $p !== ''));

            return $values ?: null;
        }
        if (is_string($raw) && trim($raw) !== '') {
            $parts = array_values(array_filter(array_map('trim', preg_split('/[,;|]/', $raw)), fn ($p) => $p !== ''));

            return $parts ?: null;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayOrEmpty($value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<string, mixed>  $ref
     */
    private function refKey(array $ref): string
    {
        return Str::lower(($ref['slug'] ?? '').'|'.implode('|', array_map('strval', $ref['name'] ?? [])));
    }

    private function bump(string $key): void
    {
        $this->stats[$key] = ($this->stats[$key] ?? 0) + 1;
    }

    private function resetState(): void
    {
        $this->stats = [];
        $this->warnings = [];
        $this->resetLookupCaches();
    }

    /**
     * Lookup ids resolved in a rolled-back transaction no longer exist — a
     * facility that shares a type/governorate/city created moments ago by an
     * earlier row in the same chunk would otherwise reuse a stale id and blow
     * up on the foreign key. A dry run rolls every facility back, and a failed
     * facility does too, so the caches are dropped whenever that happens.
     */
    private function resetLookupCaches(): void
    {
        $this->typeCache = [];
        $this->governorateCache = [];
        $this->cityCache = [];
        $this->salesCache = [];
        $this->tagCache = [];
        $this->userCache = [];
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function cleanupFiles(array $paths): void
    {
        foreach ($paths as $path) {
            if ($path && is_file($path)) {
                @unlink($path);
                @rmdir(dirname($path));
            }
        }
    }

    private function cleanupExtraction(string $dir, string $packagePath): void
    {
        if ($dir === '' || ! is_dir($dir) || $dir === dirname($packagePath)) {
            return;
        }
        $this->deleteDirectory($dir);
    }

    private function deleteDirectory(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$item;
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function findExistingFacility(array $data): ?Facility
    {
        if (! empty($data['id'])) {
            return Facility::where('id', $data['id'])->first();
        }

        return null;
    }
}
