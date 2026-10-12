<?php

namespace App\Http\Controllers\Admin\Facility\ManagedBy;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Facility;
use App\Models\FacilityLog;
use App\Models\FacilityType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The bulk screen for `facilities.managed_by`: filter the facilities, tick
 * some (or take everything the filter matches), point them at one admin user.
 *
 * A facility has exactly one `managed_by`, so assigning is an overwrite. That
 * is why `preview` exists: it says, before anything is written, how many rows
 * are empty, how many already have this user, and — the dangerous case — how
 * many currently belong to somebody ELSE and who. `assign` re-derives the
 * same set itself and trusts nothing the preview said.
 */
class AdminFacilityManagedByController extends BaseController
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

    private function filters(Request $request): array
    {
        $managed = $request->input('managed');

        return [
            'search' => trim((string) $request->input('search', '')),
            'facility_type_id' => $request->filled('facility_type_id') ? (int) $request->input('facility_type_id') : null,
            'managed' => in_array($managed, ['with', 'without'], true) ? $managed : null,
            'managed_by' => $request->filled('managed_by') ? (int) $request->input('managed_by') : null,
        ];
    }

    private function filtered(array $f): Builder
    {
        return Facility::query()
            ->tap(fn ($q) => $this->applyCreatorScope($q))
            ->when($f['search'] !== '', function ($q) use ($f) {
                $q->where(function ($query) use ($f) {
                    $query->where('name->ar', 'like', '%'.$f['search'].'%')
                        ->orWhere('name->en', 'like', '%'.$f['search'].'%')
                        ->orWhere('slug', 'like', '%'.$f['search'].'%');
                });
            })
            ->when($f['facility_type_id'] !== null, fn ($q) => $q->where('facility_type_id', $f['facility_type_id']))
            ->when($f['managed'] === 'with', fn ($q) => $q->whereNotNull('managed_by'))
            ->when($f['managed'] === 'without', fn ($q) => $q->whereNull('managed_by'))
            ->when($f['managed_by'] !== null, fn ($q) => $q->where('managed_by', $f['managed_by']));
    }

    private function label(mixed $name): string
    {
        if (is_array($name)) {
            $name = $name[app()->getLocale()] ?? $name['ar'] ?? $name['en'] ?? (reset($name) ?: '');
        }

        return (string) $name;
    }

    /**
     * The facilities an action applies to: the ticked ids, or — with
     * `all_matching` — everything the filter matches, across every page.
     */
    private function target(Request $request): Builder
    {
        if ($request->boolean('all_matching')) {
            return $this->filtered($this->filters($request));
        }

        $ids = collect($request->input('facility_ids', []))->map(fn ($id) => (int) $id)->filter()->unique()->values();

        return $this->filtered(['search' => '', 'facility_type_id' => null, 'managed' => null, 'managed_by' => null])
            ->whereIn('id', $ids);
    }

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $perPage = max(10, min((int) $request->input('per_page', 25), 100));

        $facilities = $this->filtered($filters)
            ->with(['facilityType:id,name', 'manager:id,name,email'])
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        $rows = $facilities->getCollection()->map(fn (Facility $f) => [
            'id' => $f->id,
            'slug' => $f->slug,
            'name' => $this->label($f->getTranslations('name')),
            'type' => $f->facilityType ? $this->label($f->facilityType->getTranslations('name')) : null,
            'managed_by' => $f->manager ? [
                'id' => $f->manager->id,
                'name' => $f->manager->name,
                'email' => $f->manager->email,
            ] : null,
        ])->values();

        return Inertia::render('Admin/Facility/ManagedBy/FacilityManagedByView', [
            'facilities' => [
                'data' => $rows,
                'meta' => [
                    'current_page' => $facilities->currentPage(),
                    'last_page' => $facilities->lastPage(),
                    'per_page' => $facilities->perPage(),
                    'total' => $facilities->total(),
                    'from' => $facilities->firstItem(),
                    'to' => $facilities->lastItem(),
                    'links' => $facilities->linkCollection()->toArray(),
                ],
            ],
            'filters' => $filters + ['per_page' => $perPage],
            'facilityTypes' => FacilityType::all()->map(fn ($t) => [
                'value' => $t->id,
                'label' => $this->label($t->getTranslations('name')),
            ])->values(),
            'managerOptions' => Facility::managerOptions(),
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'managed_by' => ['nullable', 'integer', 'exists:users,id'],
            'all_matching' => ['sometimes', 'boolean'],
            'facility_ids' => ['required_without:all_matching', 'array'],
            'facility_ids.*' => ['integer'],
        ]);

        $targetId = $request->filled('target') ? (int) $request->input('target') : null;

        $rows = $this->target($request)->get(['id', 'managed_by']);
        $total = $rows->count();

        $empty = $rows->whereNull('managed_by')->count();
        $same = $targetId === null ? 0 : $rows->where('managed_by', $targetId)->count();

        // Facilities that belong to somebody else right now — the ones this
        // would take away from them — grouped by who loses them.
        $others = $rows->whereNotNull('managed_by')
            ->filter(fn ($r) => $r->managed_by !== $targetId)
            ->groupBy('managed_by');

        $names = User::whereIn('id', $others->keys())->pluck('name', 'id');

        $taken = $others->map(fn ($group, $userId) => [
            'user_id' => (int) $userId,
            'name' => $names[$userId] ?? "#{$userId}",
            'count' => $group->count(),
        ])->sortByDesc('count')->values();

        return response()->json([
            'total' => $total,
            'empty' => $empty,
            'same' => $same,
            'will_change' => $total - $same,
            'taken_from' => $taken,
            'target' => $targetId === null ? null : (User::find($targetId)?->name),
        ]);
    }

    public function assign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Empty = clear the assignment.
            'target' => ['nullable', 'integer', 'exists:users,id'],
            'all_matching' => ['sometimes', 'boolean'],
            'facility_ids' => ['required_without:all_matching', 'array'],
            'facility_ids.*' => ['integer'],
        ]);

        $targetId = $validated['target'] ?? null;
        $adminId = Auth::id();

        try {
            $changed = 0;

            DB::transaction(function () use ($request, $targetId, $adminId, &$changed) {
                // Only rows that would actually move: an untouched facility
                // gets no write and no log line.
                $this->target($request)
                    ->where(function ($q) use ($targetId) {
                        $targetId === null
                            ? $q->whereNotNull('managed_by')
                            : $q->whereNull('managed_by')->orWhere('managed_by', '!=', $targetId);
                    })
                    ->select(['id', 'managed_by'])
                    ->lockForUpdate()
                    ->get()
                    ->each(function (Facility $facility) use ($targetId, $adminId, $request, &$changed) {
                        $old = $facility->managed_by;

                        Facility::whereKey($facility->id)->update(['managed_by' => $targetId]);

                        FacilityLog::record(
                            facilityId: $facility->id,
                            adminId: $adminId,
                            action: FacilityLog::ACTION_UPDATED,
                            oldValues: ['managed_by' => $old],
                            newValues: ['managed_by' => $targetId],
                            request: $request,
                            source: 'managed_by_bulk',
                        );

                        $changed++;
                    });
            });
        } catch (\Throwable $e) {
            Log::error('Bulk managed_by assignment failed', [
                'route' => $request->path(),
                'target' => $targetId,
                'all_matching' => $request->boolean('all_matching'),
                'ids' => count($request->input('facility_ids', [])),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'Could not save the assignment. Nothing was changed.'], 500);
        }

        return response()->json(['changed' => $changed]);
    }
}
