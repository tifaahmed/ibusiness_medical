<?php

namespace App\Http\Controllers\Admin\Store\Import;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\City;
use App\Models\Governorate;
use App\Models\Sales;
use App\Models\Store;
use App\Models\StoreCategory;
use App\Support\StoreSpreadsheet;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reads an uploaded CSV/XLSX in the layout {@see StoreSpreadsheet} describes,
 * matches each row to an existing store (by slug, then by title in either
 * language) and reports what the commit would do. Writes NOTHING.
 *
 * Anything the file names that we cannot resolve (a category, a sales rep, a
 * governorate…) is a WARNING, not an error: that piece is left out and the rest
 * of the row still imports. Only a row that cannot be a store at all is an error.
 */
class AdminStoreImportPreviewController extends BaseController
{
    use CreatorScoped;

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
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ]);

        try {
            $sheets = $this->read($request->file('file')->getRealPath(), $request->file('file')->getClientOriginalExtension());
        } catch (\Throwable $e) {
            Log::warning('Store import: file could not be read', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'That file could not be read. Upload the .xlsx the export produced, or a CSV with the same headers.'], 422);
        }

        $stores = Store::query()->with(['categories:id,name', 'sales'])->get();
        $bySlug = $stores->keyBy(fn (Store $s) => mb_strtolower((string) $s->slug));
        $byTitle = collect();
        foreach ($stores as $s) {
            foreach (['en', 'ar'] as $loc) {
                $t = mb_strtolower(trim((string) $s->getTranslation('title', $loc, false)));
                if ($t !== '' && ! $byTitle->has($t)) {
                    $byTitle->put($t, $s);
                }
            }
        }

        $lookups = [
            'sales' => $this->indexSales(),
            'categories' => $this->indexByName(StoreCategory::all()),
            'governorates' => $this->indexByName(Governorate::all()),
            'cities' => City::all(),
        ];

        $branchesByStore = $this->groupByStore($sheets['branches'], 'store_title', 'store_slug');
        $managersByStore = $this->groupByStore($sheets['managers'], 'store_title', 'store_slug');

        $rows = [];
        foreach ($sheets['stores'] as $i => $raw) {
            $parsed = $this->parseStore($raw, $lookups);
            $errors = $parsed['errors'];
            $warnings = $parsed['warnings'];
            unset($parsed['errors'], $parsed['warnings']);

            $slugKey = mb_strtolower((string) $parsed['slug']);
            $titleKey = mb_strtolower(trim($parsed['title']));
            $match = ($slugKey !== '' ? $bySlug->get($slugKey) : null) ?? ($titleKey !== '' ? $byTitle->get($titleKey) : null);

            if ($match && $this->scopesToCreator() && (int) $match->created_by !== (int) Auth::id()) {
                $errors['owner'] = 'This store belongs to someone else, and you can only change stores you created.';
            }

            $branches = [];
            foreach ($branchesByStore[$titleKey] ?? $branchesByStore[$slugKey] ?? [] as $b) {
                [$branch, $branchWarnings] = $this->parseBranch($b, $lookups);
                $branches[] = $branch;
                array_push($warnings, ...$branchWarnings);
            }
            $managers = collect($managersByStore[$titleKey] ?? $managersByStore[$slugKey] ?? [])
                ->map(fn (array $m) => ['name' => $m['name'], 'position' => $m['position'], 'phones' => $m['phones']])
                ->filter(fn (array $m) => $m['name'] !== '')
                ->values()->all();

            $diff = $match ? $this->diff($match, $parsed) : [];
            $changed = collect($diff)->contains(fn ($d) => $d['changed']) || $branches !== [] || $managers !== [];

            $rows[] = [
                'index' => $i,
                'parsed' => $parsed,
                'errors' => $errors,
                'warnings' => array_values(array_unique($warnings)),
                'status' => $errors ? 'error' : ($match ? ($changed ? 'update' : 'unchanged') : 'new'),
                'match' => $match ? ['store_id' => $match->id, 'slug' => $match->slug, 'name' => $match->getTranslation('title', 'en', false) ?: $match->getTranslation('title', 'ar', false)] : null,
                'diff' => $diff,
                'branches' => $branches,
                'managers' => $managers,
            ];
        }

        return response()->json(['rows' => $rows]);
    }

    /**
     * @return array{stores: list<array<string,string>>, branches: list<array<string,string>>, managers: list<array<string,string>>}
     */
    private function read(string $path, string $extension): array
    {
        if (in_array(strtolower($extension), ['csv', 'txt'], true)) {
            $reader = new CsvReader;
            $reader->setInputEncoding('UTF-8');
        } else {
            $reader = IOFactory::createReaderForFile($path);
        }
        $reader->setReadDataOnly(true);
        $book = $reader->load($path);

        $sheet = fn (string $name) => $book->getSheetByName($name);

        $out = [
            'stores' => $this->rows($sheet(StoreSpreadsheet::SHEET_STORES) ?? $book->getActiveSheet(), ['title'], [
                'title' => ['title', 'name'],
                'title_ar' => ['title (ar)', 'name (ar)'],
                'slug' => ['slug'],
                'categories' => ['categories', 'category'],
                'sales' => ['sales rep', 'sales'],
                'contracted_at' => ['contracted date', 'contracted at'],
                'online_only' => ['online only'],
            ]),
            'branches' => [],
            'managers' => [],
        ];

        if ($s = $sheet(StoreSpreadsheet::SHEET_BRANCHES)) {
            $out['branches'] = $this->rows($s, ['branch name'], [
                'store_title' => ['store title', 'store'],
                'store_slug' => ['store slug'],
                'name' => ['branch name'],
                'name_ar' => ['branch name (ar)'],
                'address' => ['address'],
                'address_ar' => ['address (ar)'],
                'phone' => ['phone', 'phones'],
                'governorate' => ['governorate'],
                'city' => ['city'],
                'latitude' => ['latitude', 'lat'],
                'longitude' => ['longitude', 'lng'],
            ]);
        }
        if ($s = $sheet(StoreSpreadsheet::SHEET_MANAGERS)) {
            $out['managers'] = $this->rows($s, ['manager name'], [
                'store_title' => ['store title', 'store'],
                'store_slug' => ['store slug'],
                'name' => ['manager name', 'name'],
                'position' => ['position'],
                'phones' => ['phones', 'phone'],
            ]);
        }

        $book->disconnectWorksheets();

        return $out;
    }

    /**
     * Find the header row (the first holding one of $signature) and read every
     * row under it into the keys of $aliases. Stops at an "END OF REPORT" row.
     *
     * @param  list<string>  $signature
     * @param  array<string, list<string>>  $aliases
     * @return list<array<string, string>>
     */
    private function rows(Worksheet $sheet, array $signature, array $aliases): array
    {
        $data = $sheet->toArray(null, false, false, false);
        $headerAt = null;
        foreach ($data as $r => $cells) {
            foreach ($cells as $cell) {
                if (in_array(mb_strtolower(trim((string) $cell)), $signature, true)) {
                    $headerAt = $r;
                    break 2;
                }
            }
        }
        if ($headerAt === null) {
            return [];
        }

        $columns = [];
        foreach ($data[$headerAt] as $c => $label) {
            $label = mb_strtolower(trim((string) $label));
            if ($label !== '') {
                $columns[$label] = $c;
            }
        }

        $out = [];
        foreach (array_slice($data, $headerAt + 1) as $cells) {
            if (str_starts_with(mb_strtoupper(trim((string) ($cells[0] ?? ''))), 'END OF REPORT')) {
                break;
            }
            $row = [];
            foreach ($aliases as $key => $candidates) {
                $row[$key] = '';
                foreach ($candidates as $candidate) {
                    if (isset($columns[$candidate])) {
                        $row[$key] = $this->text($cells[$columns[$candidate]] ?? '');
                        break;
                    }
                }
            }
            if (array_filter($row, fn ($v) => $v !== '') !== []) {
                $out[] = $row;
            }
        }

        return $out;
    }

    private function text(mixed $value): string
    {
        if (is_float($value) && floor($value) === $value && abs($value) < 1e15) {
            return (string) (int) $value;
        }

        return trim((string) ($value ?? ''));
    }

    /**
     * @param  array<string, string>  $raw
     * @param  array<string, mixed>  $lookups
     */
    private function parseStore(array $raw, array $lookups): array
    {
        $errors = [];
        $warnings = [];

        $title = $raw['title'];
        if ($title === '') {
            $errors['title'] = 'Title is required.';
        }

        $date = null;
        if ($raw['contracted_at'] !== '') {
            try {
                $date = is_numeric($raw['contracted_at'])
                    ? ExcelDate::excelToDateTimeObject((float) $raw['contracted_at'])->format('Y-m-d')
                    : Carbon::parse($raw['contracted_at'])->toDateString();
            } catch (\Throwable) {
                $errors['contracted_at'] = "\"{$raw['contracted_at']}\" is not a date. Use YYYY-MM-DD.";
            }
        }

        $salesId = null;
        if ($raw['sales'] !== '') {
            $salesId = $lookups['sales']->get(mb_strtolower($raw['sales']))?->id;
            if ($salesId === null) {
                $warnings[] = "Sales rep \"{$raw['sales']}\" not found — left unchanged.";
            }
        }

        $categoryIds = [];
        foreach (preg_split('/\s*[,;|،]\s*/u', $raw['categories']) ?: [] as $name) {
            if (trim($name) === '') {
                continue;
            }
            $cat = $lookups['categories']->get(mb_strtolower(trim($name)));
            if ($cat) {
                $categoryIds[] = $cat->id;
            } else {
                $warnings[] = "Category \"{$name}\" not found — skipped.";
            }
        }

        $online = match (mb_strtolower($raw['online_only'])) {
            'yes', 'true', '1', 'y', 'نعم' => true,
            'no', 'false', '0', 'n', 'لا' => false,
            default => null,
        };

        return [
            'title' => $title,
            'title_ar' => $raw['title_ar'] !== '' ? $raw['title_ar'] : null,
            'slug' => $raw['slug'] !== '' ? $raw['slug'] : null,
            'contracted_at' => $date,
            'online_only' => $online,
            'sales_id' => $salesId,
            'sales_input' => $raw['sales'],
            'category_ids' => array_values(array_unique($categoryIds)),
            'category_input' => $raw['categories'],
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, string>  $b
     * @param  array<string, mixed>  $lookups
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function parseBranch(array $b, array $lookups): array
    {
        $warnings = [];
        $label = $b['name'] !== '' ? $b['name'] : ($b['address'] ?: 'branch');

        $gov = $b['governorate'] !== '' ? $lookups['governorates']->get(mb_strtolower($b['governorate'])) : null;
        if ($b['governorate'] !== '' && ! $gov) {
            $warnings[] = "Branch \"{$label}\": governorate \"{$b['governorate']}\" not found — left empty.";
        }

        $city = null;
        if ($b['city'] !== '') {
            $needle = mb_strtolower($b['city']);
            $cities = $lookups['cities']->filter(fn (City $c) => in_array($needle, [
                mb_strtolower((string) $c->getTranslation('name', 'en', false)),
                mb_strtolower((string) $c->getTranslation('name', 'ar', false)),
            ], true));
            $city = ($gov ? $cities->firstWhere('governorate_id', $gov->id) : null) ?? $cities->first();
            if (! $city) {
                $warnings[] = "Branch \"{$label}\": city \"{$b['city']}\" not found — left empty.";
            }
        }

        $num = fn (string $v, float $min, float $max) => is_numeric($v) && (float) $v >= $min && (float) $v <= $max ? (float) $v : null;

        return [[
            'name' => $b['name'],
            'name_ar' => $b['name_ar'] !== '' ? $b['name_ar'] : null,
            'address' => $b['address'] !== '' ? $b['address'] : null,
            'address_ar' => $b['address_ar'] !== '' ? $b['address_ar'] : null,
            'phone' => $b['phone'],
            'governorate_id' => $gov?->id,
            'city_id' => $city?->id,
            'latitude' => $num($b['latitude'], -90, 90),
            'longitude' => $num($b['longitude'], -180, 180),
        ], $warnings];
    }

    /**
     * Branch / manager rows filed under the store they name (title or slug, lower-cased).
     *
     * @param  list<array<string, string>>  $rows
     * @return array<string, list<array<string, string>>>
     */
    private function groupByStore(array $rows, string $titleKey, string $slugKey): array
    {
        $out = [];
        foreach ($rows as $row) {
            foreach ([$row[$titleKey] ?? '', $row[$slugKey] ?? ''] as $key) {
                $key = mb_strtolower(trim($key));
                if ($key !== '') {
                    $out[$key][] = $row;
                }
            }
        }

        // A row naming a store by both title and slug is now filed twice; the
        // lookup above takes only one of the two keys, so it is read once.
        return $out;
    }

    /** @return Collection<string, Sales> */
    private function indexSales(): Collection
    {
        $index = collect();
        foreach (Sales::all() as $sale) {
            foreach ([...array_values($sale->nameTranslations()), $sale->displayName()] as $name) {
                $index->put(mb_strtolower(trim((string) $name)), $sale);
            }
        }

        return $index;
    }

    private function indexByName(Collection $models): Collection
    {
        $index = collect();
        foreach ($models as $m) {
            foreach (['en', 'ar'] as $loc) {
                $name = mb_strtolower(trim((string) $m->getTranslation('name', $loc, false)));
                if ($name !== '' && ! $index->has($name)) {
                    $index->put($name, $m);
                }
            }
        }

        return $index;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return list<array{field: string, label: string, old: mixed, new: mixed, changed: bool}>
     */
    private function diff(Store $store, array $parsed): array
    {
        // A blank cell never overwrites, so it is not a change.
        $field = fn (string $key, string $label, mixed $old, mixed $new) => [
            'field' => $key, 'label' => $label, 'old' => $old, 'new' => $new,
            'changed' => $new !== null && $new !== '' && (string) $old !== (string) $new,
        ];

        return [
            $field('title', 'Title', $store->getTranslation('title', 'en', false), $parsed['title']),
            $field('title_ar', 'Title (AR)', $store->getTranslation('title', 'ar', false), $parsed['title_ar']),
            $field('contracted_at', 'Contracted date', $store->contracted_at?->toDateString(), $parsed['contracted_at']),
            $field('online_only', 'Online only', $store->online_only ? 'yes' : 'no', $parsed['online_only'] === null ? null : ($parsed['online_only'] ? 'yes' : 'no')),
            $field('sales', 'Sales rep', $store->sales?->displayName(), $parsed['sales_id'] === null ? null : $parsed['sales_input']),
        ];
    }
}
