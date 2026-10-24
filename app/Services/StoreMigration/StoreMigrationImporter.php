<?php

namespace App\Services\StoreMigration;

use App\Actions\Store\MergeStoreContacts;
use App\Models\City;
use App\Models\Governorate;
use App\Models\Sales;
use App\Models\Store;
use App\Models\StoreCategory;
use App\Models\StoreGallery;
use App\Models\Tag;
use Illuminate\Http\File;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Restores a package built by {@see StoreMigrationExporter}, in steps.
 *
 * The package is unpacked ONCE into a session folder; the browser then asks for
 * a few stores at a time until none are left, so nothing times out. Each store
 * is its own transaction: one bad store is reported and the rest still go in.
 *
 * It is a MERGE, never a replace:
 *   - a store is matched by slug, then title, and updated or created;
 *   - nothing the package does not mention is removed;
 *   - in "fill" mode (default) a value the store already has is kept and only
 *     blanks are filled; "overwrite" lets a non-empty incoming value replace it;
 *   - an image or contract the store already has is kept unless told otherwise;
 *   - gallery files are added only if an identical file is not already there.
 * Re-running the same package is therefore safe.
 */
class StoreMigrationImporter
{
    /** Anything else in a package is refused, so an upload cannot smuggle a script onto disk. */
    private const ALLOWED_EXTENSIONS = ['json', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'pdf', 'mp4', 'mov', 'webm', 'avi'];

    private const MAX_UNPACKED_BYTES = 1_500_000_000;

    public function __construct(private MergeStoreContacts $contacts) {}

    public static function sessionsDirectory(): string
    {
        $dir = storage_path('app/store-migration/sessions');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    public static function isToken(string $token): bool
    {
        return (bool) preg_match('/^[a-f0-9]{40}$/', $token);
    }

    public function sessionPath(string $token): string
    {
        if (! self::isToken($token)) {
            throw new RuntimeException('Invalid import session.');
        }
        $path = self::sessionsDirectory().'/'.$token;
        if (! is_dir($path)) {
            throw new RuntimeException('This import session has expired. Upload the package again.');
        }

        return $path;
    }

    /**
     * Unpack $zipPath into a new session. Writes nothing to the database.
     *
     * @return array{token: string, total: int, manifest: array<string, mixed>}
     */
    public function begin(string $zipPath): array
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('That file is not a readable ZIP package.');
        }

        $token = bin2hex(random_bytes(20));
        $dir = self::sessionsDirectory().'/'.$token;
        mkdir($dir, 0775, true);

