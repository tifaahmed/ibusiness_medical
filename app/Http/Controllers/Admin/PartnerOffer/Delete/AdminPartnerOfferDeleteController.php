<?php

namespace App\Http\Controllers\Admin\PartnerOffer\Delete;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\PartnerOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminPartnerOfferDeleteController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_PARTNER_OFFERS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_PARTNER_OFFERS; }

    public function __invoke(Request $request, PartnerOffer $partnerOffer): RedirectResponse
    {
        $this->assertOwns($partnerOffer);

        $offerId = $partnerOffer->id;

        try {
            DB::beginTransaction();

            $partnerOffer->delete();

            DB::commit();

            Log::info('PartnerOffer deleted successfully', [
                'partner_offer_id' => $offerId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.partner-offer.list')
                ->with('success', 'Partner offer moved to trash successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete partner offer', [
                'partner_offer_id' => $offerId,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to delete partner offer. Please try again.']);
        }
    }
}
