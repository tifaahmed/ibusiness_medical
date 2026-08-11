<?php

namespace App\Http\Controllers\Admin\CardTemplate\Show;

use App\Http\Controllers\Controller;
use App\Models\CardTemplate;
use Illuminate\Http\JsonResponse;

class AdminCardTemplateShowController extends Controller
{
    public function __invoke(CardTemplate $cardTemplate): JsonResponse
    {
        return response()->json(['data' => $cardTemplate]);
    }
}
