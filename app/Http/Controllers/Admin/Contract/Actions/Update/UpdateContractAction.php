<?php

namespace App\Http\Controllers\Admin\Contract\Actions\Update;

use App\Models\Contract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateContractAction
{
    /**
     * Execute the action to update a contract.
     *
     * @param Contract $contract
     * @param array $validated
     * @return Contract
     * @throws \Exception
     */
    public function execute(Contract $contract, array $validated): Contract
    {
        DB::beginTransaction();

        try {
            $contract->update([
                'name' => $validated['name'] ?? null,
                'description' => $validated['description'] ?? null,
                'phones' => $validated['phones'] ?? $contract->phones,
                'is_active' => $validated['is_active'] ?? $contract->is_active,
                'sort_order' => $validated['sort_order'] ?? $contract->sort_order,
            ]);

            if (isset($validated['image'])) {
                $contract->clearMediaCollection('image');
                $contract->addMedia($validated['image'])
                    ->toMediaCollection('image');
            }

            $contract->refresh();

            DB::commit();

            Log::info('Contract updated successfully', [
                'contract_id' => $contract->id,
                'contract_slug' => $contract->slug,
            ]);

            return $contract;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update contract', [
                'contract_id' => $contract->id,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
