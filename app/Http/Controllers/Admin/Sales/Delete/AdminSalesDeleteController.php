<?php

namespace App\Http\Controllers\Admin\Sales\Delete;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Sales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminSalesDeleteController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_SALES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_SALES; }

    public function __invoke(Request $request, Sales $sale): RedirectResponse
    {
        $this->assertOwns($sale);

        $saleId = $sale->id;

        try {
            DB::beginTransaction();

            $sale->delete();

            DB::commit();

            Log::info('Sales deleted successfully', [
                'sales_id' => $saleId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.sales.list')
                ->with('success', 'Sales deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete sales', [
                'sales_id' => $saleId,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to delete sales. Please try again.']);
        }
    }
}
