<?php

namespace App\Http\Controllers\Admin\FacilityType\List;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\FacilityType\List\AdminFacilityTypeListCollection;
use App\Models\FacilityType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminFacilityTypeListController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_FACILITIES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_FACILITIES; }

    /**
     * Display a listing of facility types.
     */
    public function __invoke(Request $request): Response
    {
        $filters = $this->getFilters($request);

        $facilityTypes = FacilityType::query()
            ->with('creator:id,name,email')
            ->withCount('facilities')
            ->tap(fn($q) => $this->applyCreatorScope($q))
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $q->where(function ($query) use ($filters) {
                    $query->where('name->' . app()->getLocale(), 'like', '%' . $filters['search'] . '%')
                          ->orWhere('slug', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->latest()
            ->paginate($request->input('per_page', 15))->withQueryString();

        return Inertia::render('Admin/FacilityType/List', [
            'facilityTypes' => new AdminFacilityTypeListCollection($facilityTypes)->toArray($request),
            'filters' => $filters,
        ]);
    }

    /**
     * Get filters from request.
     */
    protected function getFilters(Request $request): array
    {
        return [
            'search' => $request->input('search', ''),
        ];
    }
}

