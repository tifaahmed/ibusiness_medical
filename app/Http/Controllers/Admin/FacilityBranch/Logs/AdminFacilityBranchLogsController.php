<?php

namespace App\Http\Controllers\Admin\FacilityBranch\Logs;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\FacilityBranch;
use App\Models\FacilityBranchLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminFacilityBranchLogsController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_FACILITY_BRANCHES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_FACILITY_BRANCHES; }

    public function __invoke(Request $request, string $facilityBranch): Response
    {
        $facilityBranch = FacilityBranch::where('slug', $facilityBranch)->firstOrFail();
        $this->assertOwns($facilityBranch);

        $filters = [
            'admin_id' => $request->filled('admin_id') ? (int) $request->input('admin_id') : null,
            'action' => $request->filled('action') ? (string) $request->input('action') : null,
        ];

        $logsQuery = FacilityBranchLog::query()
            ->with('admin:id,name,email')
            ->where('facility_branch_id', $facilityBranch->id)
            ->when($filters['admin_id'] !== null, fn ($q) => $q->where('admin_id', $filters['admin_id']))
            ->when($filters['action'] !== null, fn ($q) => $q->where('action', $filters['action']))
            ->latest('created_at');

        $logs = $logsQuery
            ->paginate($request->input('per_page', 20))
            ->withQueryString();

        $logs->getCollection()->transform(function (FacilityBranchLog $log) {
            return [
                'id' => $log->id,
                'action' => $log->action,
                'facility_branch_id' => $log->facility_branch_id,
                'facility_id' => $log->facility_id,
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

        $adminOptions = FacilityBranchLog::query()
            ->where('facility_branch_id', $facilityBranch->id)
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

        return Inertia::render('Admin/FacilityBranch/Logs/FacilityBranchLogsView', [
            'facilityBranch' => [
                'id' => $facilityBranch->id,
                'name' => $facilityBranch->name,
                'slug' => $facilityBranch->slug,
                'facility_id' => $facilityBranch->facility_id,
            ],
            'logs' => $logs->toArray(),
            'filters' => $filters,
            'adminOptions' => $adminOptions,
        ]);
    }
}
