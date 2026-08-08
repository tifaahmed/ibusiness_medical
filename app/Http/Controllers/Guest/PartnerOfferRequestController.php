<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\PartnerOffer;
use App\Models\PartnerOfferRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PartnerOfferRequestController extends Controller
{
    public function store(Request $request, PartnerOffer $partnerOffer): RedirectResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string|max:20',
        ]);

        try {
            PartnerOfferRequest::create([
                'partner_offer_id' => $partnerOffer->id,
                'phone_number' => $validated['phone_number'],
            ]);

            Log::info('PartnerOffer request submitted', [
                'partner_offer_id' => $partnerOffer->id,
                'phone_number' => $validated['phone_number'],
                'ip_address' => $request->ip(),
            ]);

            return back()->with('success', 'Your request has been submitted successfully. We will contact you soon.');
        } catch (\Exception $e) {
            Log::error('Failed to submit partner offer request', [
                'partner_offer_id' => $partnerOffer->id,
                'error_message' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to submit your request. Please try again.']);
        }
    }
}
