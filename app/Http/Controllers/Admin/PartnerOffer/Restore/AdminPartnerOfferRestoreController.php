<?php

namespace App\Http\Controllers\Admin\PartnerOffer\Restore;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\PartnerOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminPartnerOfferRestoreController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_PARTNER_OFFERS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_PARTNER_OFFERS; }

    public function __invoke(Request $request, int $partnerOffer): RedirectResponse
    {
        $offer = PartnerOffer::onlyTrashed()->findOrFail($partnerOffer);
        $this->assertOwns($offer);

        $offerId = $offer->id;

        try {
            $offer->restore();

            Log::info('PartnerOffer restored successfully', [
                'partner_offer_id' => $offerId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.partner-offer.trash')
                ->with('success', 'Partner offer restored successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to restore partner offer', [
                'partner_offer_id' => $offerId,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to restore partner offer. Please try again.']);
        }
    }
}
