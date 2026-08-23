<?php

namespace App\Http\Controllers\Admin\Tag\Create;

use App\Enums\Tag\TagEnum;
use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Tag;
use Inertia\Inertia;
use Inertia\Response;

class AdminTagCreateController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_SERVICES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_SERVICES; }

    public function __invoke(): Response
    {
        return Inertia::render('Admin/Tag/Create/TagCreateView', [
            'iconOptions' => TagEnum::getIconOptions(),
            'colorOptions' => TagEnum::getColorOptions(),
            // Icons already in use, so the same icon+color pair can be reused consistently.
            'iconUsages' => Tag::iconUsages($this->scopesToCreator() ? auth()->id() : null),
        ]);
    }
}
