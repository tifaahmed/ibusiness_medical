<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Guest\NewsTickerResource;
use App\Models\NewsTicker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsTickerController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'news_tickers' => NewsTickerResource::collection(
                NewsTicker::active()->ordered()->get()
            ),
        ]);
    }
}
