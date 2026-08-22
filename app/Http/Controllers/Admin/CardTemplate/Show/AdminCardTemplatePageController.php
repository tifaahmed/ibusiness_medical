<?php

namespace App\Http\Controllers\Admin\CardTemplate\Show;

use App\Enums\CardTemplate\CardTemplateStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\CardTemplate\Show\AdminCardTemplateShowResource;
use App\Models\CardTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Inertia detail page for a card template. Separate from
 * {@see AdminCardTemplateShowController}, which answers the same record as
 * JSON for the layout editor.
 */
class AdminCardTemplatePageController extends Controller
{
    public function __invoke(Request $request, CardTemplate $cardTemplate): Response
    {
        return Inertia::render('Admin/CardTemplate/Show', [
            'template' => (new AdminCardTemplateShowResource($cardTemplate))->toArray($request),
            'statuses' => CardTemplateStatusEnum::options(),
        ]);
    }
}
