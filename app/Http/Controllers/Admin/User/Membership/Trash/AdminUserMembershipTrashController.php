<?php

namespace App\Http\Controllers\Admin\User\Membership\Trash;

use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\User\Membership\List\AdminUserMembershipListCollection;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserMembershipTrashController extends BaseController
{
    use ScopesByMembershipCreator;

    /**
     * Display a listing of trashed users with their memberships.
     */
    public function __invoke(Request $request): Response
    {
        $filters = $this->getFilters($request);
        $isActiveFilter = $filters['is_active'];
        $loadActive = $isActiveFilter === null ? true : (bool) $isActiveFilter;

        $sort = in_array($request->input('sort'), ['name', 'created_at', 'updated_at'], true) ? $request->input('sort') : null;
        $direction = strtolower($request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        $restricted = $this->isMembershipScopeRestricted();
        $scopeFilter = $this->membershipScopeFilter();

        // own/partner admins only see trashed users whose memberships fall
        // within their union scope. Full-access admins (manage memberships)
        // see everything as before.
        $trashScope = function ($q) use ($scopeFilter, $restricted) {
            $q->withTrashed();
            if ($restricted) {
                $scopeFilter($q);
            }
        };

        $users = User::onlyTrashed()
            ->whereHas('memberships', $trashScope)
            ->when(!empty($filters['search']), fn($q) => $q->search($filters['search']))
            ->when($filters['is_active'] === true, fn($q) => $q->whereHas('memberships', function ($mq) use ($trashScope) {
                $trashScope($mq);
                $mq->where('is_active', true);
            }))
            ->when($filters['is_active'] === false, fn($q) => $q->whereHas('memberships', function ($mq) use ($trashScope) {
                $trashScope($mq);
                $mq->where('is_active', false);
            })->whereDoesntHave('memberships', function ($mq) use ($trashScope) {
                $trashScope($mq);
                $mq->where('is_active', true);
            }))
            ->with(['memberships' => function ($q) use ($loadActive, $scopeFilter, $restricted) {
                $q->withTrashed();
                if ($restricted) {
                    $scopeFilter($q);
                }
                $loadActive ? $q->active() : $q->inactive();
                $q->ordered();
            }])
            ->when($sort !== null, fn($q) => $q->orderBy($sort, $direction), fn($q) => $q->latest('deleted_at'))
            ->paginate($request->input('per_page', 15))->withQueryString();

        return Inertia::render('Admin/User/Membership/Trash', [
            'users' => new AdminUserMembershipListCollection($users)->toArray($request),
            'filters' => $filters,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    /**
     * Get filters from request.
     */
    protected function getFilters(Request $request): array
    {
        return [
            'search' => $request->input('search', ''),
            'is_active' => $request->has('is_active') ? (bool) $request->input('is_active') : null,
        ];
    }
}
