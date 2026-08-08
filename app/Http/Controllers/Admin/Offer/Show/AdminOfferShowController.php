<?php

namespace App\Http\Controllers\Admin\Offer\Show;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Offer\Show\AdminOfferShowResource;
use App\Models\Offer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminOfferShowController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_OFFERS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_OFFERS; }

    /**
     * Display the specified offer.
     */
    public function __invoke(Request $request, string $offer): Response
    {
        $offer = Offer::with('offerable')
            ->where('slug', $offer)
            ->firstOrFail();
        $this->assertOwns($offer);

        $result = [
            'offer' => (new AdminOfferShowResource($offer))->toArray($request),
        ];

        return Inertia::render('Admin/Offer/Show', $result);
    }
}
