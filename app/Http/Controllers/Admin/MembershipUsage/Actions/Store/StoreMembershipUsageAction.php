<?php

namespace App\Http\Controllers\Admin\MembershipUsage\Actions\Store;

use App\Models\MembershipUsage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreMembershipUsageAction
{
    public function execute(array $validated): MembershipUsage
    {
        DB::beginTransaction();

        try {
            $usage = MembershipUsage::create([
                'membership_id'      => $validated['membership_id'],
                'facility_id'        => $validated['facility_id'],
                'facility_branch_id' => $validated['facility_branch_id'] ?? null,
                'facility_type_id'   => $validated['facility_type_id'],
                'amount'             => $validated['amount'],
                'description'        => $validated['description'] ?? null,
            ]);

            if (!empty($validated['gallery'])) {
                foreach ($validated['gallery'] as $file) {
                    $usage->addMedia($file)->toMediaCollection('gallery');
                }
            }

            DB::commit();

            Log::info('MembershipUsage created successfully', ['membership_usage_id' => $usage->id]);

            return $usage;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create MembershipUsage', ['error_message' => $e->getMessage()]);
            throw $e;
        }
    }
}
