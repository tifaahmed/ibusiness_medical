<?php

namespace App\Http\Controllers\Admin\Offer\Delete;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Offer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminOfferDeleteController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_OFFERS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_OFFERS; }

    /**
     * Remove the specified offer from storage.
     */
    public function __invoke(Request $request, string $offerSlug): RedirectResponse
    {
        try {
            $offer = Offer::where('slug', $offerSlug)->firstOrFail();
            $this->assertOwns($offer);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Offer not found for deletion', [
                'slug' => $offerSlug,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Offer not found.']);
        } catch (\Exception $e) {
            Log::error('Error fetching offer for deletion', [
                'slug' => $offerSlug,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'An error occurred while fetching the offer.']);
        }

        // Store data for logging before deletion
        $offerId = $offer->id;
        $offerTitle = $offer->title;
        $offerSlugValue = $offer->slug;

        try {
            DB::beginTransaction();

            // Delete the offer
            $offer->delete();

            DB::commit();

            Log::info('Offer deleted successfully', [
                'offer_id' => $offerId,
                'offer_slug' => $offerSlugValue,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.offer.list')
                ->with('success', 'Offer deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete offer', [
                'offer_id' => $offerId,
                'offer_slug' => $offerSlugValue,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to delete offer. Please try again.']);
        }
    }
}
