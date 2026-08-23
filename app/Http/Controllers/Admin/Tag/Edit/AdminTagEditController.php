<?php

namespace App\Http\Controllers\Admin\Tag\Edit;

use App\Enums\Tag\TagEnum;
use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Tag\Show\AdminTagShowResource;
use App\Models\Tag;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminTagEditController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_SERVICES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_SERVICES; }

    public function __invoke(Request $request, int $tag): Response
    {
        $tag = Tag::findOrFail($tag);
        $this->assertOwns($tag);

        return Inertia::render('Admin/Tag/Edit/TagEditView', [
            'tag' => new AdminTagShowResource($tag),
            'iconOptions' => TagEnum::getIconOptions(),
            'colorOptions' => TagEnum::getColorOptions(),
            // Icons this admin already used, minus the tag being edited.
            'iconUsages' => Tag::iconUsages(
                $this->scopesToCreator() ? auth()->id() : null,
                $tag->id,
            ),
        ]);
    }
}
