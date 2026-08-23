<?php

namespace App\Http\Controllers\Admin\Tag\List;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Tag\List\AdminTagListCollection;
use App\Models\Tag;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminTagListController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_SERVICES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_SERVICES;
    }

    public function __invoke(Request $request): Response
    {
        $search = $request->input('search', '');
        // newest (default) / most_used / least_used.
        $sort = $request->input('sort', 'newest');
        // '', '1' = attached somewhere, '0' = never used.
        $used = $request->input('used', '');

        $tags = Tag::query()
            ->with(['creator:id,name,email'])
            ->tap(fn ($q) => $this->applyCreatorScope($q))
            ->withCount(['services', 'facilities', 'products'])
            ->when($search, function ($q) use ($search) {
                // Both names are listed, so both are searchable.
                $q->where(function ($query) use ($search) {
                    $query->where('name->en', 'like', "%$search%")
                        ->orWhere('name->ar', 'like', "%$search%");
                });
            })
            ->when($used === '1', fn ($q) => $q->where(function ($qq) {
                $qq->whereHas('services')
                    ->orWhereHas('facilities')
                    ->orWhereHas('products');
            }))
            ->when($used === '0', fn ($q) => $q
                ->doesntHave('services')
                ->doesntHave('facilities')
                ->doesntHave('products'))
            // Total usage across all three pivots; id as tiebreaker for stable pages.
            ->when($sort === 'most_used' || $sort === 'least_used', function ($q) use ($sort) {
                $direction = $sort === 'most_used' ? 'desc' : 'asc';
                $usage = '(select count(*) from service_tag where service_tag.tag_id = tags.id)'
                    . ' + (select count(*) from facility_tag where facility_tag.tag_id = tags.id)'
                    . ' + (select count(*) from product_tag where product_tag.tag_id = tags.id)';
                $q->orderByRaw("$usage $direction")->orderByDesc('tags.id');
            })
            ->when($sort !== 'most_used' && $sort !== 'least_used', fn ($q) => $q->latest())
            ->paginate($request->input('per_page', 15))
            ->withQueryString();

        return Inertia::render('Admin/Tag/List', [
            'tags' => new AdminTagListCollection($tags)->toArray($request),
            'filters' => ['search' => $search, 'sort' => $sort, 'used' => $used],
        ]);
    }
}
