<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Models\PartnerOfferRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PartnerOfferRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'partner_offer_id' => 'required|integer|exists:partner_offers,id',
            'phone_number' => 'required|string|max:20',
        ]);

        try {
            $offerRequest = PartnerOfferRequest::create([
                'partner_offer_id' => $validated['partner_offer_id'],
                'phone_number' => $validated['phone_number'],
            ]);

            Log::info('PartnerOffer request submitted via API', [
                'partner_offer_id' => $validated['partner_offer_id'],
                'phone_number' => $validated['phone_number'],
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'تم إرسال طلبك بنجاح. سنقوم بالتواصل معك قريباً.',
                'request' => $offerRequest,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to submit partner offer request via API', [
                'partner_offer_id' => $validated['partner_offer_id'] ?? null,
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'فشل إرسال الطلب. يرجى المحاولة مرة أخرى.',
            ], 500);
        }
    }
}
