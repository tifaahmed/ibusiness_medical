<?php

namespace App\Http\Controllers\Admin\Facility\Migration;

use App\Http\Controllers\Admin\Facility\Migration\Concerns\LookupOptions;
use App\Http\Controllers\Controller as BaseController;
use App\Models\City;
use App\Models\Governorate;
use App\Models\Sales;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Create the governorate, city or sales rep a package named while the preview
 * is open.
 *
 * The import refuses to run while a branch points at a place this site does not
 * have, so the operator needs a way out that does not mean leaving the preview,
 * making the row on the governorates screen and starting the upload again. This
 * makes exactly one row and hands back the dropdown option for it, which the
 * screen selects on every row that was waiting for it.
 */
class AdminFacilityMigrationLookupController extends BaseController
{
    use LookupOptions;

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:governorate,city,sales'],
            'name_en' => ['nullable', 'string', 'max:255', 'required_without:name_ar'],
            'name_ar' => ['nullable', 'string', 'max:255', 'required_without:name_en'],
            'governorate_id' => ['required_if:type,city', 'nullable', 'integer', 'exists:governorates,id'],
        ]);

        $en = trim((string) ($validated['name_en'] ?? ''));
        $ar = trim((string) ($validated['name_ar'] ?? ''));
        if ($en === '' && $ar === '') {
            return response()->json(['message' => 'Give it a name first.'], 422);
        }

        // A name carried in one language only is stored under both: the lookup
        // matching reads either side, and a blank one would match nothing.
        $name = ['en' => $en ?: $ar, 'ar' => $ar ?: $en];
        $governorateId = $validated['type'] === 'city' ? (int) $validated['governorate_id'] : null;

        // A sales rep is neither slugged nor placed, so it takes the short path.
        if ($validated['type'] === 'sales') {
            return $this->storeSales($name, $request->user()?->id);
        }

        // Two branches naming the same missing city both reach for the button;
        // the second one adopts what the first made rather than doubling it.
        $existing = $this->findByName($validated['type'], $name, $governorateId);
        if ($existing) {
            return response()->json([
                'option' => $this->optionFor($existing),
                'created' => false,
            ]);
        }

        $model = $validated['type'] === 'city'
            ? City::create(['governorate_id' => $governorateId, 'name' => $name])
            : Governorate::create(['name' => $name, 'created_by' => $request->user()?->id]);

        $this->settleSlug($model, $name['en']);

        return response()->json([
            'option' => $this->optionFor($model->refresh()),
            'created' => true,
        ], 201);
    }

    /**
     * Make (or adopt) the sales rep the package named.
     *
     * @param  array<string, string>  $name
     */
    private function storeSales(array $name, ?int $userId): JsonResponse
    {
        $existing = $this->findSalesByName($name);
        if ($existing) {
            return response()->json([
                'option' => $this->salesOption($existing),
                'created' => false,
            ]);
        }

        // The column is a plain varchar the model treats as translatable, so an
        // array here is stored as the same {"en": …, "ar": …} blob the rest of
        // the table holds — a bare string would read back as no translation at
        // all on the screens that ask for one locale.
        $sales = Sales::create(['name' => $name, 'created_by' => $userId]);

        return response()->json([
            'option' => $this->salesOption($sales->refresh()),
            'created' => true,
        ], 201);
    }

    /**
     * Sales names are held two ways — a translation blob, or the bare name an
     * older import wrote — so the comparison happens in PHP over the whole
     * (small) table rather than in a JSON path that one of the two shapes would
     * throw on.
     *
     * @param  array<string, string>  $name
     */
    private function findSalesByName(array $name): ?Sales
    {
        $want = array_filter([Str::lower($name['en']), Str::lower($name['ar'])]);

        return Sales::all()->first(function (Sales $sale) use ($want) {
            $have = array_map(
                fn ($value) => Str::lower(trim((string) $value)),
                array_values($this->salesTranslations($sale))
            );

            return array_intersect($have, $want) !== [];
        });
    }

    /**
     * The city picker narrows by governorate, so a city option has to say which
     * one it belongs to.
     *
     * @return array<string, mixed>
     */
    private function optionFor(Model $model): array
    {
        $option = $this->option($model);

        return $model instanceof City
            ? $option + ['governorate_id' => $model->governorate_id]
            : $option;
    }

    /**
     * @param  array<string, string>  $name
     */
    private function findByName(string $type, array $name, ?int $governorateId): ?Model
    {
        $query = $type === 'city'
            ? City::query()->where('governorate_id', $governorateId)
            : Governorate::query();

        return $query
            ->where(function (Builder $q) use ($name) {
                // The locale sits in a JSON path, which cannot be bound — both
                // are literals here, and the value stays a parameter.
                foreach (['en', 'ar'] as $locale) {
                    $q->orWhereRaw(
                        "LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, '$.\"{$locale}\"'))) = ?",
                        [Str::lower($name[$locale])]
                    );
                }
            })
            ->first();
    }

    /**
     * Both tables carry a unique slug generated from the English name, and a
     * name written only in Arabic transliterates to nothing — so the row gets a
     * slug keyed to its id rather than an empty string that the next such row
     * would collide with.
     */
    private function settleSlug(Model $model, string $englishName): void
    {
        $base = Str::slug($englishName);
        $slug = $base !== '' ? $base : $model->getTable().'-'.$model->getKey();

        $taken = $model->newQuery()
            ->where('slug', $slug)
            ->whereKeyNot($model->getKey())
            ->exists();

        if ($taken) {
            $slug .= '-'.$model->getKey();
        }

        if ($model->slug !== $slug) {
            $model->newQuery()->whereKey($model->getKey())->update(['slug' => $slug]);
        }
    }
}
