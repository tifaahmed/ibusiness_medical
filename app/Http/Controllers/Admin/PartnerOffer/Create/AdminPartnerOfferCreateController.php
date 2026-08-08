<?php

namespace App\Http\Controllers\Admin\PartnerOffer\Create;

use App\Enums\PartnerOffer\OperatorEnum;
use App\Models\Partner;
use App\Http\Controllers\Controller as BaseController;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminPartnerOfferCreateController extends BaseController
{
    public function __invoke(Request $request): Response
    {
        $partners = Partner::query()
            ->orderBy('title')
            ->get(['id', 'title']);

        return Inertia::render('Admin/PartnerOffer/Form/PartnerOfferFormView', [
            'partners' => $partners,
            'operators' => array_values(OperatorEnum::getOptions()),
        ]);
    }
}
