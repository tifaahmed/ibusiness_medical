<?php

namespace App\Http\Controllers\Admin\Contract\Actions\Store;

use App\Models\Contract;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreContractAction
{
    /**
     * Execute the action to store a contract.
     *
     * @param array $validated
     * @return Contract
     * @throws \Exception
     */
    public function execute(array $validated): Contract
    {
        DB::beginTransaction();

        try {
            $contract = Contract::create([
                'name' => $validated['name'] ?? null,
                'description' => $validated['description'] ?? null,
                'phones' => $validated['phones'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'sort_order' => $validated['sort_order'] ?? 0,
                'created_by' => Auth::id(),
            ]);

            if (isset($validated['image'])) {
                $contract->addMedia($validated['image'])
                    ->toMediaCollection('image');
            }

            DB::commit();

            Log::info('Contract created successfully', [
                'contract_id' => $contract->id,
                'contract_slug' => $contract->slug,
            ]);

            return $contract;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create contract', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
