<?php

namespace App\Http\Controllers\Admin\Tag\Create;

use App\Enums\Tag\TagEnum;
use App\Http\Controllers\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;

class AdminTagCreateController extends BaseController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Tag/Create/TagCreateView', [
            'iconOptions' => TagEnum::getIconOptions(),
            'colorOptions' => TagEnum::getColorOptions(),
        ]);
    }
}
