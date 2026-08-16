<?php

namespace App\Http\Controllers\Admin\Company\Import;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AdminCompanyImportCommitController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_COMPANIES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_COMPANIES; }

    /**
     * Apply the (possibly edited) preview rows to the database.
     *
     * Modes:
     *  - "upsert":      match each row by id, then slug, then name → update if
     *                   found, insert otherwise.
     *  - "create_only": insert only rows that do not already exist; existing
     *                   rows are skipped.
     *  - "clear":       delete existing companies first (companies that still
     *                   have members are skipped, same rule as the UI), then
     *                   insert every imported row as a brand-new company.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'mode' => ['required', 'in:upsert,create_only,clear'],
            'rows' => ['required', 'array'],
            'rows.*.id' => ['nullable', 'integer'],
            'rows.*.name_en' => ['nullable', 'string', 'max:255'],
            'rows.*.name_ar' => ['nullable', 'string', 'max:255'],
            'rows.*.slug' => ['nullable', 'string', 'max:255'],
            'rows.*.created_by_email' => ['nullable', 'string', 'max:255'],
            'rows.*.created_at' => ['nullable', 'date'],
            'rows.*.updated_at' => ['nullable', 'date'],
        ]);

        $mode = $payload['mode'];
        $rows = $payload['rows'];

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $deleted = 0;
        $skippedDelete = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            if ($mode === 'clear') {
                $companies = Company::query()
                    ->tap(fn ($q) => $this->applyCreatorScope($q))
                    ->orderBy('id')
                    ->get();

                foreach ($companies as $company) {
                    if ($company->memberships()->exists()) {
                        $skippedDelete++;
                        continue;
                    }
                    $company->delete();
                    $deleted++;
                }
            }

            foreach ($rows as $i => $row) {
                try {
                    $nameEn = trim((string) ($row['name_en'] ?? ''));
                    $nameAr = trim((string) ($row['name_ar'] ?? ''));
                    if ($nameEn === '' && $nameAr === '') {
                        $skipped++;
                        continue;
                    }

                    $rowId = isset($row['id']) && $row['id'] !== null && $row['id'] !== ''
                        ? (int) $row['id']
                        : null;
                    $rowSlug = trim((string) ($row['slug'] ?? ''));

                    $existing = $this->findMatch($rowId, $rowSlug, $nameEn, $nameAr);

                    // "Add only" never touches rows that already exist.
                    if ($mode === 'create_only' && $existing) {
                        $skipped++;
                        continue;
                    }

                    if ($existing && $mode === 'upsert') {
                        $this->applyUpdate($existing, $nameEn, $nameAr, $rowSlug);
                        $updated++;
                        continue;
                    }

                    if ($rowId !== null && Company::where('id', $rowId)->exists()) {
                        $skipped++;
                        continue;
                    }

                    Company::forceCreate($this->buildCreateAttributes($rowId, $nameEn, $nameAr, $rowSlug, $row['created_by_email'] ?? '', $row['created_at'] ?? '', $row['updated_at'] ?? ''));
                    $created++;
                } catch (\Throwable $e) {
                    $errors[] = [
                        'index' => $i,
                        'name' => $nameEn ?? null,
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
            Log::error('Company import commit failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Import failed: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Import complete.',
            'mode' => $mode,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'deleted' => $deleted,
            'skippedDelete' => $skippedDelete,
        ]);
    }

    private function findMatch(?int $id, string $slug, string $nameEn, string $nameAr): ?Company
    {
        $query = fn () => Company::query()->tap(fn ($q) => $this->applyCreatorScope($q));

        if ($id !== null) {
            $match = $query()->where('id', $id)->first();
            if ($match) {
                return $match;
            }
        }

        if ($slug !== '') {
            $match = $query()->where('slug', $slug)->first();
            if ($match) {
                return $match;
            }
        }

        if ($nameEn !== '') {
            $needle = mb_strtolower(trim($nameEn));
            $match = $query()
                ->where('name->en', $nameEn)
                ->orWhere('name->ar', $nameEn)
                ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, "$.en"))) = ?', [$needle])
                ->first();
            if ($match) {
                return $match;
            }
        }

        return null;
    }

    private function applyUpdate(Company $company, string $nameEn, string $nameAr, string $rowSlug): void
    {
        $name = $company->getTranslations('name');
        if ($nameEn !== '') {
            $name['en'] = $nameEn;
        }
        if ($nameAr !== '') {
            $name['ar'] = $nameAr;
        }

        // Keep the file's slug when provided, otherwise preserve the current one.
        $intendedSlug = $rowSlug !== '' ? $rowSlug : (string) $company->slug;

        $company->update([
            'name' => $name,
            'slug' => $intendedSlug,
        ]);

        // HasSlug regenerates the slug from the (possibly translated) name on
        // every update, which would silently overwrite the intended slug — for
        // example when the current locale is Arabic. Restore it if it drifted.
        // Events are skipped so the regenerator does not run again.
        if ($company->slug !== $intendedSlug) {
            $company->forceFill(['slug' => $intendedSlug])->saveQuietly();
        }
    }

    private function buildCreateAttributes(?int $id, string $nameEn, string $nameAr, string $rowSlug, string $createdByEmail, string $createdAt, string $updatedAt): array
    {
        $name = [];
        if ($nameEn !== '') {
            $name['en'] = $nameEn;
        }
        if ($nameAr !== '') {
            $name['ar'] = $nameAr;
        }
        if (empty($name)) {
            $name['en'] = 'Untitled';
        }

        $slug = $rowSlug;
        if ($slug === '') {
            $slug = Str::slug($nameEn !== '' ? $nameEn : $nameAr);
        }

        $attrs = [
            'name' => $name,
            'created_by' => $this->resolveCreatorId($createdByEmail),
        ];

        if ($slug !== '') {
            $attrs['slug'] = $slug;
        }
        if ($id !== null) {
            $attrs['id'] = $id;
        }
        if ($createdAt !== '') {
            $attrs['created_at'] = date('Y-m-d H:i:s', strtotime($createdAt));
        }
        if ($updatedAt !== '') {
            $attrs['updated_at'] = date('Y-m-d H:i:s', strtotime($updatedAt));
        }

        return $attrs;
    }

    private function resolveCreatorId(string $email): int
    {
        $email = trim($email);
        if ($email !== '') {
            $user = User::where('email', $email)->first();
            if ($user) {
                return $user->id;
            }
        }
        return Auth::id();
    }
}
