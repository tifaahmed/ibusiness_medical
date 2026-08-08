<?php

namespace App\Http\Controllers\Admin\Facility\Import;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Facility;
use App\Models\FacilityBranch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminFacilityImportCommitController extends BaseController
{
    /**
     * Apply the (possibly edited) preview rows to the database.
     *
     * Modes:
     *  - "upsert": match each row by slug (then by name) → update if found, insert otherwise.
     *  - "clear":  delete every existing facility + its branches first, then insert all
     *              imported rows as brand-new facilities.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'mode' => ['required', 'in:upsert,clear'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.name' => ['required', 'string', 'max:255'],
            'rows.*.name_ar' => ['nullable', 'string', 'max:255'],
            'rows.*.slug' => ['nullable', 'string', 'max:255'],
            'rows.*.facility_type_id' => ['required', 'integer', 'exists:facility_types,id'],
            'rows.*.governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'rows.*.city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'rows.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'rows.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'rows.*.branches' => ['nullable', 'array'],
            'rows.*.branches.*.name' => ['nullable', 'string', 'max:255'],
            'rows.*.branches.*.name_ar' => ['nullable', 'string', 'max:255'],
            'rows.*.branches.*.address' => ['nullable', 'string'],
            'rows.*.branches.*.address_ar' => ['nullable', 'string'],
            'rows.*.branches.*.phone' => ['nullable', 'string', 'max:255'],
            'rows.*.branches.*.governorate_id' => ['nullable', 'integer', 'exists:governorates,id'],
            'rows.*.branches.*.city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'rows.*.branches.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'rows.*.branches.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $mode = $payload['mode'];
        $rows = $payload['rows'];

        $created = 0;
        $updated = 0;
        $cleared = 0;
        $branchesUpserted = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            if ($mode === 'clear') {
                $cleared = Facility::count();
                FacilityBranch::query()->delete();
                Facility::query()->delete();
            }

            foreach ($rows as $i => $row) {
                try {
                    $nameEn = $row['name'];
                    $nameAr = $row['name_ar'] ?? $row['name'];

                    $existing = null;
                    if ($mode === 'upsert') {
                        if (!empty($row['slug'])) {
                            $existing = Facility::where('slug', $row['slug'])->first();
                        }
                        if (!$existing) {
                            $existing = Facility::where('name->en', $nameEn)
                                ->orWhere('name->ar', $nameAr)
                                ->first();
                        }
                    }

                    $payloadAttrs = [
                        'name' => ['en' => $nameEn, 'ar' => $nameAr],
                        'facility_type_id' => $row['facility_type_id'],
                        'governorate_id' => $row['governorate_id'],
                        'city_id' => $row['city_id'] ?? null,
                        'latitude' => $row['latitude'] ?? null,
                        'longitude' => $row['longitude'] ?? null,
                    ];

                    if ($existing) {
                        $existing->update($payloadAttrs);
                        $facility = $existing;
                        $updated++;
                    } else {
                        $facility = Facility::create(array_merge($payloadAttrs, [
                            'created_by' => Auth::id(),
                        ]));
                        $created++;
                    }

                    $branchesUpserted += $this->syncBranches($facility, $row['branches'] ?? []);
                } catch (\Throwable $e) {
                    $errors[] = [
                        'index' => $i,
                        'name' => $row['name'] ?? null,
                        'message' => $e->getMessage(),
                    ];
                }
            }

            if (!empty($errors)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Some rows failed; nothing was saved.',
                    'errors' => $errors,
                ], 422);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Facility import commit failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Import failed: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Import complete.',
            'mode' => $mode,
            'created' => $created,
            'updated' => $updated,
            'cleared' => $cleared,
            'branches' => $branchesUpserted,
        ]);
    }

    /**
     * Upsert branches for a facility by EN-name match (case-insensitive).
     * Existing branches not present in the import are left alone.
     */
    private function syncBranches(Facility $facility, array $branches): int
    {
        if (empty($branches)) {
            return 0;
        }
        $existing = $facility->branches()->get()->keyBy(
            fn($b) => mb_strtolower(trim((string) ($b->getTranslation('name', 'en') ?: '')))
        );

        $count = 0;
        foreach ($branches as $b) {
            $nameEn = trim((string) ($b['name'] ?? ''));
            $nameAr = trim((string) ($b['name_ar'] ?? '')) ?: $nameEn;
            // Skip empties — but allow a branch with only an address.
            if ($nameEn === '' && empty($b['address'])) {
                continue;
            }
            $phone = $this->normalizePhone($b['phone'] ?? null);

            $payload = [
                'name' => $nameEn !== '' ? ['en' => $nameEn, 'ar' => $nameAr] : null,
                'address' => !empty($b['address']) ? ['en' => $b['address'], 'ar' => $b['address_ar'] ?? $b['address']] : null,
                'phone' => $phone,
                'governorate_id' => $b['governorate_id'] ?? null,
                'city_id' => $b['city_id'] ?? null,
                'latitude' => $b['latitude'] ?? null,
                'longitude' => $b['longitude'] ?? null,
            ];

            $key = mb_strtolower($nameEn);
            if ($key !== '' && isset($existing[$key])) {
                $existing[$key]->update($payload);
            } else {
                $facility->branches()->create(array_merge($payload, [
                    'created_by' => Auth::id(),
                ]));
            }
            $count++;
        }
        return $count;
    }

    private function normalizePhone($raw): ?array
    {
        if (is_array($raw)) {
            $values = array_values(array_filter(array_map('trim', $raw), fn($p) => $p !== ''));
            return $values ?: null;
        }
        if (is_string($raw) && trim($raw) !== '') {
            $parts = array_values(array_filter(array_map('trim', preg_split('/[,;|]/', $raw)), fn($p) => $p !== ''));
            return $parts ?: null;
        }
        return null;
    }
}