        try {
            $bytes = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = (string) $stat['name'];
                if (str_ends_with($name, '/')) {
                    continue;
                }
                $this->assertSafeEntry($name);
                $bytes += (int) $stat['size'];
                if ($bytes > self::MAX_UNPACKED_BYTES) {
                    throw new RuntimeException('The package is too large to unpack.');
                }

                $target = $dir.'/'.$name;
                if (! is_dir(dirname($target))) {
                    mkdir(dirname($target), 0775, true);
                }
                $in = $zip->getStream($name);
                $out = fopen($target, 'wb');
                if (! $in || ! $out) {
                    throw new RuntimeException("Could not unpack {$name}.");
                }
                stream_copy_to_stream($in, $out);
                fclose($in);
                fclose($out);
            }
        } catch (\Throwable $e) {
            $zip->close();
            $this->cancel($token);
            throw $e;
        }
        $zip->close();

        $manifest = json_decode((string) @file_get_contents($dir.'/manifest.json'), true);
        if (! is_array($manifest) || ($manifest['format'] ?? null) !== StoreMigrationExporter::FORMAT) {
            $this->cancel($token);
            throw new RuntimeException('This is not a store migration package (manifest.json is missing or wrong).');
        }
        if ((int) ($manifest['version'] ?? 0) > StoreMigrationExporter::VERSION) {
            $this->cancel($token);
            throw new RuntimeException('This package was made by a newer version of the app.');
        }

        $total = count(glob($dir.'/stores/*.json') ?: []);

        return ['token' => $token, 'total' => $total, 'manifest' => $manifest];
    }

    /**
     * What each store in the session would do. Read-only.
     *
     * @return list<array<string, mixed>>
     */
    public function preview(string $token): array
    {
        $dir = $this->sessionPath($token);
        $rows = [];

        foreach (glob($dir.'/stores/*.json') ?: [] as $i => $file) {
            $data = json_decode((string) file_get_contents($file), true) ?: [];
            $match = $this->findStore($data);

            $rows[] = [
                'index' => $i,
                'title' => $data['title']['en'] ?? $data['title']['ar'] ?? '(untitled)',
                'title_ar' => $data['title']['ar'] ?? null,
                'slug' => $data['slug'] ?? null,
                'status' => $match ? 'update' : 'new',
                'match' => $match ? ['id' => $match->id, 'slug' => $match->slug] : null,
                'branches' => count($data['branches'] ?? []),
                'managers' => count($data['managers'] ?? []),
                'gallery' => count($data['gallery'] ?? []),
                'has_logo' => isset($data['media']['logo']),
                'has_contract' => isset($data['media']['contract']),
                'categories' => collect($data['categories'] ?? [])->map(fn ($c) => $c['en'] ?? $c['ar'] ?? '')->filter()->values()->all(),
                'sales' => $data['sales']['en'] ?? $data['sales']['ar'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * Apply one store from the session.
     *
     * @param  array{mode?: string, create_categories?: bool, overwrite_media?: bool, skip_media?: bool}  $options
     * @return array<string, mixed>
     */
    public function applyStore(string $token, int $index, array $options = []): array
    {
        $dir = $this->sessionPath($token);
        $files = glob($dir.'/stores/*.json') ?: [];
        if (! isset($files[$index])) {
            throw new RuntimeException("Store #{$index} is not in this package.");
        }
        $data = json_decode((string) file_get_contents($files[$index]), true) ?: [];

        $opts = $options + ['mode' => 'fill', 'create_categories' => true, 'overwrite_media' => false, 'skip_media' => false];
        $overwrite = $opts['mode'] === 'overwrite';
        $warnings = [];
        $result = ['index' => $index, 'title' => $data['title']['en'] ?? $data['title']['ar'] ?? '', 'warnings' => []];

        try {
            DB::transaction(function () use ($data, $opts, $overwrite, $dir, &$warnings, &$result) {
                $store = $this->findStore($data);
                $result['action'] = $store ? 'updated' : 'created';

                $salesId = $this->resolveSales($data['sales'] ?? null, $warnings);

                if (! $store) {
                    $store = Store::create([
                        'title' => $this->translations($data['title'] ?? []) ?: ['en' => 'Untitled', 'ar' => 'بدون عنوان'],
                        'description' => $this->translations($data['description'] ?? []) ?: null,
                        'short_description' => $this->translations($data['short_description'] ?? []) ?: null,
                        'meta_title' => $this->translations($data['meta_title'] ?? []) ?: null,
                        'meta_description' => $this->translations($data['meta_description'] ?? []) ?: null,
                        'meta_keywords' => $this->translations($data['meta_keywords'] ?? []) ?: null,
                        'youtube_link' => $data['youtube_link'] ?? null,
                        'online_only' => (bool) ($data['online_only'] ?? false),
                        'websites' => $data['websites'] ?? [],
                        'app_store_url' => $data['app_store_url'] ?? null,
                        'google_play_url' => $data['google_play_url'] ?? null,
                        'social_links' => $data['social_links'] ?? [],
                        'coupons' => $data['coupons'] ?? [],
                        'supports_shipping' => (bool) ($data['supports_shipping'] ?? false),
                        'ships_everywhere' => (bool) ($data['ships_everywhere'] ?? true),
                        'offer_percent_from' => $data['offer_percent_from'] ?? null,
                        'offer_percent_to' => $data['offer_percent_to'] ?? null,
                        'contracted_at' => $data['contracted_at'] ?? null,
                        'sales_id' => $salesId,
                        'created_by' => Auth::id(),
                    ]);
                    // Keep the original slug (public URLs) when it is free here.
                    $wanted = (string) ($data['slug'] ?? '');
                    if ($wanted !== '' && $wanted !== $store->slug && ! Store::where('slug', $wanted)->exists()) {
                        DB::table('stores')->where('id', $store->id)->update(['slug' => $wanted]);
                        $store->slug = $wanted;
                    }
                } else {
                    $this->mergeInto($store, $data, $salesId, $overwrite);
                }

                $this->syncRelations($store, $data, (bool) $opts['create_categories'], $warnings);

                if (! $store->online_only) {
                    $result['branches'] = $this->contacts->branches($store, $this->branchRows($data['branches'] ?? [], $warnings));
                }
                $result['managers'] = $this->contacts->managers($store, $data['managers'] ?? []);

                if (! $opts['skip_media']) {
                    $result += $this->restoreMedia($store, $data, $dir, (bool) $opts['overwrite_media']);
                }

                $result['store_id'] = $store->id;
            });
        } catch (\Throwable $e) {
            Log::error('Store migration: a store failed', ['index' => $index, 'title' => $result['title'], 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $result['error'] = $e->getMessage();
        }

        $result['warnings'] = array_values(array_unique($warnings));

        return $result;
    }

    public function cancel(string $token): void
    {
        if (! self::isToken($token)) {
            return;
        }
        $dir = self::sessionsDirectory().'/'.$token;
        if (is_dir($dir)) {
            $this->removeDirectory($dir);
        }
    }

    /** Sessions older than a day are leftovers from an abandoned import. */
    public function purgeStale(int $hours = 24): int
    {
        $n = 0;
        foreach (glob(self::sessionsDirectory().'/*', GLOB_ONLYDIR) ?: [] as $dir) {
            if (filemtime($dir) < time() - $hours * 3600) {
                $this->removeDirectory($dir);
                $n++;
            }
        }

        return $n;
    }

    /* ------------------------------------------------------------------ */

    private function assertSafeEntry(string $name): void
    {
        if ($name === '' || str_contains($name, '..') || str_contains($name, '\\') || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name)) {
            throw new RuntimeException("Unsafe path in package: {$name}");
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException("File type not allowed in a package: {$name}");
        }
    }

    /** @param array<string, mixed> $data */
    private function findStore(array $data): ?Store
    {
        if (! empty($data['slug']) && ($s = Store::where('slug', $data['slug'])->first())) {
            return $s;
        }
        $wanted = collect(['en', 'ar'])->map(fn ($l) => mb_strtolower(trim((string) ($data['title'][$l] ?? ''))))->filter()->all();
        if ($wanted === []) {
            return null;
        }

        return Store::query()->get()->first(function (Store $s) use ($wanted) {
            foreach (['en', 'ar'] as $l) {
                if (in_array(mb_strtolower(trim((string) $s->getTranslation('title', $l, false))), $wanted, true)) {
                    return true;
                }
            }

            return false;
        });
    }

    /** @param array<string, mixed> $map @return array<string, string> */
    private function translations(array $map): array
    {
        return array_filter(array_map(fn ($v) => is_string($v) ? trim($v) : '', $map), fn ($v) => $v !== '');
    }

    /** @param array<string, mixed> $data */
    private function mergeInto(Store $store, array $data, ?int $salesId, bool $overwrite): void
    {
        $attrs = [];

        foreach (['title', 'description', 'short_description', 'meta_title', 'meta_description', 'meta_keywords'] as $field) {
            $incoming = $this->translations($data[$field] ?? []);
            if ($incoming === []) {
                continue;
            }
            $current = $this->translations($store->getTranslations($field));
            $merged = $overwrite ? array_merge($current, $incoming) : array_merge($incoming, $current);
            if ($merged !== $current) {
                $attrs[$field] = $merged;
            }
        }

        $fill = function (string $field, mixed $incoming) use ($store, $overwrite, &$attrs) {
            if ($incoming === null || $incoming === '') {
                return;
            }
            $current = $store->{$field};
            $empty = $current === null || $current === '';
            if (($empty || $overwrite) && (string) $current !== (string) $incoming) {
                $attrs[$field] = $incoming;
            }
        };
        foreach (['youtube_link', 'app_store_url', 'google_play_url', 'offer_percent_from', 'offer_percent_to'] as $field) {
            $fill($field, $data[$field] ?? null);
        }
        $fill('contracted_at', $data['contracted_at'] ?? null);
        if ($salesId !== null) {
            $fill('sales_id', $salesId);
        }

        // Lists only ever grow.
        $attrs['websites'] = array_values(array_unique(array_merge($store->websites ?? [], $data['websites'] ?? [])));
        $knownUrls = collect($store->social_links ?? [])->pluck('url')->all();
        $attrs['social_links'] = array_merge($store->social_links ?? [], array_values(array_filter($data['social_links'] ?? [], fn ($l) => ! in_array($l['url'] ?? null, $knownUrls, true))));
        $knownCodes = collect($store->coupons ?? [])->pluck('code')->all();
        $attrs['coupons'] = array_merge($store->coupons ?? [], array_values(array_filter($data['coupons'] ?? [], fn ($c) => ! in_array($c['code'] ?? null, $knownCodes, true))));

        if ($overwrite) {
            foreach (['online_only', 'supports_shipping', 'ships_everywhere'] as $flag) {
                if (isset($data[$flag])) {
                    $attrs[$flag] = (bool) $data[$flag];
                }
            }
        }

        $store->update($attrs);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $warnings
     */
    private function syncRelations(Store $store, array $data, bool $createCategories, array &$warnings): void
    {
        $categoryIds = [];
        foreach ($data['categories'] ?? [] as $names) {
            $cat = $this->byName(StoreCategory::all(), $names);
            if (! $cat && $createCategories && $this->translations($names)) {
                $cat = StoreCategory::create(['name' => $this->translations($names), 'created_by' => Auth::id()]);
            }
            if ($cat) {
                $categoryIds[] = $cat->id;
            } else {
                $warnings[] = 'Category "'.($names['en'] ?? $names['ar'] ?? '?').'" not found — skipped.';
            }
        }
        if ($categoryIds) {
            $store->categories()->syncWithoutDetaching($categoryIds);
        }

        $tagIds = [];
        foreach ($data['tags'] ?? [] as $names) {
            $tag = $this->byName(Tag::all(), $names);
            if ($tag) {
                $tagIds[] = $tag->id;
            } else {
                $warnings[] = 'Tag "'.($names['en'] ?? $names['ar'] ?? '?').'" not found — skipped.';
            }
        }
        if ($tagIds) {
            $store->tags()->syncWithoutDetaching($tagIds);
        }

        $govIds = [];
        foreach ($data['shipping_governorates'] ?? [] as $names) {
            $gov = $this->byName(Governorate::all(), $names);
            if ($gov) {
                $govIds[] = $gov->id;
            } else {
                $warnings[] = 'Shipping governorate "'.($names['en'] ?? $names['ar'] ?? '?').'" not found — skipped.';
            }
        }
        if ($govIds) {
            $store->shippingGovernorates()->syncWithoutDetaching($govIds);
        }
    }

    /** @param array<string, string>|null $names */
    private function resolveSales(?array $names, array &$warnings): ?int
    {
        if (! $names) {
            return null;
        }
        $wanted = collect($names)->map(fn ($v) => mb_strtolower(trim((string) $v)))->filter()->all();
        foreach (Sales::all() as $sale) {
            $have = collect([...array_values($sale->nameTranslations()), $sale->displayName()])->map(fn ($v) => mb_strtolower(trim((string) $v)));
            if ($have->intersect($wanted)->isNotEmpty()) {
                return $sale->id;
            }
        }
        $warnings[] = 'Sales rep "'.($names['en'] ?? $names['ar'] ?? '?').'" not found — left unchanged.';

        return null;
    }

    /** @param Collection<int, \Illuminate\Database\Eloquent\Model> $models @param array<string, string>|null $names */
    private function byName(Collection $models, ?array $names): mixed
    {
        $wanted = collect($names ?? [])->map(fn ($v) => mb_strtolower(trim((string) $v)))->filter()->all();
        if ($wanted === []) {
            return null;
        }

        return $models->first(function ($m) use ($wanted) {
            foreach (['en', 'ar'] as $l) {
                if (in_array(mb_strtolower(trim((string) $m->getTranslation('name', $l, false))), $wanted, true)) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Branch JSON (names) to the row shape the merge understands (ids).
     *
     * @param  list<array<string, mixed>>  $branches
     * @param  list<string>  $warnings
     * @return list<array<string, mixed>>
     */
    private function branchRows(array $branches, array &$warnings): array
    {
        $govs = Governorate::all();
        $cities = City::all();
        $rows = [];

        foreach ($branches as $b) {
            $label = $b['name']['en'] ?? $b['address']['en'] ?? 'branch';
            $gov = $this->byName($govs, $b['governorate'] ?? null);
            if (! empty($b['governorate']) && ! $gov) {
                $warnings[] = "Branch \"{$label}\": governorate not found — left empty.";
            }
            $city = null;
            if (! empty($b['city'])) {
                $match = $cities->filter(fn (City $c) => $this->byName(collect([$c]), $b['city']) !== null);
                $city = ($gov ? $match->firstWhere('governorate_id', $gov->id) : null) ?? $match->first();
                if (! $city) {
                    $warnings[] = "Branch \"{$label}\": city not found — left empty.";
                }
            }
            $rows[] = [
                'name' => $b['name']['en'] ?? ($b['name']['ar'] ?? ''),
                'name_ar' => $b['name']['ar'] ?? null,
                'address' => $b['address']['en'] ?? ($b['address']['ar'] ?? ''),
                'address_ar' => $b['address']['ar'] ?? null,
                'area' => $b['area'] ?? [],
                'phone' => $b['phone'] ?? null,
                'governorate_id' => $gov?->id,
                'city_id' => $city?->id,
                'latitude' => is_numeric($b['latitude'] ?? null) ? (float) $b['latitude'] : null,
                'longitude' => is_numeric($b['longitude'] ?? null) ? (float) $b['longitude'] : null,
                'google_location_url' => $b['google_location_url'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, int>
     */
    private function restoreMedia(Store $store, array $data, string $dir, bool $overwrite): array
    {
        $done = ['media' => 0, 'gallery' => 0];

        foreach ($data['media'] ?? [] as $collection => $info) {
            $path = $dir.'/'.($info['file'] ?? '');
            if (! in_array($collection, StoreMigrationExporter::MEDIA_COLLECTIONS, true) || ! is_file($path)) {
                continue;
            }
            $store->unsetRelation('media');
            if ($store->getFirstMedia($collection)) {
                if (! $overwrite) {
                    continue;
                }
                $store->clearMediaCollection($collection);
            }
            $store->addMedia($path)->preservingOriginal()->usingFileName(basename($path))->toMediaCollection($collection);
            $done['media']++;
        }

        // Editor images keep their path so the URLs inside descriptions still resolve.
        $disk = Storage::disk('public');
        $editor = [];
        foreach ($data['editor_images'] ?? [] as $img) {
            $src = $dir.'/'.($img['file'] ?? '');
            $target = ltrim((string) ($img['path'] ?? ''), '/');
            if ($target === '' || str_contains($target, '..') || ! is_file($src)) {
                continue;
            }
            if (! $disk->exists($target)) {
                $disk->putFileAs(dirname($target), new File($src), basename($target));
            }
            $editor[] = $target;
        }
        if ($editor) {
            $store->attachEditorImages($editor);
        }

        $existingHashes = $store->galleries->reject(fn ($g) => \App\Support\EditorImages::isAnyEditorPath($g->media_path))
            ->map(fn ($g) => $disk->exists($g->media_path) ? sha1_file($disk->path($g->media_path)) : null)->filter()->all();
        $sort = (int) StoreGallery::where('store_id', $store->id)->max('sort_order');

        foreach ($data['gallery'] ?? [] as $item) {
            $src = $dir.'/'.($item['file'] ?? '');
            if (! is_file($src)) {
                continue;
            }
            $hash = sha1_file($src);
            if (in_array($hash, $existingHashes, true)) {
                continue;
            }
            $stored = $disk->putFileAs("stores/gallery/{$store->id}", new File($src), $hash.'_'.basename($src));
            StoreGallery::create([
                'store_id' => $store->id,
                'media_path' => $stored,
                'type' => in_array($item['type'] ?? '', StoreGallery::TYPES, true) ? $item['type'] : StoreGallery::TYPE_IMAGE,
                'sort_order' => ++$sort,
            ]);
            $existingHashes[] = $hash;
            $done['gallery']++;
        }

        return $done;
    }

    private function removeDirectory(string $dir): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
