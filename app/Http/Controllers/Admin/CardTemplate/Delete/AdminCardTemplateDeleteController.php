<?php

namespace App\Http\Controllers\Admin\CardTemplate\Delete;

use App\Http\Controllers\Controller;
use App\Models\CardTemplate;
use Illuminate\Http\JsonResponse;

class AdminCardTemplateDeleteController extends Controller
{
    public function __invoke(CardTemplate $cardTemplate): JsonResponse
    {
        $cardTemplate->delete();

        return response()->json(['data' => $cardTemplate]);
    }
}
