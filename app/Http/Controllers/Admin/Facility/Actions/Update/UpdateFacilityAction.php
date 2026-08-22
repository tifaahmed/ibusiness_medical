<?php

namespace App\Http\Controllers\Admin\Facility\Actions\Update;

use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityBranchLog;
use App\Models\FacilityLog;
use App\Models\FacilityManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateFacilityAction
{
    /**
     * Execute the action to update a facility.
     *
     * @throws \Exception
     */
    public function execute(Facility $facility, array $validated): Facility
    {
        DB::beginTransaction();

        try {
            $oldSnapshot = $this->facilitySnapshot($facility);

            // Update the facility
            $facility->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'meta_title' => $validated['meta_title'] ?? null,
                'meta_description' => $validated['meta_description'] ?? null,
                'meta_keywords' => $validated['meta_keywords'] ?? null,
                'canonical_url' => $validated['canonical_url'] ?? null,
                'facility_type_id' => $validated['facility_type_id'],
                'sales_id' => $validated['sales_id'] ?? null,
                'discount_percent' => $validated['discount_percent'] ?? null,
                'banner_config' => $validated['banner_config'] ?? null,
            ]);

            $facility->refresh();

            if (array_key_exists('tag_ids', $validated)) {
                $facility->tags()->sync($validated['tag_ids'] ?? []);
            }

            // Handle branches and collect changes for logging
            $branchChanges = ['created' => [], 'updated' => [], 'deleted' => []];
            if (isset($validated['branches'])) {
                $branchChanges = $this->handleBranches($facility, $validated['branches']);
            }

            // Handle managers and collect changes for logging
            $managerChanges = ['created' => [], 'updated' => [], 'deleted' => []];
            if (isset($validated['managers'])) {
                $managerChanges = $this->handleManagers($facility, $validated['managers']);
            }

            if (! empty($validated['logo'])) {
                $facility->clearMediaCollection('logo');
                $facility->addMedia($validated['logo'])->toMediaCollection('logo');
            }
            if (! empty($validated['mobile_logo'])) {
                $facility->clearMediaCollection('mobile_logo');
                $facility->addMedia($validated['mobile_logo'])->toMediaCollection('mobile_logo');
            }
            if (! empty($validated['image'])) {
                $facility->clearMediaCollection('image');
                $facility->addMedia($validated['image'])->toMediaCollection('image');
            }
            if (! empty($validated['mobile_image'])) {
                $facility->clearMediaCollection('mobile_image');
                $facility->addMedia($validated['mobile_image'])->toMediaCollection('mobile_image');
            }
            if (! empty($validated['og_image'])) {
                $facility->clearMediaCollection('og_image');
                $facility->addMedia($validated['og_image'])->toMediaCollection('og_image');
            } elseif (! empty($validated['og_image_delete'])) {
                $facility->clearMediaCollection('og_image');
            }
            if (! empty($validated['gallery_delete'])) {
                foreach ($validated['gallery_delete'] as $mediaId) {
                    $facility->deleteMedia((int) $mediaId);
                }
            }
            if (! empty($validated['gallery'])) {
                foreach ($validated['gallery'] as $file) {
                    $facility->addMedia($file)->toMediaCollection('gallery');
                }
            }

            $adminId = Auth::id();
            $request = request();
            $newSnapshot = $this->facilitySnapshot($facility->fresh());

            FacilityLog::record(
                facilityId: $facility->id,
                adminId: $adminId,
                action: FacilityLog::ACTION_UPDATED,
                oldValues: $oldSnapshot,
                newValues: $newSnapshot,
                request: $request,
            );

            foreach ($branchChanges['created'] as $branch) {
                $snap = $this->branchSnapshot($branch);
                FacilityBranchLog::record(
                    facilityBranchId: $branch->id,
                    facilityId: $facility->id,
                    adminId: $adminId,
                    action: FacilityBranchLog::ACTION_CREATED,
                    oldValues: null,
                    newValues: $snap,
                    request: $request,
                );
                FacilityLog::record(
                    facilityId: $facility->id,
                    adminId: $adminId,
                    action: FacilityLog::ACTION_BRANCH_CREATED,
                    oldValues: null,
                    newValues: $snap,
                    request: $request,
                );
            }

            foreach ($branchChanges['updated'] as $change) {
                FacilityBranchLog::record(
                    facilityBranchId: $change['branch']->id,
                    facilityId: $facility->id,
                    adminId: $adminId,
                    action: FacilityBranchLog::ACTION_UPDATED,
                    oldValues: $change['old'],
                    newValues: $change['new'],
                    request: $request,
                );
                FacilityLog::record(
                    facilityId: $facility->id,
                    adminId: $adminId,
                    action: FacilityLog::ACTION_BRANCH_UPDATED,
                    oldValues: $change['old'],
                    newValues: $change['new'],
                    request: $request,
                );
            }

            foreach ($branchChanges['deleted'] as $snap) {
                FacilityBranchLog::record(
                    facilityBranchId: null,
                    facilityId: $facility->id,
                    adminId: $adminId,
                    action: FacilityBranchLog::ACTION_DELETED,
                    oldValues: $snap,
                    newValues: null,
                    request: $request,
                );
                FacilityLog::record(
                    facilityId: $facility->id,
                    adminId: $adminId,
                    action: FacilityLog::ACTION_BRANCH_DELETED,
                    oldValues: $snap,
                    newValues: null,
                    request: $request,
                );
            }

            DB::commit();

            Log::info('Facility updated successfully', [
                'facility_id' => $facility->id,
                'facility_slug' => $facility->slug,
            ]);

            return $facility;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update facility', [
                'facility_id' => $facility->id,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle facility branches: create, update, and delete.
     * Returns the changes grouped by type for logging.
     */
    private function handleBranches(Facility $facility, array $branchesData): array
    {
        $existingBranchIds = [];
        foreach ($branchesData as $branchData) {
            if (isset($branchData['id']) && $branchData['id']) {
                $existingBranchIds[] = $branchData['id'];
            }
        }

        // Snapshot branches that are about to be deleted
        $deletedSnaps = $facility->branches()
            ->whereNotIn('id', $existingBranchIds)
            ->get()
            ->map(fn (FacilityBranch $b) => $this->branchSnapshot($b))
            ->all();

        $facility->branches()
            ->whereNotIn('id', $existingBranchIds)
            ->delete();

        $created = [];
        $updated = [];

        foreach ($branchesData as $branchData) {
            $phone = $this->normalizePhone($branchData['phone'] ?? null);

            if (isset($branchData['id']) && $branchData['id']) {
                $branch = FacilityBranch::where('id', $branchData['id'])
                    ->where('facility_id', $facility->id)
                    ->first();

                if ($branch) {
                    $old = $this->branchSnapshot($branch);
                    $branch->update([
                        'name' => $branchData['name'] ?? null,
                        'address' => $branchData['address'] ?? null,
                        'phone' => $phone,
                        'governorate_id' => $branchData['governorate_id'] ?? null,
                        'city_id' => $branchData['city_id'] ?? null,
                        'latitude' => $branchData['latitude'] ?? null,
                        'longitude' => $branchData['longitude'] ?? null,
                    ]);
                    $new = $this->branchSnapshot($branch->fresh());

                    if ($old !== $new) {
                        $updated[] = ['branch' => $branch, 'old' => $old, 'new' => $new];
                    }
                }
            } else {
                $created[] = FacilityBranch::create([
                    'facility_id' => $facility->id,
                    'name' => $branchData['name'] ?? null,
                    'address' => $branchData['address'] ?? null,
                    'phone' => $phone,
                    'governorate_id' => $branchData['governorate_id'] ?? null,
                    'city_id' => $branchData['city_id'] ?? null,
                    'latitude' => $branchData['latitude'] ?? null,
                    'longitude' => $branchData['longitude'] ?? null,
                ]);
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'deleted' => $deletedSnaps,
        ];
    }

    private function normalizePhone(mixed $phone): ?array
    {
        if (is_string($phone) && ! empty($phone)) {
            return [$phone];
        }
        if (! is_array($phone) || empty($phone)) {
            return null;
        }
        $phone = array_filter(array_map('trim', $phone), fn ($p) => ! empty($p));

        return ! empty($phone) ? array_values($phone) : null;
    }

    /**
     * Handle facility managers: create, update, and delete.
     * Returns the changes grouped by type for logging.
     */
    private function handleManagers(Facility $facility, array $managersData): array
    {
        $existingManagerIds = [];
        foreach ($managersData as $managerData) {
            if (isset($managerData['id']) && $managerData['id']) {
                $existingManagerIds[] = $managerData['id'];
            }
        }

        // Snapshot managers that are about to be deleted
        $deletedSnaps = $facility->managers()
            ->whereNotIn('id', $existingManagerIds)
            ->get()
            ->map(fn (FacilityManager $m) => $this->managerSnapshot($m))
            ->all();

        $facility->managers()
            ->whereNotIn('id', $existingManagerIds)
            ->delete();

        $created = [];
        $updated = [];

        foreach ($managersData as $managerData) {
            $phones = $this->normalizePhone($managerData['phones'] ?? null);

            if (isset($managerData['id']) && $managerData['id']) {
                $manager = FacilityManager::where('id', $managerData['id'])
                    ->where('facility_id', $facility->id)
                    ->first();

                if ($manager) {
                    $old = $this->managerSnapshot($manager);
                    $manager->update([
                        'name' => $managerData['name'] ?? null,
                        'position' => $managerData['position'] ?? null,
                        'phones' => $phones,
                    ]);
                    $new = $this->managerSnapshot($manager->fresh());

                    if ($old !== $new) {
                        $updated[] = ['manager' => $manager, 'old' => $old, 'new' => $new];
                    }
                }
            } else {
                $created[] = FacilityManager::create([
                    'facility_id' => $facility->id,
                    'name' => $managerData['name'] ?? null,
                    'position' => $managerData['position'] ?? null,
                    'phones' => $phones,
                ]);
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'deleted' => $deletedSnaps,
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

    private function facilitySnapshot(Facility $facility): array
    {
        return [
            'name' => $this->normalizeTranslatable($facility->getRawOriginal('name')),
            'description' => $this->normalizeTranslatable($facility->getRawOriginal('description')),
            'meta_title' => $this->normalizeTranslatable($facility->getRawOriginal('meta_title')),
            'meta_description' => $this->normalizeTranslatable($facility->getRawOriginal('meta_description')),
            'meta_keywords' => $this->normalizeTranslatable($facility->getRawOriginal('meta_keywords')),
            'canonical_url' => $facility->canonical_url,
            'facility_type_id' => $facility->facility_type_id,
            'sales_id' => $facility->sales_id,
            'discount_percent' => $facility->discount_percent,
            'banner_config' => $facility->banner_config,
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
