<?php

namespace App\Http\Controllers\Admin\Partner\Update;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Admin\Partner\Actions\Update\UpdatePartnerAction;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Partner\UpdatePartnerRequest;
use App\Models\Partner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminPartnerUpdateController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_PARTNERS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_PARTNERS; }

    private UpdatePartnerAction $updateAction;

    public function __construct(UpdatePartnerAction $updateAction)
    {
        $this->updateAction = $updateAction;
    }

    public function __invoke(UpdatePartnerRequest $request, Partner $partner): RedirectResponse
    {
        $this->assertOwns($partner);

        $validated = $request->validated();

        try {
            $updatedPartner = $this->updateAction->execute($partner, $validated);

            Log::info('Partner updated successfully', [
                'partner_id' => $updatedPartner->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.partner.list')
                ->with('success', 'Partner updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update partner', [
                'partner_id' => $partner->id,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to update partner. Please try again.'])
                ->withInput();
        }
    }
}
