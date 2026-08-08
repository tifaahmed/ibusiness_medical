<?php

namespace App\Http\Controllers\Admin\PartnerOffer\Update;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Admin\PartnerOffer\Actions\Update\UpdatePartnerOfferAction;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\PartnerOffer\UpdatePartnerOfferRequest;
use App\Models\PartnerOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminPartnerOfferUpdateController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_PARTNER_OFFERS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_PARTNER_OFFERS; }

    private UpdatePartnerOfferAction $updateAction;

    public function __construct(UpdatePartnerOfferAction $updateAction)
    {
        $this->updateAction = $updateAction;
    }

    public function __invoke(UpdatePartnerOfferRequest $request, PartnerOffer $partnerOffer): RedirectResponse
    {
        $this->assertOwns($partnerOffer);

        $validated = $request->validated();

        try {
            $updatedOffer = $this->updateAction->execute($partnerOffer, $validated);

            Log::info('PartnerOffer updated successfully', [
                'partner_offer_id' => $updatedOffer->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.partner-offer.list')
                ->with('success', 'Partner offer updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update partner offer', [
                'partner_offer_id' => $partnerOffer->id,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to update partner offer. Please try again.'])
                ->withInput();
        }
    }
}
