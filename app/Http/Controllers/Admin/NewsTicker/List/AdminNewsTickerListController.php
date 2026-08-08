<?php

namespace App\Http\Controllers\Admin\NewsTicker\List;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\NewsTicker\List\AdminNewsTickerListCollection;
use App\Models\NewsTicker;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminNewsTickerListController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_NEWS_TICKERS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_NEWS_TICKERS; }

    public function __invoke(Request $request): Response
    {
        $filters = $this->getFilters($request);

        $newsTickers = NewsTicker::query()
            ->with('creator:id,name,email')
            ->tap(fn($q) => $this->applyCreatorScope($q))
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $q->where(function ($query) use ($filters) {
                    $query->where('title->' . app()->getLocale(), 'like', '%' . $filters['search'] . '%')
                          ->orWhere('description->' . app()->getLocale(), 'like', '%' . $filters['search'] . '%');
                });
            })
            ->when(isset($filters['is_active']) && $filters['is_active'] !== '', function ($q) use ($filters) {
                $q->where('is_active', (bool) $filters['is_active']);
            })
            ->when(!empty($filters['category']), function ($q) use ($filters) {
                $q->where('category', $filters['category']);
            })
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->paginate($request->input('per_page', 15))->withQueryString();

        return Inertia::render('Admin/NewsTicker/List', [
            'newsTickers' => (new AdminNewsTickerListCollection($newsTickers))->toArray($request),
            'filters' => $filters,
        ]);
    }

    protected function getFilters(Request $request): array
    {
        return [
            'search' => $request->input('search', ''),
            'is_active' => $request->input('is_active', ''),
            'category' => $request->input('category', ''),
        ];
    }
}
