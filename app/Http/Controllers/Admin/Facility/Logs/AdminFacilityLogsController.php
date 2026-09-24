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

    /**
     * What each entity filter means in `action` terms: the facility's own
     * actions, and the branch and manager ones filed on its history.
     */
    private const ENTITY_ACTIONS = [
        'facility' => [
            FacilityLog::ACTION_CREATED, FacilityLog::ACTION_UPDATED, FacilityLog::ACTION_DELETED,
            FacilityLog::ACTION_RESTORED, FacilityLog::ACTION_FORCE_DELETED,
        ],
        'branch' => [
            FacilityLog::ACTION_BRANCH_CREATED, FacilityLog::ACTION_BRANCH_UPDATED,
            FacilityLog::ACTION_BRANCH_DELETED, FacilityLog::ACTION_BRANCH_RESTORED,
        ],
        'manager' => [
            FacilityLog::ACTION_MANAGER_CREATED, FacilityLog::ACTION_MANAGER_UPDATED,
            FacilityLog::ACTION_MANAGER_DELETED, FacilityLog::ACTION_MANAGER_RESTORED,
        ],
    ];

    private function entityOf(string $action): string
    {
        return str_starts_with($action, 'branch_') ? 'branch' : (str_starts_with($action, 'manager_') ? 'manager' : 'facility');
    }

    /** A name as one string — a translation map picks the current locale, then Arabic, then English. */
    private function label(mixed $name): ?string
    {
        if (is_array($name)) {
            $name = $name[app()->getLocale()] ?? $name['ar'] ?? $name['en'] ?? (reset($name) ?: null);
        }

        return is_string($name) && $name !== '' ? $name : null;
    }

    private function date(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_FACILITIES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_FACILITIES;
    }

    public function __invoke(Request $request, string $facility): Response
    {
        $facility = Facility::where('slug', $facility)->firstOrFail();
        $this->assertOwns($facility);

        $filters = [
            'admin_id' => $request->filled('admin_id') ? (int) $request->input('admin_id') : null,
            'action' => $request->filled('action') ? (string) $request->input('action') : null,
            'entity' => in_array($request->input('entity'), array_keys(self::ENTITY_ACTIONS), true) ? (string) $request->input('entity') : null,
            'source' => $request->filled('source') ? (string) $request->input('source') : null,
            'from' => $this->date($request->input('from')),
            'to' => $this->date($request->input('to')),
        ];

        $logsQuery = FacilityLog::query()
            ->with('admin:id,name,email')
            ->where('facility_id', $facility->id)
            ->when($filters['admin_id'] !== null, fn ($q) => $q->where('admin_id', $filters['admin_id']))
            ->when($filters['action'] !== null, fn ($q) => $q->where('action', $filters['action']))
            ->when($filters['entity'] !== null, fn ($q) => $q->whereIn('action', self::ENTITY_ACTIONS[$filters['entity']]))
            // "manual" is the ordinary case — an admin on a form — stored as NULL.
            ->when($filters['source'] === 'manual', fn ($q) => $q->whereNull('source'))
            ->when($filters['source'] !== null && $filters['source'] !== 'manual', fn ($q) => $q->where('source', $filters['source']))
            ->when($filters['from'] !== null, fn ($q) => $q->where('created_at', '>=', $filters['from'].' 00:00:00'))
            ->when($filters['to'] !== null, fn ($q) => $q->where('created_at', '<=', $filters['to'].' 23:59:59'))
            ->latest('created_at')
            ->latest('id');

        $logs = $logsQuery
            ->paginate($request->input('per_page', 20))
            ->withQueryString();

        $logs->getCollection()->transform(function (FacilityLog $log) {
            $entity = $this->entityOf($log->action);
            $values = $log->new_values ?? $log->old_values ?? [];

            return [
                'id' => $log->id,
                'action' => $log->action,
                'entity' => $entity,
                'subject' => $entity === 'facility' ? null : [
                    'id' => $values['branch_id'] ?? $values['manager_id'] ?? null,
                    'label' => $this->label($log->new_values['name'] ?? $log->old_values['name'] ?? null),
                ],
                // Rows written before the column existed carry it in new_values.
                'source' => $log->source ?? ($log->new_values['source'] ?? null),
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
