<?php

namespace App\Http\Controllers\Admin\Facility\Trash;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Facility\List\AdminFacilityListCollection;
use App\Models\Facility;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The deleted facilities, newest deletion first.
 *
 * A trimmed-down sibling of AdminFacilityListController: same search box and
 * creator scoping, but scoped to `onlyTrashed()` and ordered by when each row
 * was deleted rather than created.
 */
class AdminFacilityTrashController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_FACILITIES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_FACILITIES;
    }

    public function __invoke(Request $request): Response
    {
        $filters = ['search' => $request->input('search', '')];

        $facilities = Facility::onlyTrashed()
            ->with(['facilityType', 'creator:id,name,email', 'deletedBy:id,name,email'])
            ->withCount('branches')
            ->tap(fn ($q) => $this->applyCreatorScope($q))
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $q->where(function ($query) use ($filters) {
                    $query->where('name->'.app()->getLocale(), 'like', '%'.$filters['search'].'%')
                        ->orWhere('slug', 'like', '%'.$filters['search'].'%');
                });
            })
            ->orderByDesc('deleted_at')
            ->paginate($request->input('per_page', 15))->withQueryString();

        return Inertia::render('Admin/Facility/Trash/FacilityTrashView', [
            'facilities' => (new AdminFacilityListCollection($facilities))->toArray($request),
            'filters' => $filters,
        ]);
    }
}
