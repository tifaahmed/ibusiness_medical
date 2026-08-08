<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerCompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $partners = Partner::with(['media', 'offers' => fn($q) => $q->latest()])
            ->latest()
            ->get()
            ->map(fn($partner) => [
                'id' => $partner->id,
                'title' => $partner->title,
                'short_description' => $partner->short_description,
                'header_image' => $partner->header_image,
                'offers_count' => $partner->offers->count(),
            ]);

        return response()->json([
            'partners' => $partners,
        ]);
    }

    public function show(Partner $partner): JsonResponse
    {
        $partner->load(['media', 'offers' => fn($q) => $q->with('media')->latest()->limit(5)]);

        return response()->json([
            'partner' => [
                'id' => $partner->id,
                'title' => $partner->title,
                'short_description' => $partner->short_description,
                'description' => $partner->description,
                'header_image' => $partner->header_image,
                'offers' => $partner->offers->map(fn($offer) => [
                    'id' => $offer->id,
                    'title' => $offer->title,
                    'short_description' => $offer->short_description,
                    'description' => $offer->description,
                    'old_price' => $offer->old_price,
                    'new_price' => $offer->new_price,
                    'phone_number' => $offer->phone_number,
                    'operator' => $offer->operator?->value,
                    'header_image' => $offer->mobile_header_image,
                    'small_image' => $offer->mobile_small_image,
                    'created_at' => $offer->created_at,
                ]),
            ],
        ]);
    }

    public function offers(Partner $partner, Request $request): JsonResponse
    {
        $offers = $partner->offers()
            ->with('media')
            ->latest()
            ->get()
            ->map(fn($offer) => [
                'id' => $offer->id,
                'title' => $offer->title,
                'short_description' => $offer->short_description,
                'description' => $offer->description,
                'old_price' => $offer->old_price,
                'new_price' => $offer->new_price,
                'phone_number' => $offer->phone_number,
                'operator' => $offer->operator?->value,
                'header_image' => $offer->mobile_header_image,
                'small_image' => $offer->mobile_small_image,
                'created_at' => $offer->created_at,
            ]);

        return response()->json([
            'partner' => [
                'id' => $partner->id,
                'title' => $partner->title,
            ],
            'offers' => $offers,
        ]);
    }
}
