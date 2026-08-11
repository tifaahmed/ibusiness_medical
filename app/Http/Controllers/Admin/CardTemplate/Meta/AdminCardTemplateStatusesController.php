<?php

namespace App\Http\Controllers\Admin\CardTemplate\Meta;

use App\Enums\CardTemplate\CardTemplateStatusEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AdminCardTemplateStatusesController extends Controller
{
    /**
     * List available statuses and the layout/sample_data fields each one hides,
     * so the admin form stays in sync with CardTemplateStatusEnum::hiddenFields()
     * without hardcoding the mapping on the frontend.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                fn (CardTemplateStatusEnum $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                    'hidden_fields' => $status->hiddenFields(),
                ],
                CardTemplateStatusEnum::cases(),
            ),
        ]);
    }
}
