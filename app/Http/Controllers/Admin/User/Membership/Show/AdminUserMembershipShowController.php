<?php

namespace App\Http\Controllers\Admin\User\Membership\Show;

use App\Http\Controllers\Concerns\ProvidesCardTemplates;
use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\User\Membership\Show\AdminUserMembershipShowResource;
use App\Models\User;
use App\Support\AdminMemberLoyalty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserMembershipShowController extends BaseController
{
    use ProvidesCardTemplates;
    use ScopesByMembershipCreator;

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
                'subscriptions' => fn ($sq) => $sq->with(['confirmer:id,name', 'media'])->latest('id'),
            ]);
            $query->orderBy('created_at', 'desc');
        }];

        // Relatives are member rows hanging on the primary card
        if (Schema::hasColumn('memberships', 'member_type')) {
            $withRelations['memberships.relatives'] = function ($query) {
                $query->with('user')->orderBy('created_at', 'asc');
            };
        }

        // Only eager load addresses if the table exists
        if (Schema::hasTable('addresses')) {
            $withRelations['memberships.addresses'] = function ($query) {
                $query->with(['governorate', 'city'])->orderBy('created_at', 'asc');
            };
        }

        $user = User::with($withRelations)->where('slug', $user)->firstOrFail();
        $this->assertCanManageUser($user);
        $result = [
            'user' => (new AdminUserMembershipShowResource($user))->toArray($request),
            'cardTemplates' => $this->cardTemplatesByStatus(),
            // Read-only loyalty and wallet picture (points, plays, conversions, transfers, relatives).
            'loyalty' => AdminMemberLoyalty::summary($user),
        ];

        return Inertia::render('Admin/User/Membership/Show', $result);
    }
}
