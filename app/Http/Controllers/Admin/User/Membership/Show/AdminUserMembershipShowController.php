<?php

namespace App\Http\Controllers\Admin\User\Membership\Show;

use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Concerns\ProvidesCardTemplates;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\User\Membership\Show\AdminUserMembershipShowResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserMembershipShowController extends BaseController
{
    use ScopesByMembershipCreator;
    use ProvidesCardTemplates;

    /**
     * Display the specified user and membership.
     */
    public function __invoke(Request $request, string $user): Response
    {
        $restricted = $this->isMembershipScopeRestricted();
        $scopeFilter = $this->membershipScopeFilter();

        // Fetch user with all memberships and their family members in a single database query
        $withRelations = ['memberships' => function ($query) use ($restricted, $scopeFilter) {
            if ($restricted) {
                $scopeFilter($query);
            }
            $query->with([
                'creator:id,name,email',
                'company',
                'partner',
                'sales',
                'governorate',
                'city',
                'cardLayouts.cardTemplate',
                'memberPayments',
            ]);
            $query->orderBy('created_at', 'desc');
        }];

        // Only eager load familyMembers if the table exists
        if (Schema::hasTable('family_members')) {
            $withRelations['memberships.familyMembers'] = function ($query) {
                $query->orderBy('created_at', 'asc');
            };
        }

        $user = User::with($withRelations)->where('slug', $user)->firstOrFail();
        $this->assertCanManageUser($user);
        $result = [
            'user' => (new AdminUserMembershipShowResource($user))->toArray($request),
            'cardTemplates' => $this->cardTemplatesByStatus(),
        ];

        return Inertia::render('Admin/User/Membership/Show', $result);
    }
}
