<?php

namespace App\Http\Controllers\Admin\Tag\Show;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Tag\Show\AdminTagShowResource;
use App\Models\Tag;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminTagShowController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_SERVICES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_SERVICES; }

    public function __invoke(Request $request, int $tag): Response
    {
        $tag = Tag::with(['creator:id,name,email'])->findOrFail($tag);
        $this->assertOwns($tag);

        return Inertia::render('Admin/Tag/Show', [
            'tag' => new AdminTagShowResource($tag),
        ]);
    }
}
