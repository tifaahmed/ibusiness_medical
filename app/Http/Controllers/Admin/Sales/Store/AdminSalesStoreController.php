<?php

namespace App\Http\Controllers\Admin\Sales\Store;

use App\Http\Controllers\Admin\Sales\Actions\Store\StoreSalesAction;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Sales\StoreSalesRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminSalesStoreController extends BaseController
{
    private StoreSalesAction $storeAction;

    public function __construct(StoreSalesAction $storeAction)
    {
        $this->storeAction = $storeAction;
    }

    public function __invoke(StoreSalesRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $sales = $this->storeAction->execute($validated);

            Log::info('Sales created successfully', [
                'sales_id' => $sales->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.sales.list')
                ->with('success', 'Sales created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create sales', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to create sales. Please try again.'])
                ->withInput();
        }
    }
}
