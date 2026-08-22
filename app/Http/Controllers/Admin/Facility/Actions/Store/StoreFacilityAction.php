<?php

namespace App\Http\Controllers\Admin\Facility\Actions\Store;

use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityBranchLog;
use App\Models\FacilityLog;
use App\Models\FacilityManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreFacilityAction
{
    /**
     * Execute the action to store a facility.
     *
     * @throws \Exception
     */
    public function execute(array $validated): Facility
    {
        DB::beginTransaction();

        try {
            // Create the facility
            $facility = Facility::create([
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
                'created_by' => Auth::id(),
            ]);

            // Handle branches
            $createdBranches = [];
            if (isset($validated['branches'])) {
                $createdBranches = $this->handleBranches($facility, $validated['branches']);
            }

            // Handle managers
            $createdManagers = [];
            if (isset($validated['managers'])) {
                $createdManagers = $this->handleManagers($facility, $validated['managers']);
            }

            $facility->refresh();

            if (array_key_exists('tag_ids', $validated)) {
                $facility->tags()->sync($validated['tag_ids'] ?? []);
            }

            if (! empty($validated['logo'])) {
                $facility->addMedia($validated['logo'])->toMediaCollection('logo');
            }
            if (! empty($validated['mobile_logo'])) {
                $facility->addMedia($validated['mobile_logo'])->toMediaCollection('mobile_logo');
            }
            if (! empty($validated['image'])) {
                $facility->addMedia($validated['image'])->toMediaCollection('image');
            }
            if (! empty($validated['og_image'])) {
                $facility->addMedia($validated['og_image'])->toMediaCollection('og_image');
            }
            if (! empty($validated['mobile_image'])) {
                $facility->addMedia($validated['mobile_image'])->toMediaCollection('mobile_image');
            }
            if (! empty($validated['gallery'])) {
                foreach ($validated['gallery'] as $file) {
                    $facility->addMedia($file)->toMediaCollection('gallery');
                }
            }
            if (! empty($validated['contract'])) {
                $facility->addMedia($validated['contract'])->toMediaCollection('contract');
            }

            $adminId = Auth::id();
            $request = request();

            FacilityLog::record(
                facilityId: $facility->id,
                adminId: $adminId,
                action: FacilityLog::ACTION_CREATED,
                oldValues: null,
                newValues: $this->facilitySnapshot($facility),
                request: $request,
            );

            foreach ($createdBranches as $branch) {
                $branchSnapshot = $this->branchSnapshot($branch);
                FacilityBranchLog::record(
                    facilityBranchId: $branch->id,
                    facilityId: $facility->id,
                    adminId: $adminId,
                    action: FacilityBranchLog::ACTION_CREATED,
                    oldValues: null,
                    newValues: $branchSnapshot,
                    request: $request,
                );
                FacilityLog::record(
                    facilityId: $facility->id,
                    adminId: $adminId,
                    action: FacilityLog::ACTION_BRANCH_CREATED,
                    oldValues: null,
                    newValues: $branchSnapshot,
                    request: $request,
                );
            }

            DB::commit();

            Log::info('Facility created successfully', [
                'facility_id' => $facility->id,
                'facility_slug' => $facility->slug,
            ]);

            return $facility;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create facility', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle facility branches creation.
     *
     * @return FacilityBranch[]
     */
    private function handleBranches(Facility $facility, array $branchesData): array
    {
        $created = [];
        foreach ($branchesData as $branchData) {
            $phone = $this->normalizePhone($branchData['phone'] ?? null);

            $created[] = FacilityBranch::create([
                'facility_id' => $facility->id,
                'name' => $branchData['name'] ?? null,
                'address' => $branchData['address'] ?? null,
                'phone' => $phone,
                'governorate_id' => $branchData['governorate_id'] ?? null,
                'city_id' => $branchData['city_id'] ?? null,
                'latitude' => $branchData['latitude'] ?? null,
                'longitude' => $branchData['longitude'] ?? null,
                'created_by' => Auth::id(),
            ]);
        }

        return $created;
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
     * Handle facility managers creation.
     *
     * @return FacilityManager[]
     */
    private function handleManagers(Facility $facility, array $managersData): array
    {
        $created = [];
        foreach ($managersData as $managerData) {
            $phones = $this->normalizePhone($managerData['phones'] ?? null);

            $created[] = FacilityManager::create([
                'facility_id' => $facility->id,
                'name' => $managerData['name'] ?? null,
                'position' => $managerData['position'] ?? null,
                'phones' => $phones,
                'created_by' => Auth::id(),
            ]);
        }

        return $created;
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
