<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerCardLayoutController extends Controller
{
    public function __invoke(Request $request, Partner $partner): JsonResponse
    {
        $data = $request->validate([
            'card_x' => ['nullable', 'numeric'],
            'card_y' => ['nullable', 'numeric'],
            'card_scale' => ['nullable', 'numeric', 'min:0.1', 'max:10'],
        ]);

        $partner->update([
            'card_x' => $data['card_x'] ?? null,
            'card_y' => $data['card_y'] ?? null,
            'card_scale' => $data['card_scale'] ?? null,
        ]);

        return response()->json([
            'partner' => [
                'id' => $partner->id,
                'card_x' => $partner->card_x,
                'card_y' => $partner->card_y,
                'card_scale' => $partner->card_scale,
            ],
        ]);
    }
}
