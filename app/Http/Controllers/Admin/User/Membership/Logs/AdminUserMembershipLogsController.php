<?php

namespace App\Http\Controllers\Admin\User\Membership\Logs;

use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Models\MemberLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserMembershipLogsController extends BaseController
{
    use ScopesByMembershipCreator;

    public function __invoke(Request $request, string $user): Response
    {
        $restricted = $this->isMembershipScopeRestricted();
        $scopeFilter = $this->membershipScopeFilter();

        $user = User::withTrashed()
            ->with(['memberships' => function ($q) use ($restricted, $scopeFilter) {
                $q->withTrashed();
                if ($restricted) {
                    $scopeFilter($q);
                }
            }])
            ->where('slug', $user)
            ->firstOrFail();

        $this->assertCanManageUser($user);

        $membershipIds = $user->memberships->pluck('id')->all();

        $filters = [
            'admin_id' => $request->filled('admin_id') ? (int) $request->input('admin_id') : null,
        ];

        $logsQuery = MemberLog::query()
            ->with('admin:id,name,email')
            ->whereIn('membership_id', $membershipIds)
            ->when($filters['admin_id'] !== null, fn ($q) => $q->where('admin_id', $filters['admin_id']))
            ->latest('created_at');

        $logs = $logsQuery
            ->paginate($request->input('per_page', 20))
            ->withQueryString();

        $logs->getCollection()->transform(function (MemberLog $log) {
            return [
                'id' => $log->id,
                'action' => $log->action,
                'membership_id' => $log->membership_id,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'changed_fields' => $log->changed_fields,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at?->toDateTimeString(),
                'admin' => $log->admin ? [
                    'id' => $log->admin->id,
                    'name' => $log->admin->name,
                    'email' => $log->admin->email,
                ] : null,
            ];
        });

        // Distinct admins who have logged actions for this member's memberships
        $adminOptions = MemberLog::query()
            ->whereIn('membership_id', $membershipIds)
            ->whereNotNull('admin_id')
            ->with('admin:id,name,email')
            ->get()
            ->pluck('admin')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values()
            ->map(fn ($a) => [
                'value' => $a->id,
                'label' => $a->name,
                'email' => $a->email,
            ])
            ->toArray();

        return Inertia::render('Admin/User/Membership/Logs', [
            'member' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'slug' => $user->slug,
            ],
            'logs' => $logs->toArray(),
            'filters' => $filters,
            'adminOptions' => $adminOptions,
        ]);
    }
}
