<?php

namespace App\Http\Controllers\Api\V1\Partner;

use App\Http\Controllers\Controller;
use App\Http\Resources\Partner\PartnerMembershipResource;
use App\Models\Membership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membership lookup for partner properties.
 *
 * One GET, one membership number, the card summary back as JSON. The caller is
 * another server holding the shared key — see `VerifyPartnerApiKey`.
 */
class MembershipController extends Controller
{
    public function show(Request $request, string $membershipNumber): JsonResponse
    {
        $membership = Membership::query()
            ->visible()
            ->with([
                'user',
                'company',
                'familyMembers' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('created_at'),
            ])
            /*
             * Slug as well as number: the slug is what the member sees in their
             * own card URL, so it is what they are most likely to paste in.
             * The scope is shared with `Membership::earnsMemberPrice()`, so a
             * number this answers for is a number an order prices for.
             */
            ->matchingNumber($membershipNumber)
            ->first();

        if (! $membership) {
            return response()->json([
                'message' => __('home.member_card_modal.not_found'),
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'data' => (new PartnerMembershipResource($membership))->toArray($request),
        ]);
    }
}
