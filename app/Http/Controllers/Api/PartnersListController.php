<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;

class PartnersListController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $partners = Partner::orderBy('title')
            ->get()
            ->map(fn(Partner $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'image' => $p->image ?: null,
                'card_x' => $p->card_x,
                'card_y' => $p->card_y,
                'card_scale' => $p->card_scale,
            ])
            ->values();

        return response()->json(['partners' => $partners]);
    }
}
