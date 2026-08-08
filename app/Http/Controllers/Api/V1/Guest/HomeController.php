<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Guest\NewsTickerResource;
use App\Http\Resources\Api\V1\Guest\OfferResource;
use App\Models\Contract;
use App\Models\NewsTicker;
use App\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $offers = OfferResource::collection(
            Offer::with(['offerable'])->orderByDesc('created_at')->get()
        );

        $contracts = Contract::active()->ordered()->get()->map(fn($contract) => [
            'id' => $contract->id,
            'name' => $contract->name,
            'description' => $contract->description,
            'phones' => $contract->phones,
            'slug' => $contract->slug,
            'image' => $contract->image,
        ]);

        $newsTickers = NewsTickerResource::collection(
            NewsTicker::active()->ordered()->get()
        );

        return response()->json([
            'news_tickers' => $newsTickers,
            'offers' => $offers,
            'contracts' => $contracts,
        ]);
    }
}
