<?php

namespace App\Http\Controllers\Admin\Offer\List;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Offer\List\AdminOfferListCollection;
use App\Models\Offer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminOfferListController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_OFFERS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_OFFERS; }

    /**
     * Display a listing of offers.
     */
    public function __invoke(Request $request): Response
    {
        $filters = $this->getFilters($request);

        $offers = Offer::with(['offerable', 'creator:id,name,email'])
            ->tap(fn($q) => $this->applyCreatorScope($q))
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $q->where(function ($query) use ($filters) {
                    $query->where('title->' . app()->getLocale(), 'like', '%' . $filters['search'] . '%')
                          ->orWhere('slug', 'like', '%' . $filters['search'] . '%')
                          ->orWhere('phone', 'like', '%' . $filters['search'] . '%')
                          ->orWhere('price', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->when(!empty($filters['offerable_type']), function ($q) use ($filters) {
                $q->where('offerable_type', $filters['offerable_type']);
            })
            ->latest()
            ->paginate($request->input('per_page', 15))->withQueryString();

        return Inertia::render('Admin/Offer/List', [
            'offers' => new AdminOfferListCollection($offers)->toArray($request),
            'filters' => $filters,
        ]);
    }

    /**
     * Get filters from request.
     */
    protected function getFilters(Request $request): array
    {
        return [
            'search' => $request->input('search', ''),
            'offerable_type' => $request->input('offerable_type'),
        ];
    }
}
