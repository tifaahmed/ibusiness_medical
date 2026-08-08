<?php

namespace App\Http\Controllers\Admin\PartnerOfferRequest\Show;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\PartnerOfferRequest\List\AdminPartnerOfferRequestListResource;
use App\Models\PartnerOfferRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminPartnerOfferRequestShowController extends BaseController
{
    public function __invoke(Request $request, PartnerOfferRequest $partnerOfferRequest): Response
    {
        $partnerOfferRequest->load('partnerOffer:id,title,partner_id', 'partnerOffer.partner:id,title');

        $partnerOfferRequest->markAsRead();

        return Inertia::render('Admin/PartnerOfferRequest/Show', [
            'request' => (new AdminPartnerOfferRequestListResource($partnerOfferRequest))->toArray($request),
        ]);
    }
}
