<?php

namespace App\Http\Controllers\Admin\CardTemplate\Back;

use App\Http\Controllers\Controller;
use App\Models\CardTemplate;
use Inertia\Inertia;
use Inertia\Response;

class AdminCardTemplateBackPageController extends Controller
{
    public function __invoke(CardTemplate $cardTemplate): Response
    {
        return Inertia::render('Admin/CardTemplate/Back', ['template' => $cardTemplate, 'backDefaults' => CardTemplate::BACK_DEFAULTS]);
    }
}
