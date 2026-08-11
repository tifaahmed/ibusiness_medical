<?php

namespace App\Http\Controllers\Admin\CardTemplate\Index;

use App\Enums\CardTemplate\CardTemplateStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\CardTemplate;
use Inertia\Inertia;
use Inertia\Response;

class AdminCardTemplateIndexController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/CardTemplate/List', [
            'templates' => CardTemplate::orderByDesc('id')->get(),
            'statuses' => CardTemplateStatusEnum::options(),
        ]);
    }
}
