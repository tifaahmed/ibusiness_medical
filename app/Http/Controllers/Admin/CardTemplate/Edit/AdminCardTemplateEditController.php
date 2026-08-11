<?php

namespace App\Http\Controllers\Admin\CardTemplate\Edit;

use App\Enums\CardTemplate\CardTemplateStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\CardTemplate;
use App\Support\CardTemplateLayoutDefaults;
use Inertia\Inertia;
use Inertia\Response;

class AdminCardTemplateEditController extends Controller
{
    /**
     * The layout editor, used for both create (no template bound) and edit.
     */
    public function __invoke(?CardTemplate $cardTemplate = null): Response
    {
        return Inertia::render('Admin/CardTemplate/Edit', [
            'template' => $cardTemplate?->exists ? $cardTemplate : null,
            'statuses' => array_map(
                fn (CardTemplateStatusEnum $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                    'hidden_fields' => $status->hiddenFields(),
                ],
                CardTemplateStatusEnum::cases(),
            ),
            'defaults' => [
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
