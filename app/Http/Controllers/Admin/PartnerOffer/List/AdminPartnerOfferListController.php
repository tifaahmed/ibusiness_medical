<?php

namespace App\Http\Controllers\Admin\PartnerOffer\List;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\PartnerOffer\List\AdminPartnerOfferListCollection;
use App\Models\PartnerOffer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminPartnerOfferListController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_PARTNER_OFFERS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_PARTNER_OFFERS; }

    public function __invoke(Request $request): Response
    {
        $filters = $this->getFilters($request);

        $offers = PartnerOffer::query()
            ->with('creator:id,name,email', 'partner:id,title')
            ->tap(fn($q) => $this->applyCreatorScope($q))
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $q->where('title', 'like', '%' . $filters['search'] . '%');
            })
            ->when(!empty($filters['partner_id']), function ($q) use ($filters) {
                $q->where('partner_id', $filters['partner_id']);
            })
            ->orderBy('id', 'desc')
            ->paginate($request->input('per_page', 15))->withQueryString();

        return Inertia::render('Admin/PartnerOffer/List', [
            'offers' => (new AdminPartnerOfferListCollection($offers))->toArray($request),
            'filters' => $filters,
        ]);
    }

    protected function getFilters(Request $request): array
    {
        return [
            'search' => $request->input('search', ''),
            'partner_id' => $request->input('partner_id', ''),
        ];
    }
}
