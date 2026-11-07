<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Referrals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The trip of an invitation link through the app store.
 *
 *  POST partner/referral/click  (key-gated, from the website) { code, ip }  — a visitor tapped `/r/<code>`
 *  POST mobile/referral/match   (public, throttled, from the app's first launch) — "which code was tapped from MY IP?"
 *
 * Only a hash of the IP is stored, a tap is useful for 24 hours and is matched once, and an ambiguous IP matches nothing.
 */
class ReferralLinkController extends Controller
{
    public function click(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'ip' => ['required', 'ip'],
        ]);

        return response()->json(['data' => ['recorded' => Referrals::recordClick($data['code'], $data['ip'])]]);
    }

    public function match(Request $request): JsonResponse
    {
        return response()->json(['data' => ['code' => Referrals::matchClick((string) $request->ip())]]);
    }
}
