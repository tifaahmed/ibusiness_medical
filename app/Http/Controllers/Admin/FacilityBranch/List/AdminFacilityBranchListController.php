<?php

namespace App\Http\Controllers\Admin\FacilityBranch\List;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\FacilityBranch\List\AdminFacilityBranchListCollection;
use App\Models\Facility;
use App\Models\FacilityBranch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminFacilityBranchListController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_FACILITY_BRANCHES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_FACILITY_BRANCHES;
    }

    /**
     * Display a listing of facility branches.
     */
    public function __invoke(Request $request): Response
    {
        $filters = $this->getFilters($request);

        $facilityBranches = FacilityBranch::with(['facility.facilityType', 'governorate', 'city', 'creator:id,name,email'])
            ->tap(fn ($q) => $this->applyCreatorScope($q))
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $q->where(function ($query) use ($filters) {
                    $query->where('name->'.app()->getLocale(), 'like', '%'.$filters['search'].'%')
                        ->orWhere('slug', 'like', '%'.$filters['search'].'%')
                        ->orWhere('phone', 'like', '%'.$filters['search'].'%')
                        ->orWhereHas('facility', function ($q) use ($filters) {
                            $q->where('name->'.app()->getLocale(), 'like', '%'.$filters['search'].'%');
                        });
                });
            })
            ->when(! empty($filters['facility_id']), function ($q) use ($filters) {
                $q->where('facility_id', $filters['facility_id']);
            })
            // The rows nobody can place on a map, and the ones a migration
            // package cannot be imported over until somebody fills them in.
            ->when($filters['no_governorate'], fn ($q) => $q->whereNull('governorate_id'))
            ->when($filters['no_city'], fn ($q) => $q->whereNull('city_id'))
            ->latest()
            ->paginate($request->input('per_page', 15))->withQueryString();

        $facilities = Facility::all()->map(function ($facility) {
            return [
                'id' => $facility->id,
                'name' => $facility->name,
            ];
        });

        // What the two "missing" filters would find, counted over everything the
        // reader is allowed to see rather than the page in front of them — the
        // number is the size of the job, not of this screen.
        $incomplete = FacilityBranch::query()
            ->tap(fn ($q) => $this->applyCreatorScope($q))
            ->selectRaw('SUM(governorate_id IS NULL) AS no_governorate, SUM(city_id IS NULL) AS no_city')
            ->first();

        return Inertia::render('Admin/FacilityBranch/List', [
            'facilityBranches' => new AdminFacilityBranchListCollection($facilityBranches)->toArray($request),
            'filters' => $filters,
            'facilities' => $facilities,
            'incompleteCounts' => [
                'no_governorate' => (int) ($incomplete->no_governorate ?? 0),
                'no_city' => (int) ($incomplete->no_city ?? 0),
            ],
        ]);
    }

    /**
     * Get filters from request.
     */
    protected function getFilters(Request $request): array
    {
        return [
            'search' => $request->input('search', ''),
            'facility_id' => $request->input('facility_id'),
            'no_governorate' => $request->boolean('no_governorate'),
            'no_city' => $request->boolean('no_city'),
        ];
    }
}
