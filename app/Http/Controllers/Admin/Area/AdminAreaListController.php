<?php

namespace App\Http\Controllers\Admin\Area;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Area;
use App\Models\City;
use App\Models\Governorate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only list of the areas (admin level 3), filterable by governorate and
 * city. Reference data, behind the same permissions as the governorate list.
 * Plain arrays, not a JsonResource: a single resource passed to Inertia gets
 * wrapped in {data} and the page renders blank.
 */
class AdminAreaListController extends BaseController
{
    public function __invoke(Request $request): Response
    {
        $filters = [
            'search' => (string) $request->input('search', ''),
            'governorate_id' => $request->input('governorate_id'),
            'city_id' => $request->input('city_id'),
        ];

        $areas = Area::query()
            ->with(['governorate:id,slug,name', 'city:id,slug,name'])
            ->select(['id', 'governorate_id', 'city_id', 'name', 'pcode', 'slug'])
            // Whether a border is stored, without shipping the blob itself.
            ->selectRaw('boundary is not null as has_border')
            ->when($filters['governorate_id'], fn ($q, $id) => $q->where('governorate_id', $id))
            ->when($filters['city_id'], fn ($q, $id) => $q->where('city_id', $id))
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $like = '%'.$filters['search'].'%';
                $q->where(fn ($w) => $w->where('name->ar', 'like', $like)->orWhere('pcode', 'like', $like));
            })
            ->orderBy('city_id')
            ->orderBy('id')
            ->paginate(min((int) $request->input('per_page', 15), 100))
            ->withQueryString();

        $areas->getCollection()->transform(fn (Area $area) => [
            'id' => $area->id,
            'pcode' => $area->pcode,
            'name' => $area->getTranslations('name'),
            'has_border' => (bool) $area->has_border,
            'governorate' => ['id' => $area->governorate_id, 'name' => $area->governorate?->getTranslations('name')],
            'city' => ['id' => $area->city_id, 'name' => $area->city?->getTranslations('name')],
        ]);

        return Inertia::render('Admin/Area/List', [
            'areas' => $areas,
            'filters' => $filters,
            'governorates' => Governorate::query()->orderBy('id')->get(['id', 'name'])
                ->map(fn ($g) => ['id' => $g->id, 'name' => $g->getTranslations('name')])->values(),
            // Only the chosen governorate's cities: 397 in one dropdown is unusable.
            'cities' => $filters['governorate_id']
                ? City::query()->where('governorate_id', $filters['governorate_id'])->orderBy('id')->get(['id', 'name'])
                    ->map(fn ($c) => ['id' => $c->id, 'name' => $c->getTranslations('name')])->values()
                : [],
        ]);
    }
}
