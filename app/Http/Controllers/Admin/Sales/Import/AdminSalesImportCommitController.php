<?php

namespace App\Http\Controllers\Admin\Sales\Import;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Sales;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AdminSalesImportCommitController extends BaseController
{
    /**
     * Apply the (possibly edited) preview rows to the database.
     *
     * Strategies:
     *  - "update":              rows whose id exists are UPDATED; unknown/empty ids are inserted.
     *  - "create":              every included row is inserted as a brand-new record (new auto ids).
     *  - "delete_all_then_add": all existing sales are deleted first, then every row is inserted
     *                           with its exact id preserved (full restore).
     *  - "add_only":            only rows whose id does not exist yet are inserted; existing rows are skipped.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'strategy' => ['required', 'in:update,create,delete_all_then_add,add_only'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.index' => ['nullable', 'integer'],
            'rows.*.id' => ['nullable', 'integer', 'gt:0'],
            'rows.*.name_ar' => ['nullable', 'string', 'max:255'],
            'rows.*.name_en' => ['nullable', 'string', 'max:255'],
            'rows.*.image_url' => ['nullable', 'string'],
            'rows.*.created_by' => ['nullable', 'integer'],
        ]);

        $strategy = $payload['strategy'];
        $rows = $payload['rows'];

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $cleared = 0;
        $errors = [];
        $usedIds = [];

        DB::beginTransaction();
        try {
            if ($strategy === 'delete_all_then_add') {
                $cleared = Sales::count();
                Sales::query()->delete();
            }

            foreach ($rows as $i => $row) {
                $nameAr = trim((string) ($row['name_ar'] ?? ''));
                $nameEn = trim((string) ($row['name_en'] ?? ''));

                if ($nameAr === '' && $nameEn === '') {
                    $skipped++;
                    continue;
                }

                $id = !empty($row['id']) ? (int) $row['id'] : null;
                $existing = null;

                if ($strategy === 'update' && $id !== null) {
                    $existing = Sales::find($id);
                }

                if ($strategy === 'add_only' && $id !== null && Sales::whereKey($id)->exists()) {
                    $skipped++;
                    continue;
                }

                if ($existing === null && $id !== null && ($strategy === 'create')) {
                    // "create" always makes fresh records; ignore a taken id.
                    $id = null;
                }

                $name = $this->buildName($nameAr, $nameEn);
                $createdBy = $this->resolveCreatedBy($row['created_by'] ?? null);

                try {
                    if ($existing) {
                        $existing->update(['name' => $name]);
                        $this->syncImage($existing, $row['image_url'] ?? '');
                        $updated++;
                        continue;
                    }

                    if ($id !== null && isset($usedIds[$id])) {
                        // Duplicate id inside the same file → create as a fresh row.
                        $id = null;
                    }

                    $attrs = ['name' => $name, 'created_by' => $createdBy];
                    $sales = new Sales($attrs);
                    if ($id !== null) {
                        // id is not mass-assignable, so set it explicitly.
                        $sales->id = $id;
                    }
                    $sales->save();
                    if ($id !== null) {
                        $usedIds[$id] = true;
                    }
                    $this->syncImage($sales, $row['image_url'] ?? '');
                    $created++;
                } catch (\Throwable $e) {
                    $errors[] = [
                        'index' => $row['index'] ?? $i,
                        'name' => $nameEn ?: $nameAr,
                        'message' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Sales import commit failed', [
                'strategy' => $strategy,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Import failed: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Import complete.',
            'strategy' => $strategy,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'cleared' => $cleared,
            'errors' => $errors,
        ]);
    }

    /**
     * Build the translatable name array. At least one locale is guaranteed by
     * the loop above, so both keys always end up with a value.
     */
    private function buildName(string $nameAr, string $nameEn): array
    {
        if ($nameAr === '') {
            $nameAr = $nameEn;
        }
        if ($nameEn === '') {
            $nameEn = $nameAr;
        }
        return ['ar' => $nameAr, 'en' => $nameEn];
    }

    /**
     * Prefer the creator id from the file when that user still exists,
     * otherwise fall back to the importing user.
     */
    private function resolveCreatedBy($raw): ?int
    {
        if (!empty($raw) && User::whereKey((int) $raw)->exists()) {
            return (int) $raw;
        }
        return Auth::id();
    }

    /**
     * Try to download an image from the exported URL and attach it to the media
     * collection. Failures are non-fatal — the sales row is still imported.
     */
    private function syncImage(Sales $sales, string $url): void
    {
        $url = trim($url);
        if ($url === '') {
            return;
        }

        try {
            $response = Http::timeout(15)->get($url);
            if (!$response->ok()) {
                return;
            }

            $mime = $response->header('Content-Type');
            if (!is_string($mime) || !str_starts_with($mime, 'image/')) {
                return;
            }

            $temp = tempnam(sys_get_temp_dir(), 'sales_import_');
            if ($temp === false) {
                return;
            }
            file_put_contents($temp, $response->body());

            $sales->clearMediaCollection('image');
            $sales->addMedia($temp)
                ->usingFileName('image_' . $sales->id . $this->extensionForMime($mime))
                ->toMediaCollection('image');
            @unlink($temp);
        } catch (\Throwable $e) {
            Log::warning('Sales import: could not attach image', [
                'sales_id' => $sales->id,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'image/png' => '.png',
            'image/gif' => '.gif',
            'image/webp' => '.webp',
            'image/avif' => '.avif',
            'image/svg+xml' => '.svg',
            'image/jpeg', 'image/jpg' => '.jpg',
            default => '.jpg',
        };
    }
}
