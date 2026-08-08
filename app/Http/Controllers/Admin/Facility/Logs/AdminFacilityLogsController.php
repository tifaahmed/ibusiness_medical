<?php

namespace App\Http\Controllers\Admin\Facility\Logs;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Facility;
use App\Models\FacilityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminFacilityLogsController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_FACILITIES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_FACILITIES; }

    public function __invoke(Request $request, string $facility): Response
    {
        $facility = Facility::where('slug', $facility)->firstOrFail();
        $this->assertOwns($facility);

        $filters = [
            'admin_id' => $request->filled('admin_id') ? (int) $request->input('admin_id') : null,
            'action' => $request->filled('action') ? (string) $request->input('action') : null,
        ];

        $logsQuery = FacilityLog::query()
            ->with('admin:id,name,email')
            ->where('facility_id', $facility->id)
            ->when($filters['admin_id'] !== null, fn ($q) => $q->where('admin_id', $filters['admin_id']))
            ->when($filters['action'] !== null, fn ($q) => $q->where('action', $filters['action']))
            ->latest('created_at');

        $logs = $logsQuery
            ->paginate($request->input('per_page', 20))
            ->withQueryString();

        $logs->getCollection()->transform(function (FacilityLog $log) {
            return [
                'id' => $log->id,
                'action' => $log->action,
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

        $adminOptions = FacilityLog::query()
            ->where('facility_id', $facility->id)
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

        return Inertia::render('Admin/Facility/Logs/FacilityLogsView', [
            'facility' => [
                'id' => $facility->id,
                'name' => $facility->name,
                'slug' => $facility->slug,
            ],
            'logs' => $logs->toArray(),
            'filters' => $filters,
            'adminOptions' => $adminOptions,
        ]);
    }
}
