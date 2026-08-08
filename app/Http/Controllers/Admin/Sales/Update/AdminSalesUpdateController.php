<?php

namespace App\Http\Controllers\Admin\Sales\Update;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Admin\Sales\Actions\Update\UpdateSalesAction;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Sales\UpdateSalesRequest;
use App\Models\Sales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminSalesUpdateController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_SALES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_SALES; }

    private UpdateSalesAction $updateAction;

    public function __construct(UpdateSalesAction $updateAction)
    {
        $this->updateAction = $updateAction;
    }

    public function __invoke(UpdateSalesRequest $request, Sales $sale): RedirectResponse
    {
        $this->assertOwns($sale);

        $validated = $request->validated();

        try {
            $updatedSale = $this->updateAction->execute($sale, $validated);

            Log::info('Sales updated successfully', [
                'sales_id' => $updatedSale->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.sales.list')
                ->with('success', 'Sales updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update sales', [
                'sales_id' => $sale->id,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to update sales. Please try again.'])
                ->withInput();
        }
    }
}
