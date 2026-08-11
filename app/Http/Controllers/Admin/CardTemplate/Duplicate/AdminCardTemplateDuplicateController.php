<?php

namespace App\Http\Controllers\Admin\CardTemplate\Duplicate;

use App\Http\Controllers\Controller;
use App\Models\CardTemplate;
use Illuminate\Http\JsonResponse;

class AdminCardTemplateDuplicateController extends Controller
{
    /**
     * Clone an existing template (layout, sample data, status, artwork) into a
     * new record, so an admin can start from an existing design instead of
     * building one from scratch.
     */
    public function __invoke(CardTemplate $cardTemplate): JsonResponse
    {
        $clone = $cardTemplate->replicate();

        $name = $cardTemplate->getTranslations('name');
        $clone->name = [
            'ar' => trim(($name['ar'] ?? '').' (نسخة)'),
            'en' => trim(($name['en'] ?? '').' (Copy)'),
        ];
        // Let HasSlug mint a fresh slug from the new name — the column is unique.
        $clone->slug = null;
        $clone->save();

        return response()->json(['data' => $clone], 201);
    }
}
