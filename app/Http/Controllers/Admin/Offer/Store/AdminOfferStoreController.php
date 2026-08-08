<?php

namespace App\Http\Controllers\Admin\Offer\Store;

use App\Http\Controllers\Admin\Offer\Actions\Store\StoreOfferAction;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Offer\StoreOfferRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminOfferStoreController extends BaseController
{
    private StoreOfferAction $storeAction;

    public function __construct(StoreOfferAction $storeAction)
    {
        $this->storeAction = $storeAction;
    }

    /**
     * Store a newly created offer in storage.
     */
    public function __invoke(
        StoreOfferRequest $request,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            // Execute the action to store offer in database
            $offer = $this->storeAction->execute($validated);

            Log::info('Offer created successfully', [
                'offer_id' => $offer->id,
                'offer_slug' => $offer->slug,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.offer.list')
                ->with('success', 'Offer created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create offer', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to create offer. Please try again.'])
                ->withInput();
        }
    }
}
