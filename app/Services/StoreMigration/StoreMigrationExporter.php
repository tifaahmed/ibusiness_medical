<?php

namespace App\Services\StoreMigration;

use App\Models\Store;
use App\Models\StoreGallery;
use App\Support\EditorImages;
use App\Support\StoreSpreadsheet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Builds a store migration package: one ZIP holding a manifest, one JSON file
 * per store and (optionally) the store's image files.
 *
 *   manifest.json            format, version, counts, filters described
 *   stores/000001.json       one store: fields, categories, tags, sales,
 *                            branches, contacts, media + gallery file refs
 *   media/<n>/<collection>/<file>      logo, header, seo_image, contract
 *   gallery/<n>/<file>                 gallery images and videos
 *   editor/<path…>                     description-editor images, kept at their
 *                                      original path so description URLs still work
 *
 * Everything is by NAME, never by id, so a package restores onto a database
 * whose ids differ. The same builder produces the safety copy taken before a
 * "clear" import, which is why it must be complete.
 */
class StoreMigrationExporter
{
    public const FORMAT = 'deilar-store-package';

    public const VERSION = 1;

    /** Media-library collections a store carries. */
    public const MEDIA_COLLECTIONS = ['logo', 'header', 'seo_image', 'contract'];

    public static function packageDirectory(): string
    {
        $dir = storage_path('app/store-migration/packages');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /**
     * @param  array<string, mixed>  $filters  search, category_ids, sales_id, created_from, created_to
     * @return Builder<Store>
     */
    public function query(array $filters = []): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $categoryIds = array_values(array_filter((array) ($filters['category_ids'] ?? []), 'is_numeric'));

        return Store::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('title->en', 'like', "%{$search}%")
                        ->orWhere('title->ar', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($categoryIds !== [], fn ($q) => $q->whereHas('categories', fn ($c) => $c->whereIn('store_categories.id', $categoryIds)))
            ->when(! empty($filters['sales_id']), fn ($q) => $q->where('sales_id', (int) $filters['sales_id']))
            ->when(! empty($filters['created_from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['created_from']))
            ->when(! empty($filters['created_to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['created_to']))
            ->orderBy('id');
    }

    /** @return list<string> the filters in words, for the manifest and the screen */
    public function describeFilters(array $filters): array
    {
        $out = [];
        if (! empty($filters['search'])) {
            $out[] = 'Search: '.$filters['search'];
        }
        if (! empty($filters['category_ids'])) {
            $out[] = 'Categories: '.implode(', ', (array) $filters['category_ids']);
        }
        if (! empty($filters['sales_id'])) {
            $out[] = 'Sales rep #'.$filters['sales_id'];
        }
        if (! empty($filters['created_from'])) {
            $out[] = 'Created from '.$filters['created_from'];
        }
        if (! empty($filters['created_to'])) {
            $out[] = 'Created to '.$filters['created_to'];
        }

        return $out ?: ['No filters — every store'];
    }

    /**
     * Write the package for $stores to $zipPath.
     *
     * @param  iterable<Store>  $stores
     * @param  array<string, mixed>  $meta  extra manifest keys (filters, part, parts…)
     * @return array{stores: int, files: int}
     */
    public function build(iterable $stores, string $zipPath, bool $withMedia = true, array $meta = []): array
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the package file.');
        }

        $count = 0;
        $files = 0;
        $editorSeen = [];

        foreach ($stores as $store) {
            $count++;
            $store->loadMissing(['categories', 'tags', 'sales', 'shippingGovernorates', 'branches.governorate', 'branches.city', 'managers', 'galleries', 'media']);
            $data = $this->serialize($store);

            if ($withMedia) {
                $data['media'] = [];
                foreach (self::MEDIA_COLLECTIONS as $collection) {
                    $media = $store->getFirstMedia($collection);
                    if ($media && is_file($media->getPath())) {
                        $entry = sprintf('media/%d/%s/%s', $count, $collection, $this->safeName($media->file_name));
                        $zip->addFile($media->getPath(), $entry);
                        $data['media'][$collection] = ['file' => $entry, 'name' => $media->file_name, 'mime' => $media->mime_type];
                        $files++;
                    }
                }

                $data['gallery'] = [];
                $data['editor_images'] = [];
                foreach ($store->galleries as $item) {
                    $disk = Storage::disk('public');
                    if (! $disk->exists($item->media_path)) {
                        continue;
                    }
                    if (EditorImages::isAnyEditorPath($item->media_path)) {
                        $entry = 'editor/'.ltrim($item->media_path, '/');
                        if (! isset($editorSeen[$entry])) {
                            $zip->addFile($disk->path($item->media_path), $entry);
                            $editorSeen[$entry] = true;
                            $files++;
                        }
                        $data['editor_images'][] = ['file' => $entry, 'path' => $item->media_path];
                    } else {
                        $entry = sprintf('gallery/%d/%d_%s', $count, $item->id, $this->safeName(basename($item->media_path)));
                        $zip->addFile($disk->path($item->media_path), $entry);
                        $data['gallery'][] = ['file' => $entry, 'type' => $item->type, 'sort_order' => $item->sort_order];
                        $files++;
                    }
                }
            }

            $zip->addFromString(sprintf('stores/%06d.json', $count), json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        }

        $zip->addFromString('manifest.json', json_encode([
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exported_at' => now()->toIso8601String(),
            'app_url' => config('app.url'),
            'stores' => $count,
            'with_media' => $withMedia,
        ] + $meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        if (! $zip->close()) {
            throw new RuntimeException('Could not finish writing the package file.');
        }

        return ['stores' => $count, 'files' => $files];
    }

    /** @return array<string, mixed> */
    public function serialize(Store $store): array
    {
        $names = fn ($model) => $model->getTranslations('name');

        return [
            'title' => $store->getTranslations('title'),
            'description' => $store->getTranslations('description'),
            'short_description' => $store->getTranslations('short_description'),
            'meta_title' => $store->getTranslations('meta_title'),
            'meta_description' => $store->getTranslations('meta_description'),
            'meta_keywords' => $store->getTranslations('meta_keywords'),
            'slug' => $store->slug,
            'youtube_link' => $store->youtube_link,
            'online_only' => (bool) $store->online_only,
            'websites' => $store->websites ?? [],
            'app_store_url' => $store->app_store_url,
            'google_play_url' => $store->google_play_url,
            'social_links' => $store->social_links ?? [],
            'coupons' => $store->coupons ?? [],
            'supports_shipping' => (bool) $store->supports_shipping,
            'ships_everywhere' => (bool) $store->ships_everywhere,
            'offer_percent_from' => $store->offer_percent_from,
            'offer_percent_to' => $store->offer_percent_to,
            'contracted_at' => $store->contracted_at?->toDateString(),
            'sales' => $store->sales ? $store->sales->nameTranslations() : null,
            'categories' => $store->categories->map($names)->values()->all(),
            'tags' => $store->tags->map($names)->values()->all(),
            'shipping_governorates' => $store->shippingGovernorates->map($names)->values()->all(),
            'branches' => $store->branches->map(fn ($b) => [
                'name' => $b->getTranslations('name'),
                'address' => $b->getTranslations('address'),
                'area' => $b->getTranslations('area'),
                'phone' => StoreSpreadsheet::writePhones($b->phone),
                'governorate' => $b->governorate?->getTranslations('name'),
                'city' => $b->city?->getTranslations('name'),
                'latitude' => $b->latitude,
                'longitude' => $b->longitude,
                'google_location_url' => $b->google_location_url,
            ])->values()->all(),
            'managers' => $store->managers->map(fn ($m) => [
                'name' => $m->name,
                'position' => $m->position,
                'phones' => StoreSpreadsheet::writePhones($m->phones),
            ])->values()->all(),
        ];
    }

    private function safeName(string $name): string
    {
        $clean = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?: 'file';

        return ltrim($clean, '.') ?: 'file';
    }
}
