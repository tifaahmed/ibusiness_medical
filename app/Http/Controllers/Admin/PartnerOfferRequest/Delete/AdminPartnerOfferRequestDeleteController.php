<?php

namespace App\Http\Controllers\Admin\PartnerOfferRequest\Delete;

use App\Http\Controllers\Controller as BaseController;
use App\Models\PartnerOfferRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminPartnerOfferRequestDeleteController extends BaseController
{
    public function __invoke(Request $request, PartnerOfferRequest $partnerOfferRequest): RedirectResponse
    {
        try {
            $partnerOfferRequest->delete();

            Log::info('PartnerOfferRequest deleted successfully', [
                'partner_offer_request_id' => $partnerOfferRequest->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.partner-offer-request.list')
                ->with('success', 'Request deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete partner offer request', [
                'partner_offer_request_id' => $partnerOfferRequest->id,
                'error_message' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to delete request. Please try again.']);
        }
    }
}
