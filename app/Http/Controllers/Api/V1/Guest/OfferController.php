<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Guest\OfferResource;
use App\Models\Offer;
use Illuminate\Http\JsonResponse;

class OfferController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $offers = OfferResource::collection(
            Offer::with(['offerable'])->orderByDesc('created_at')->get()
        );

        return response()->json([
            'offers' => $offers,
        ]);
    }
}
