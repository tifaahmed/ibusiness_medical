<?php

namespace App\Http\Controllers\Admin\Facility\Delete;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityBranchLog;
use App\Models\FacilityLog;
use App\Models\FacilityManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminFacilityDeleteController extends BaseController
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

    /**
     * Move the facility, its branches and its managers to the trash together.
     *
     * All three are stamped with the exact same `deleted_at` (not each one's
     * own `->delete()` call, whose timestamps could differ by a few
     * microseconds) so that restoring the facility later can restore only the
     * branches/managers that went into the trash with it — not one that an
     * admin had already deleted separately beforehand.
     */
    public function __invoke(Request $request, string $facilitySlug): RedirectResponse
    {
        try {
            $facility = Facility::where('slug', $facilitySlug)->firstOrFail();
            $this->assertOwns($facility);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Facility not found for deletion', [
                'slug' => $facilitySlug,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Facility not found.']);
        } catch (\Exception $e) {
            Log::error('Error fetching facility for deletion', [
                'slug' => $facilitySlug,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'An error occurred while fetching the facility.']);
        }

        // Store data for logging before deletion
        $facilityId = $facility->id;
        $facilitySlugValue = $facility->slug;
        $branches = $facility->branches()->get();
        $managers = $facility->managers()->get();
        $branchesCount = $branches->count();
        $facilitySnapshot = $this->facilitySnapshot($facility);
        $branchSnapshots = $branches->map(fn (FacilityBranch $b) => $this->branchSnapshot($b))->all();
        $managerSnapshots = $managers->map(fn (FacilityManager $m) => $this->managerSnapshot($m))->all();
        $adminId = Auth::id();
        /*
         * A plain string with microseconds, not a Carbon instance: passing a
         * DateTimeInterface as a query binding gets reformatted by
         * Connection::prepareBindings() to the grammar's default format
         * (whole seconds only), which would throw away exactly the precision
         * the migration added the column for. A raw query-builder update()
         * with a pre-formatted string writes it byte for byte instead.
         */
        $deletedAt = now()->format('Y-m-d H:i:s.u');

        try {
            DB::beginTransaction();

            // Log each branch deletion before cascade
            foreach ($branchSnapshots as $snap) {
                FacilityBranchLog::record(
                    facilityBranchId: $snap['branch_id'],
                    facilityId: $facilityId,
                    adminId: $adminId,
                    action: FacilityBranchLog::ACTION_DELETED,
                    oldValues: $snap,
                    newValues: null,
                    request: $request,
                );
                FacilityLog::record(
                    facilityId: $facilityId,
                    adminId: $adminId,
                    action: FacilityLog::ACTION_BRANCH_DELETED,
                    oldValues: $snap,
                    newValues: null,
                    request: $request,
                );
            }

            foreach ($managerSnapshots as $snap) {
                FacilityLog::record(
                    facilityId: $facilityId,
                    adminId: $adminId,
                    action: FacilityLog::ACTION_MANAGER_DELETED,
                    oldValues: $snap,
                    newValues: null,
                    request: $request,
                );
            }

            // Soft-delete the branches and managers with the facility, all
            // sharing the one $deletedAt so a later restore can tell them
            // apart from anything deleted separately, before or since.
            $facility->branches()->update(['deleted_at' => $deletedAt, 'deleted_by' => $adminId]);
            $facility->managers()->update(['deleted_at' => $deletedAt, 'deleted_by' => $adminId]);

            FacilityLog::record(
                facilityId: $facilityId,
                adminId: $adminId,
                action: FacilityLog::ACTION_DELETED,
                oldValues: $facilitySnapshot,
                newValues: null,
                request: $request,
            );

            // A raw update, not $facility->save(): Eloquent would cast this
            // string back into a Carbon instance and reformat it through
            // getDateFormat() on write, throwing away the same precision.
            Facility::where('id', $facilityId)->update(['deleted_at' => $deletedAt, 'deleted_by' => $adminId]);

            DB::commit();

            Log::info('Facility moved to trash by admin', [
                'facility_id' => $facilityId,
                'facility_slug' => $facilitySlugValue,
                'branches_deleted' => $branchesCount,
                'admin_id' => $adminId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.facility.list')
                ->with('success', 'Facility and its branches moved to trash. It can be restored from the trash page.');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete facility', [
                'facility_id' => $facilityId,
                'facility_slug' => $facilitySlugValue,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to delete facility. Please try again.']);
        }
    }

    private function facilitySnapshot(Facility $facility): array
    {
        return [
            'name' => $this->normalizeTranslatable($facility->getRawOriginal('name')),
            'facility_type_id' => $facility->facility_type_id,
            'governorate_id' => $facility->governorate_id,
            'city_id' => $facility->city_id,
            'latitude' => $facility->latitude,
            'longitude' => $facility->longitude,
        ];
    }

    private function branchSnapshot(FacilityBranch $branch): array
    {
        return [
            'branch_id' => $branch->id,
            'facility_id' => $branch->facility_id,
            'name' => $this->normalizeTranslatable($branch->getRawOriginal('name')),
            'address' => $this->normalizeTranslatable($branch->getRawOriginal('address')),
            'phone' => $branch->phone,
            'governorate_id' => $branch->governorate_id,
            'city_id' => $branch->city_id,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
        ];
    }

    private function managerSnapshot(FacilityManager $manager): array
    {
        return [
            'manager_id' => $manager->id,
            'facility_id' => $manager->facility_id,
            'name' => $manager->name,
            'position' => $manager->position,
            'phones' => $manager->phones,
        ];
    }

    private function normalizeTranslatable(mixed $raw): ?array
    {
        if (empty($raw)) {
            return null;
        }
        $decoded = is_array($raw) ? $raw : json_decode($raw, true);
        if (! is_array($decoded)) {
            return null;
        }
        $filtered = array_filter($decoded, fn ($v) => $v !== null && $v !== '');

        return $filtered === [] ? null : $filtered;
    }
}
