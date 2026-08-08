<?php

namespace App\Http\Controllers\Admin\Partner\Delete;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Partner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminPartnerDeleteController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_PARTNERS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_PARTNERS; }

    public function __invoke(Request $request, Partner $partner): RedirectResponse
    {
        $this->assertOwns($partner);

        $partnerId = $partner->id;

        try {
            DB::beginTransaction();

            $partner->delete();

            DB::commit();

            Log::info('Partner deleted successfully', [
                'partner_id' => $partnerId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.partner.list')
                ->with('success', 'Partner deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete partner', [
                'partner_id' => $partnerId,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to delete partner. Please try again.']);
        }
    }
}
