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

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_SERVICES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_SERVICES; }

    public function __invoke(Request $request): Response
    {
        $search = $request->input('search', '');

        $tags = Tag::query()
            ->with(['creator:id,name,email'])
            ->tap(fn($q) => $this->applyCreatorScope($q))
            ->when($search, function ($q) use ($search) {
                $q->where('name', 'like', "%$search%");
            })
            ->latest()
            ->paginate($request->input('per_page', 15))
            ->withQueryString();

        return Inertia::render('Admin/Tag/List', [
            'tags' => new AdminTagListCollection($tags)->toArray($request),
            'filters' => ['search' => $search],
        ]);
    }
}
