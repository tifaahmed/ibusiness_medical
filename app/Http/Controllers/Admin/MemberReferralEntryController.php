<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\User;
use App\Support\MemberTransfers;
use App\Support\Referrals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * An admin enters a referral code FOR a member (after confirming it with them): same rules as the member's own entry (individual,
 * not yet subscribed, once for ever, never yourself) via `Referrals::check/claim`; the referrer gets the signup bonus at once.
 *   GET  /admin/user/membership/{user}/referral          current state + (with ?number=) a preview of who owns the number
 *   POST /admin/user/membership/{user}/referral {number}  apply it
 */
class MemberReferralEntryController extends Controller
{
    public function show(Request $request, User $user): JsonResponse
    {
        $mine = Referral::query()->where('referred_user_id', $user->id)->first();
        $out = ['brought_by' => $mine ? ['number' => $mine->referrer_number, 'source' => $mine->source, 'at' => $mine->created_at?->format('Y-m-d H:i')] : null, 'bonus' => Referrals::signupBonus()];

        if (! $mine && filled($request->query('number'))) {
            $check = Referrals::check($user, (string) $request->query('number'));
            if (! $check['ok']) {
                return response()->json($out + ['ok' => false, 'reason' => $check['reason']], 422);
            }
            $out += ['ok' => true, 'owner' => ['number' => $check['card']->membership_number, 'name' => MemberTransfers::masked($check['card']->user), 'full_name' => $check['card']->user->name, 'phone' => $check['card']->user->phone]];
        }

        return response()->json($out);
    }

    public function store(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['number' => ['required', 'string', 'max:32'], 'confirmed' => ['accepted']]);

        $result = Referrals::claim($user, $data['number'], Referral::SOURCE_ADMIN);
        if (! $result['ok']) {
            return response()->json(['ok' => false, 'reason' => $result['reason']], 422);
        }

        Log::info('Admin entered a referral code for a member.', ['admin_id' => $request->user()->id, 'member_id' => $user->id, 'number' => $data['number']]);

        return response()->json(['ok' => true, 'bonus' => Referrals::signupBonus()], 201);
    }
}
