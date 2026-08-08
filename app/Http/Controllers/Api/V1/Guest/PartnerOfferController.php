<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Enums\PartnerOffer\OperatorEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Guest\PartnerOfferResource;
use App\Models\PartnerOffer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerOfferController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $offers = PartnerOffer::with(['partner', 'media'])
            ->when($request->filled('operator'), fn($q) => $q->where('operator', $request->input('operator')))
            ->latest()
            ->get();

        return response()->json([
            'offers' => PartnerOfferResource::collection($offers),
            'operators' => OperatorEnum::getOptions(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        try {
            $partnerOffer = PartnerOffer::withTrashed()->with(['partner', 'media'])->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'العرض غير موجود.'], 404);
        }

        return response()->json([
            'offer' => new PartnerOfferResource($partnerOffer),
        ]);
    }
}
