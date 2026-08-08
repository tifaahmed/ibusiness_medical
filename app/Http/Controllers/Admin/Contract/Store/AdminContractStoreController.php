<?php

namespace App\Http\Controllers\Admin\Contract\Store;

use App\Http\Controllers\Admin\Contract\Actions\Store\StoreContractAction;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Contract\StoreContractRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminContractStoreController extends BaseController
{
    private StoreContractAction $storeAction;

    public function __construct(StoreContractAction $storeAction)
    {
        $this->storeAction = $storeAction;
    }

    /**
     * Store a newly created contract in storage.
     */
    public function __invoke(
        StoreContractRequest $request,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $contract = $this->storeAction->execute($validated);

            Log::info('Contract created successfully', [
                'contract_id' => $contract->id,
                'contract_slug' => $contract->slug,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.contract.list')
                ->with('success', 'Contract created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create contract', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to create contract. Please try again.'])
                ->withInput();
        }
    }
}
