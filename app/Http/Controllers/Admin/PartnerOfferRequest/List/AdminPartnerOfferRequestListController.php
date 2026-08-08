<?php

namespace App\Http\Controllers\Admin\PartnerOfferRequest\List;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\PartnerOfferRequest\List\AdminPartnerOfferRequestListCollection;
use App\Models\PartnerOfferRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminPartnerOfferRequestListController extends BaseController
{
    public function __invoke(Request $request): Response
    {
        $filters = $this->getFilters($request);

        $requests = PartnerOfferRequest::query()
            ->with('partnerOffer:id,title,partner_id', 'partnerOffer.partner:id,title')
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $q->where('phone_number', 'like', '%' . $filters['search'] . '%');
            })
            ->when(!empty($filters['partner_offer_id']), function ($q) use ($filters) {
                $q->where('partner_offer_id', $filters['partner_offer_id']);
            })
            ->orderBy('id', 'desc')
            ->paginate($request->input('per_page', 15))->withQueryString();

        return Inertia::render('Admin/PartnerOfferRequest/List', [
            'requests' => (new AdminPartnerOfferRequestListCollection($requests))->toArray($request),
            'filters' => $filters,
        ]);
    }

    protected function getFilters(Request $request): array
    {
        return [
            'search' => $request->input('search', ''),
            'partner_offer_id' => $request->input('partner_offer_id', ''),
        ];
    }
}
