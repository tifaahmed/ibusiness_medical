<?php

namespace App\Http\Controllers\Admin\PartnerOffer\Edit;

use App\Enums\PartnerOffer\OperatorEnum;
use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\PartnerOffer\Edit\AdminPartnerOfferEditResource;
use App\Models\Partner;
use App\Models\PartnerOffer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminPartnerOfferEditController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_PARTNER_OFFERS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_PARTNER_OFFERS; }

    public function __invoke(Request $request, PartnerOffer $partnerOffer): Response
    {
        $this->assertOwns($partnerOffer);

        $partners = Partner::query()
            ->orderBy('title')
            ->get(['id', 'title']);

        return Inertia::render('Admin/PartnerOffer/Form/PartnerOfferFormView', [
            'partnerOffer' => (new AdminPartnerOfferEditResource($partnerOffer))->toArray($request),
            'partners' => $partners,
            'operators' => array_values(OperatorEnum::getOptions()),
        ]);
    }
}
