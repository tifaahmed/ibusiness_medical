<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Referral;
use App\Models\User;
use App\Support\MemberTransfers;
use App\Support\PublicMembershipUrl;
use App\Support\Referrals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Member-gets-member for the signed-in member (`App\Support\Referrals` owns every rule and number).
 *
 *  GET  /referral        my code + share link, the amounts, the dashboard (`totals`), one row per member I brought with their stage,
 *                        what they earned me and every payout (`referrals[].rewards`), who brought me
 *  POST /referral/claim  { number, source: link|card } — I came through this card number
 */
class ReferralController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $card = $user->memberships()->where('member_type', Membership::TYPE_PRIMARY)->latest('id')->first();
        $number = $card && ! str_starts_with(strtoupper((string) $card->membership_number), 'REG-') ? $card->membership_number : null;

        $mine = Referral::query()->where('referred_user_id', $user->id)->first();
        $board = Referrals::dashboard($user, $number);

        return response()->json(['data' => [
            'enabled' => Referrals::enabled(),
            /* null while the member has no real card yet: nothing to share until then. */
            'code' => $number,
            'link' => $number ? PublicMembershipUrl::base().'/r/'.$number : null,
            'amounts' => [
                /* Flat reward when a brought member starts a trial; percent of EVERY paid subscription (renewals too); discount off their first one. */
                'trial_bonus' => Referrals::signupBonus(),
                'signup_bonus' => Referrals::signupBonus(),
                'commission_percent' => Referrals::commissionPercent(),
                'discount' => Referrals::discount(),
                /* PER PLAN (month|half|year): the new member's discount and what the referrer earns (a percent OR a fixed sum). Draw these. */
                'plans' => Referrals::rulesByPlan(),
            ],
            'totals' => $board['totals'] + ['count' => $board['totals']['registered']],
            'referrals' => $board['rows'],
            'brought_by' => $mine ? ['number' => $mine->referrer_number, 'discount' => $mine->isSubscribed() ? (float) $mine->discount_amount : Referrals::discount(), 'discounts' => array_map(fn ($rule) => $rule['discount'], Referrals::rulesByPlan()), 'discount_used' => $mine->isSubscribed()] : null,
        ]]);
    }

    /** Messages for every refusal, shared by `preview` and `claim`. */
    private const REFUSALS = [
        'disabled' => 'Invitations are not available right now.',
        'not_found' => 'We could not find a member card with this number.',
        'self' => 'You cannot use your own card number.',
        'mutual' => 'This member was brought by you, so they cannot bring you.',
        'already_referred' => 'You already told us who brought you.',
        'company' => 'Only individual members can enter a referral code.',
        'already_paid' => 'This code can only be added before your first subscription.',
    ];

    /**
     * POST /referral/preview { number } — the confirm step before entering a code: WRITES NOTHING. Answers who owns the number (card
     * number + first name + last two phone digits) and the discount per plan this member would get; or the reason it is refused.
     */
    public function preview(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate(['number' => ['required', 'string', 'max:32']]);

        $check = Referrals::check($user, $data['number']);
        if (! $check['ok']) {
            return response()->json(['message' => self::REFUSALS[$check['reason']] ?? 'The code could not be used.', 'reason' => $check['reason']], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['data' => [
            'number' => $check['card']->membership_number,
            'name' => MemberTransfers::masked($check['card']->user),
            'discounts' => array_map(fn ($rule) => $rule['discount'], Referrals::rulesByPlan()),
            'discount' => Referrals::discount(),
        ]]);
    }

    public function claim(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'number' => ['required', 'string', 'max:32'],
            'source' => ['nullable', 'in:link,card'],
        ]);

        $result = Referrals::claim($user, $data['number'], $data['source'] ?? Referral::SOURCE_LINK);

        if (! $result['ok']) {
            $messages = [
                'disabled' => 'Invitations are not available right now.',
                'not_found' => 'We could not find a member card with this number.',
                'self' => 'You cannot use your own card number.',
                'mutual' => 'This member was brought by you, so they cannot bring you.',
                'already_referred' => 'You already told us who brought you.',
                'company' => 'Only individual members can enter a referral code.',
                'already_paid' => 'This code can only be added before your first subscription.',
            ];

            return response()->json(['message' => $messages[$result['reason']] ?? 'The code could not be used.', 'reason' => $result['reason']], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['data' => ['discount' => (float) $result['referral']->discount_amount]], Response::HTTP_CREATED);
    }
}
