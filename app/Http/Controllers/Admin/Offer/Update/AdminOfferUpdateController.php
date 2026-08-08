<?php

namespace App\Http\Controllers\Admin\Offer\Update;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Admin\Offer\Actions\Update\UpdateOfferAction;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Offer\UpdateOfferRequest;
use App\Models\Offer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminOfferUpdateController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_OFFERS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_OFFERS; }

    private UpdateOfferAction $updateAction;

    public function __construct(UpdateOfferAction $updateAction)
    {
        $this->updateAction = $updateAction;
    }

    public function __invoke(
        UpdateOfferRequest $request,
        string $offer,
    ): RedirectResponse {
        $validated = $request->validated();

        Log::debug('Offer update', [
            'method' => $request->method(),
            'real_method' => $request->getRealMethod(),
            'has__method' => $request->has('_method'),
            '_method_val' => $request->input('_method'),
            'hasFile_image' => $request->hasFile('image'),
            'hasFile_mobile_image' => $request->hasFile('mobile_image'),
            'hasFile_thumbnail' => $request->hasFile('thumbnail'),
            'hasFile_mobile_thumbnail' => $request->hasFile('mobile_thumbnail'),
            'validated_keys' => array_keys($validated),
        ]);

        try {
            $offer = Offer::where('slug', $offer)->firstOrFail();
            $this->assertOwns($offer);

            $updatedOffer = $this->updateAction->execute($offer, $validated);

            return redirect()->route('admin.offer.list')
                ->with('success', 'Offer updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update offer', [
                'offer_slug' => $offer,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to update offer. Please try again.'])
                ->withInput();
        }
    }
}
