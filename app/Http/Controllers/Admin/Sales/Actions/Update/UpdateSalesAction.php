<?php

namespace App\Http\Controllers\Admin\Sales\Actions\Update;

use App\Models\Sales;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateSalesAction
{
    public function execute(Sales $sales, array $validated): Sales
    {
        DB::beginTransaction();

        try {
            $sales->update([
                'name' => $validated['name'],
            ]);

            if (isset($validated['image'])) {
                $sales->clearMediaCollection('image');
                $sales->addMedia($validated['image'])
                    ->toMediaCollection('image');
            }

            $sales->refresh();

            DB::commit();

            Log::info('Sales updated successfully', [
                'sales_id' => $sales->id,
            ]);

            return $sales;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update sales', [
                'sales_id' => $sales->id,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
