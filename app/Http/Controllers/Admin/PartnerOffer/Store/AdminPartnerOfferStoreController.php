<?php

namespace App\Http\Controllers\Admin\PartnerOffer\Store;

use App\Http\Controllers\Admin\PartnerOffer\Actions\Store\StorePartnerOfferAction;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\PartnerOffer\StorePartnerOfferRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminPartnerOfferStoreController extends BaseController
{
    private StorePartnerOfferAction $storeAction;

    public function __construct(StorePartnerOfferAction $storeAction)
    {
        $this->storeAction = $storeAction;
    }

    public function __invoke(StorePartnerOfferRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $offer = $this->storeAction->execute($validated);

            Log::info('PartnerOffer created successfully', [
                'partner_offer_id' => $offer->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.partner-offer.list')
                ->with('success', 'Partner offer created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create partner offer', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to create partner offer. Please try again.'])
                ->withInput();
        }
    }
}
