<?php

namespace App\Http\Controllers\Admin\Partner\Store;

use App\Http\Controllers\Admin\Partner\Actions\Store\StorePartnerAction;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Partner\StorePartnerRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminPartnerStoreController extends BaseController
{
    private StorePartnerAction $storeAction;

    public function __construct(StorePartnerAction $storeAction)
    {
        $this->storeAction = $storeAction;
    }

    public function __invoke(StorePartnerRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $partner = $this->storeAction->execute($validated);

            Log::info('Partner created successfully', [
                'partner_id' => $partner->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.partner.list')
                ->with('success', 'Partner created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create partner', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to create partner. Please try again.'])
                ->withInput();
        }
    }
}
