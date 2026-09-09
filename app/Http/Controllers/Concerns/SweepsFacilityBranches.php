<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\User\UserPermissionEnum;
use App\Models\FacilityBranch;
use App\Models\FacilityBranchLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The parts every AI sweep on the branch list shares.
 *
 * Both sweeps are browser-stepped in the same shape as the facility list's:
 * `begin` hands back the work list, then `step` is called with a small slice
 * until it is done, so no single request has to outlive a shared-hosting
 * timeout and the browser can show progress as it goes.
 *
 * Scoping is on the branch's own creator, not the facility's — these run from
 * the branch list, where "manage own facility branches" means the branches this
 * admin added, whoever owns the facility above them.
 */
trait SweepsFacilityBranches
{
    use CreatorScoped;

    /** Branches handled per step — each one is an AI round trip. */
    private const CHUNK = 3;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_FACILITY_BRANCHES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_FACILITY_BRANCHES;
    }

    /**
     * Branches this admin may write, in the order the list behind the dialog
     * reads.
     */
    protected function branchQuery()
    {
        return FacilityBranch::query()
            ->with(['facility:id,name', 'governorate:id,name', 'city:id,name'])
            ->tap(fn ($q) => $this->applyCreatorScope($q))
            ->orderBy('facility_id')
            ->orderBy('id');
    }

    /**
     * What the branch was given to read: everything the model needs to place it.
     *
     * @return array<string, mixed>
     */
    protected function context(FacilityBranch $branch): array
    {
        return [
            'facility_name' => $branch->facility?->getTranslations('name'),
            'name' => $branch->getTranslations('name'),
            'address' => $branch->getTranslations('address'),
            'governorate' => $this->placeName($branch->governorate),
            'city' => $this->placeName($branch->city),
        ];
    }

    protected function placeName(mixed $place): ?string
    {
        if ($place === null) {
            return null;
        }

        return $place->getTranslation('name', 'en')
            ?: $place->getTranslation('name', 'ar')
            ?: null;
    }

    /**
     * What the dialog shows for a row: the facility, then the branch.
     */
    protected function label(FacilityBranch $branch): string
    {
        $locale = app()->getLocale();

        $facility = $branch->facility?->getTranslation('name', $locale)
            ?: $branch->facility?->getTranslation('name', 'ar')
            ?: '—';

        $name = $branch->getTranslation('name', $locale)
            ?: $branch->getTranslation('name', 'ar')
            ?: $branch->getTranslation('address', $locale)
            ?: '—';

        return trim($facility.' — '.$name);
    }

    /**
     * True when the branch has an address worth sending. Without one there is
     * nothing to read a place or a pin out of, so the row is not queued at all.
     */
    protected function hasAddress(FacilityBranch $branch): bool
    {
        foreach ($branch->getTranslations('address') as $value) {
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Log a sweep's write against the branch the same way an edit from the form
     * would, so the branch's history says where the values came from.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    protected function logChange(FacilityBranch $branch, array $before, array $after, string $source, Request $request): void
    {
        FacilityBranchLog::record(
            facilityBranchId: $branch->id,
            facilityId: $branch->facility_id,
            adminId: Auth::id(),
            action: FacilityBranchLog::ACTION_UPDATED,
            oldValues: $before,
            newValues: [...$after, 'source' => $source],
            request: $request,
        );
    }
}
