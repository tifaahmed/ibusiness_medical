<?php

namespace App\Http\Controllers\Admin\Facility\List;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Facility\List\AdminFacilityListCollection;
use App\Models\Facility;
use App\Models\FacilityType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminFacilityListController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_FACILITIES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_FACILITIES; }

    /**
     * Display a listing of facilities.
     */
    public function __invoke(Request $request): Response
    {
        $filters = $this->getFilters($request);

        $facilities = Facility::with([
                'facilityType',
                'media',
                'creator:id,name,email',
                'branches:id,facility_id,governorate_id,city_id,name,address,phone,latitude,longitude',
                'branches.governorate:id,name',
                'branches.city:id,name',
                'tags',
            ])
            ->withCount('branches')
            ->tap(fn($q) => $this->applyCreatorScope($q))
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $q->where(function ($query) use ($filters) {
                    $query->where('name->' . app()->getLocale(), 'like', '%' . $filters['search'] . '%')
                          ->orWhere('slug', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->when(isset($filters['facility_type_id']) && $filters['facility_type_id'] !== '' && $filters['facility_type_id'] !== null, function ($q) use ($filters) {
                $q->where('facility_type_id', (int) $filters['facility_type_id']);
            })
            ->latest()
            ->paginate($request->input('per_page', 15))->withQueryString();

        $facilityTypes = FacilityType::all()->map(function ($type) {
            return [
                'id' => $type->id,
                'name' => $type->name,
            ];
        });

        return Inertia::render('Admin/Facility/List', [
            'facilities' => new AdminFacilityListCollection($facilities)->toArray($request),
            'filters' => $filters,
            'facilityTypes' => $facilityTypes,
        ]);
    }

    /**
     * Get filters from request.
     */
    protected function getFilters(Request $request): array
    {
        return [
            'search' => $request->input('search', ''),
            'facility_type_id' => $request->input('facility_type_id'),
        ];
    }
}

