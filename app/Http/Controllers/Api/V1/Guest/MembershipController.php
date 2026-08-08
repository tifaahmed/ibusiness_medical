<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Http\Resources\Guest\MembershipResource;
use App\Models\Membership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MembershipController extends Controller
{
    public function lookup(Request $request): JsonResponse
    {
        $request->validate([
            'membership_number' => 'required|string',
        ]);

        $membership = Membership::visible()
            ->where(function ($query) use ($request) {
                $query->where('membership_number', $request->membership_number)
                    ->orWhere('slug', $request->membership_number);
            })
            ->first();

        if (!$membership) {
            throw ValidationException::withMessages([
                'membership_number' => __('home.member_card_modal.not_found'),
            ]);
        }

        return response()->json([
            'membership' => [
                'slug' => $membership->slug,
                'membership_number' => $membership->membership_number,
            ],
        ]);
    }

    public function show(Request $request, string $membership): JsonResponse
    {
        $membershipModel = Membership::visible()
            ->with(['user', 'company', 'familyMembers' => function ($query) {
                $query->where('is_active', true)->orderBy('created_at', 'asc');
            }, 'usages.facility', 'usages.facilityBranch', 'usages.facilityType', 'usages.media', 'cardLayouts'])
            ->where(function ($query) use ($membership) {
                $query->where('slug', $membership)
                    ->orWhere('membership_number', $membership);
            })
            ->firstOrFail();

        return response()->json([
            'membership' => (new MembershipResource($membershipModel))->toArray($request),
        ]);
    }
}
