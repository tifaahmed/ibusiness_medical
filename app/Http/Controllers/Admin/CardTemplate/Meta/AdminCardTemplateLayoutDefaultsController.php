<?php

namespace App\Http\Controllers\Admin\CardTemplate\Meta;

use App\Http\Controllers\Controller;
use App\Support\CardTemplateLayoutDefaults;
use Illuminate\Http\JsonResponse;

class AdminCardTemplateLayoutDefaultsController extends Controller
{
    /**
     * Default position/size/colour/font_size for a fresh template's layout,
     * plus the field groupings and the editor width font sizes are relative
     * to — served from the backend so it can be tuned without a redeploy.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'layout' => CardTemplateLayoutDefaults::layout(),
                'sample_data' => CardTemplateLayoutDefaults::sampleData(),
                'editor_width' => CardTemplateLayoutDefaults::EDITOR_WIDTH,
                'text_fields' => CardTemplateLayoutDefaults::TEXT_FIELDS,
                'image_fields' => CardTemplateLayoutDefaults::IMAGE_FIELDS,
                'code_fields' => CardTemplateLayoutDefaults::CODE_FIELDS,
            ],
        ]);
    }
}
