<?php

namespace App\Http\Controllers\Admin\City;

use App\Http\Controllers\Controller as BaseController;
use App\Models\City;
use App\Models\Governorate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The cities, filterable by governorate and name. Plain arrays, not a
 * JsonResource: a single resource passed to Inertia gets wrapped in {data} and
 * the page renders blank. The border blob is never shipped, only whether one is
 * stored.
 */
class AdminCityListController extends BaseController
{
    public function __invoke(Request $request): Response
    {
        $filters = [
            'search' => (string) $request->input('search', ''),
            'governorate_id' => $request->input('governorate_id'),
        ];

        $cities = City::query()
            ->with('governorate:id,slug,name')
            ->select(['id', 'governorate_id', 'slug', 'name'])
            ->selectRaw('boundary is not null as has_border')
            ->withCount(['areas', 'branches', 'facilities'])
            ->when($filters['governorate_id'], fn ($q, $id) => $q->where('governorate_id', $id))
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $like = '%'.$filters['search'].'%';
                $q->where(fn ($w) => $w->where('name->ar', 'like', $like)->orWhere('name->en', 'like', $like));
            })
            ->orderBy('governorate_id')
            ->orderBy('id')
            ->paginate(min((int) $request->input('per_page', 15), 100))
            ->withQueryString();

        $cities->getCollection()->transform(fn (City $city) => [
            'id' => $city->id,
            'name' => $city->getTranslations('name'),
            'is_unmarked' => $city->isUnmarked(),
            'has_border' => (bool) $city->has_border,
            'areas_count' => $city->areas_count,
            'branches_count' => $city->branches_count,
            'facilities_count' => $city->facilities_count,
            'governorate' => ['id' => $city->governorate_id, 'name' => $city->governorate?->getTranslations('name')],
        ]);

        return Inertia::render('Admin/City/List', [
            'cities' => $cities,
            'filters' => $filters,
            'governorates' => $this->governorates(),
            'canManage' => $this->canManage($request),
        ]);
    }

    private function governorates(): array
    {
        return Governorate::query()->orderBy('id')->get(['id', 'name'])
            ->map(fn ($g) => ['id' => $g->id, 'name' => $g->getTranslations('name')])->values()->all();
    }

    private function canManage(Request $request): bool
    {
        return (bool) $request->user()?->can('manage cities');
    }
}
