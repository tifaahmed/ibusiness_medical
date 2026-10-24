<?php

namespace App\Http\Controllers\Admin\Store\Import;

use App\Actions\Store\MergeStoreContacts;
use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Store;
use App\Services\StoreMigration\StoreMigrationExporter;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies the (possibly edited) preview rows. UPSERT ONLY, and deliberately so:
 * a row either updates the store it matched or creates a new one; nothing the
 * file does not mention is removed, and a blank cell never overwrites a value.
 * All rows go in one transaction — one bad row saves nothing.
 *
 * `mode=clear` (like the facility importer's) removes the existing stores first.
 * Stores are not soft-deleted, so it is guarded: full permission only, a typed
 * confirmation, and a complete package of every store (images included) is
 * written and verified first — restorable from the Migration page. A store that
 * still has products is skipped, exactly as deleting it by hand would refuse.
 */
class AdminStoreImportCommitController extends BaseController
{
    use CreatorScoped;

    public const CLEAR_PHRASE = 'DELETE ALL STORES';

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_STORES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_STORES;
    }

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'mode' => ['nullable', 'in:upsert,clear'],
            'confirm' => ['nullable', 'string'],
            'rows' => ['required', 'array', 'min:1', 'max:2000'],
            'rows.*.title' => ['required', 'string', 'max:255'],
            'rows.*.title_ar' => ['nullable', 'string', 'max:255'],
            'rows.*.slug' => ['nullable', 'string', 'max:255'],
            'rows.*.contracted_at' => ['nullable', 'date'],
            'rows.*.online_only' => ['nullable', 'boolean'],
            'rows.*.sales_id' => ['nullable', 'integer', 'exists:sales,id'],
            'rows.*.category_ids' => ['nullable', 'array'],
            'rows.*.category_ids.*' => ['integer', 'exists:store_categories,id'],
            'rows.*.branches' => ['nullable', 'array'],
            'rows.*.branches.*.name' => ['nullable', 'string', 'max:255'],
            'rows.*.branches.*.name_ar' => ['nullable', 'string', 'max:255'],
            'rows.*.branches.*.address' => ['nullable', 'string', 'max:500'],
            'rows.*.branches.*.address_ar' => ['nullable', 'string', 'max:500'],
            'rows.*.branches.*.phone' => ['nullable', 'string', 'max:1000'],
            'rows.*.branches.*.governorate_id' => ['nullable', 'integer', 'exists:governorates,id'],
            'rows.*.branches.*.city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'rows.*.branches.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'rows.*.branches.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'rows.*.managers' => ['nullable', 'array'],
            'rows.*.managers.*.name' => ['required', 'string', 'max:255'],
            'rows.*.managers.*.position' => ['nullable', 'string', 'max:255'],
            'rows.*.managers.*.phones' => ['nullable', 'string', 'max:1000'],
        ]);

        $stats = ['created' => 0, 'updated' => 0, 'branches' => 0, 'managers' => 0];
        $errors = [];
        $clearing = ($payload['mode'] ?? 'upsert') === 'clear';
        $backup = null;
        $skipped = [];
        $filesToDelete = [];

        if ($clearing) {
            // Wiping the catalogue is a whole-site act: never for "manage own".
            if ($this->scopesToCreator()) {
                return response()->json(['message' => 'Clearing the stores needs the full "manage stores" permission.'], 403);
            }
            if (($payload['confirm'] ?? '') !== self::CLEAR_PHRASE) {
                return response()->json(['message' => 'Type "'.self::CLEAR_PHRASE.'" exactly to confirm clearing the stores.'], 422);
            }

            try {
                $backup = $this->backupEverything();
            } catch (\Throwable $e) {
                Log::error('Store import: safety backup failed, nothing was cleared', ['admin_id' => Auth::id(), 'error' => $e->getMessage()]);

                return response()->json(['message' => 'The safety backup could not be made, so nothing was cleared: '.$e->getMessage()], 500);
            }
        }

        DB::beginTransaction();
        try {
            if ($clearing) {
                foreach (Store::query()->withCount('products')->get() as $gone) {
                    // Same rule as deleting a store by hand: one that still has products stays.
                    if ($gone->products_count > 0) {
                        $skipped[] = $gone->getTranslation('title', 'en', false) ?: $gone->getTranslation('title', 'ar', false);

                        continue;
                    }
                    foreach ($gone->galleries as $item) {
                        $filesToDelete[] = $item->media_path;
                    }
                    $gone->delete();
                    $stats['cleared'] = ($stats['cleared'] ?? 0) + 1;
                }
            }

            foreach ($payload['rows'] as $i => $row) {
                try {
                    $this->applyRow($row, $stats);
                } catch (\Throwable $e) {
                    $errors[] = ['index' => $i, 'name' => $row['title'] ?? null, 'message' => $e->getMessage()];
                }
            }

            if ($errors) {
                DB::rollBack();
                Log::warning('Store import rejected', ['admin_id' => Auth::id(), 'errors' => $errors]);

                return response()->json(['message' => 'Some rows failed; nothing was saved.', 'errors' => $errors], 422);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Store import commit failed', ['admin_id' => Auth::id(), 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json(['message' => 'Import failed: '.$e->getMessage()], 500);
        }

        // Files go only once the database commit has succeeded.
        foreach ($filesToDelete as $path) {
            Storage::disk('public')->delete($path);
        }

        Log::info('Store import committed', ['admin_id' => Auth::id(), 'mode' => $clearing ? 'clear' : 'upsert', 'backup' => $backup, 'skipped' => count($skipped)] + $stats);

        return response()->json(['message' => 'Import complete.', 'backup' => $backup, 'skipped_with_products' => $skipped] + $stats);
    }

    /**
     * A complete package (data and images) of every store, written and checked
     * BEFORE anything is cleared. Restorable from the Migration page.
     */
    private function backupEverything(): string
    {
        $name = 'pre-clear-'.now()->format('Y-m-d_His').'.zip';
        $path = StoreMigrationExporter::packageDirectory().'/'.$name;
        $expected = Store::count();

        $exporter = app(StoreMigrationExporter::class);
        $built = $exporter->build($exporter->query()->get(), $path, true, ['reason' => 'safety copy before "clear stores" import', 'exported_by' => Auth::user()?->email]);

        $zip = new ZipArchive;
        $manifest = $zip->open($path) === true ? json_decode((string) $zip->getFromName('manifest.json'), true) : null;
        $zip->close();
        if (! is_array($manifest) || (int) ($manifest['stores'] ?? -1) !== $expected || $built['stores'] !== $expected) {
            @unlink($path);
            throw new \RuntimeException('the backup did not verify');
        }

        return $name;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $stats
     */
    private function applyRow(array $row, array &$stats): void
    {
        $store = $this->findStore($row);

        if ($store) {
            $this->assertOwns($store);

            // Only what the file actually says: a blank cell leaves the store as it was.
            $title = $store->getTranslations('title');
            $title['en'] = $row['title'];
            if (! empty($row['title_ar'])) {
                $title['ar'] = $row['title_ar'];
            }
            $attrs = array_filter([
                'sales_id' => $row['sales_id'] ?? null,
                'contracted_at' => $row['contracted_at'] ?? null,
                'online_only' => $row['online_only'] ?? null,
            ], fn ($v) => $v !== null);

            $store->update(['title' => $title] + $attrs);
            $stats['updated']++;
        } else {
            $store = Store::create([
                'title' => ['en' => $row['title'], 'ar' => $row['title_ar'] ?? $row['title']],
                'sales_id' => $row['sales_id'] ?? null,
                'contracted_at' => $row['contracted_at'] ?? null,
                'online_only' => (bool) ($row['online_only'] ?? false),
                'created_by' => Auth::id(),
            ]);
            $stats['created']++;
        }

        // Add categories, never detach the ones the store already has.
        if (! empty($row['category_ids'])) {
            $store->categories()->syncWithoutDetaching($row['category_ids']);
        }

        // An online-only store has no branches (the same rule as the form).
        if (! $store->online_only) {
            $stats['branches'] += app(MergeStoreContacts::class)->branches($store, $row['branches'] ?? []);
        }
        $stats['managers'] += app(MergeStoreContacts::class)->managers($store, $row['managers'] ?? []);
    }

    /** @param array<string, mixed> $row */
    private function findStore(array $row): ?Store
    {
        if (! empty($row['slug']) && ($s = Store::where('slug', $row['slug'])->first())) {
            return $s;
        }

        $needle = mb_strtolower(trim($row['title']));
        $needleAr = mb_strtolower(trim((string) ($row['title_ar'] ?? '')));

        return Store::query()->get()->first(function (Store $s) use ($needle, $needleAr) {
            foreach (['en', 'ar'] as $loc) {
                $t = mb_strtolower(trim((string) $s->getTranslation('title', $loc, false)));
                if ($t !== '' && ($t === $needle || ($needleAr !== '' && $t === $needleAr))) {
                    return true;
                }
            }

            return false;
        });
    }
}
