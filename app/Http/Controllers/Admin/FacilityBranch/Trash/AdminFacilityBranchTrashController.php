<?php

namespace App\Http\Controllers\Admin\FacilityBranch\Trash;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\FacilityBranch\List\AdminFacilityBranchListCollection;
use App\Models\FacilityBranch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The deleted facility branches, newest deletion first — the trimmed-down
 * sibling of AdminFacilityBranchListController, scoped to `onlyTrashed()`.
 */
class AdminFacilityBranchTrashController extends BaseController
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

    public function __invoke(Request $request): Response
    {
        $filters = ['search' => $request->input('search', '')];

        $facilityBranches = FacilityBranch::onlyTrashed()
            ->with(['facility', 'governorate', 'city', 'creator:id,name,email', 'deletedBy:id,name,email'])
            ->tap(fn ($q) => $this->applyCreatorScope($q))
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $q->where(function ($query) use ($filters) {
                    $query->where('name->'.app()->getLocale(), 'like', '%'.$filters['search'].'%')
                        ->orWhere('slug', 'like', '%'.$filters['search'].'%');
                });
            })
            ->orderByDesc('deleted_at')
            ->paginate($request->input('per_page', 15))->withQueryString();

        return Inertia::render('Admin/FacilityBranch/Trash/FacilityBranchTrashView', [
            'facilityBranches' => (new AdminFacilityBranchListCollection($facilityBranches))->toArray($request),
            'filters' => $filters,
        ]);
    }
}
