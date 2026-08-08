<?php

namespace App\Http\Controllers\Admin\Sales\Actions\Store;

use App\Models\Sales;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreSalesAction
{
    public function execute(array $validated): Sales
    {
        DB::beginTransaction();

        try {
            $sales = Sales::create([
                'name' => $validated['name'],
                'created_by' => Auth::id(),
            ]);

            if (isset($validated['image'])) {
                $sales->addMedia($validated['image'])
                    ->toMediaCollection('image');
            }

            DB::commit();

            Log::info('Sales created successfully', [
                'sales_id' => $sales->id,
            ]);

            return $sales;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create sales', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
