<?php

namespace App\Http\Controllers\Admin\PartnerOffer\Show;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\PartnerOffer\Show\AdminPartnerOfferShowResource;
use App\Models\PartnerOffer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminPartnerOfferShowController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_PARTNER_OFFERS;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_PARTNER_OFFERS;
    }

    public function __invoke(Request $request, PartnerOffer $partnerOffer): Response
    {
        $partnerOffer->load(['partner:id,title', 'creator:id,name,email'])->loadCount('requests');
        $this->assertOwns($partnerOffer);

        return Inertia::render('Admin/PartnerOffer/Show', [
            'partnerOffer' => (new AdminPartnerOfferShowResource($partnerOffer))->toArray($request),
        ]);
    }
}
