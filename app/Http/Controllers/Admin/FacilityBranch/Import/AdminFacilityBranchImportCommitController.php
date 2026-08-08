<?php

namespace App\Http\Controllers\Admin\FacilityBranch\Import;

use App\Http\Controllers\Controller as BaseController;
use App\Models\FacilityBranch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminFacilityBranchImportCommitController extends BaseController
{
    /**
     * Apply the (possibly edited) preview rows to the database.
     *
     * Modes:
     *  - "upsert": match each row by facility + branch name → update if found, insert otherwise.
     *  - "clear":  delete every existing branch first, then insert all rows as new.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'mode' => ['required', 'in:upsert,clear'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'rows.*.name' => ['required', 'string', 'max:255'],
            'rows.*.name_ar' => ['nullable', 'string', 'max:255'],
            'rows.*.address' => ['nullable', 'string'],
            'rows.*.address_ar' => ['nullable', 'string'],
            'rows.*.phone' => ['nullable', 'string', 'max:255'],
            'rows.*.governorate_id' => ['nullable', 'integer', 'exists:governorates,id'],
            'rows.*.city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'rows.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'rows.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $mode = $payload['mode'];
        $rows = $payload['rows'];

        $created = 0;
        $updated = 0;
        $cleared = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            if ($mode === 'clear') {
                $cleared = FacilityBranch::count();
                FacilityBranch::query()->delete();
            }

            foreach ($rows as $i => $row) {
                try {
                    $nameEn = $row['name'];
                    $nameAr = $row['name_ar'] ?? $row['name'];
                    $addrEn = $row['address'] ?? null;
                    $addrAr = $row['address_ar'] ?? $addrEn;
                    $phone = $this->normalizePhone($row['phone'] ?? null);

                    $existing = null;
                    if ($mode === 'upsert') {
                        $needle = mb_strtolower(trim($nameEn));
                        $existing = FacilityBranch::where('facility_id', $row['facility_id'])
                            ->where(function ($q) use ($nameEn, $nameAr, $needle) {
                                $q->where('name->en', $nameEn)
                                  ->orWhere('name->ar', $nameAr)
                                  ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, "$.en"))) = ?', [$needle]);
                            })
                            ->first();
                    }

                    $payloadAttrs = [
                        'facility_id' => $row['facility_id'],
                        'name' => ['en' => $nameEn, 'ar' => $nameAr],
                        'address' => $addrEn !== null ? ['en' => $addrEn, 'ar' => $addrAr] : null,
                        'phone' => $phone,
                        'governorate_id' => $row['governorate_id'] ?? null,
                        'city_id' => $row['city_id'] ?? null,
                        'latitude' => $row['latitude'] ?? null,
                        'longitude' => $row['longitude'] ?? null,
                    ];

                    if ($existing) {
                        $existing->update($payloadAttrs);
                        $updated++;
                    } else {
                        FacilityBranch::create(array_merge($payloadAttrs, [
                            'created_by' => Auth::id(),
                        ]));
                        $created++;
                    }
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
            Log::error('Facility branch import commit failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Import failed: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Import complete.',
            'mode' => $mode,
            'created' => $created,
            'updated' => $updated,
            'cleared' => $cleared,
        ]);
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
