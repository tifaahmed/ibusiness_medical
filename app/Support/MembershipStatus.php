<?php

namespace App\Support;

use App\Enums\FamilyMember\ApprovalStatusEnum;
use App\Models\Membership;
use App\Models\MembershipSubscription;
use App\Models\User;
use App\Services\Otp\RegistrationProgress;

/**
 * The state of a card as every client draws it: one shape for the scan page,
 * the app and the website, so none of them re-derives "is it expired".
 *
 * Two audiences. {@see self::forCard()} is safe to show to whoever scans the
 * QR code — no name, phone or ID. {@see self::forMember()} adds what only the
 * signed-in owner sees: profile progress, relatives and open payments.
 */
final class MembershipStatus
{
    /**
     * @return array<string, mixed>
     */
    public static function forCard(?Membership $membership): array
    {
        $state = $membership?->state() ?? 'none';
        $expired = in_array($state, [Membership::STATE_TRIAL_EXPIRED, Membership::STATE_EXPIRED], true);

        return [
            'state' => $state,
            'is_trial' => (bool) $membership?->isTrial(),
            /* True for a trial that ran out AND a paid card that lapsed: both get the big red sign. */
            'expired' => $expired,
            'valid' => in_array($state, [Membership::STATE_TRIAL, Membership::STATE_ACTIVE], true),
            'expires_on' => $membership?->expiration_date?->toDateString(),
            'days_left' => $membership?->daysLeft() ?? 0,
            'price' => MembershipPlan::price(),
            'months' => MembershipPlan::months(),
            /* Whether "buy now with Paymob" may be drawn; the contact options are always available. */
            'can_pay_online' => MembershipPlan::canPayOnline(),
            'contact' => ShopContact::current(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forMember(User $user): array
    {
        $membership = $user->memberships()->latest('id')->first();
        $base = self::forCard($membership);

        $relatives = $membership?->familyMembers()->get(['id', 'approval_status', 'is_active']) ?? collect();
        $counted = $relatives->filter(fn ($r) => $r->approval_status !== ApprovalStatusEnum::REJECTED)->count();

        $open = $membership?->subscriptions()
            ->where('status', MembershipSubscription::STATUS_PENDING)
            ->latest('id')
            ->first(['id', 'method', 'amount', 'created_at']);

        return $base + [
            'has_card' => $membership !== null && $base['state'] !== Membership::STATE_PENDING,
            'membership_number' => $membership?->membership_number,
            'slug' => $membership?->slug,
            'progress' => app(RegistrationProgress::class)->for($user),
            'relatives' => [
                'count' => $counted,
                'pending' => $relatives->filter(fn ($r) => $r->approval_status === ApprovalStatusEnum::PENDING)->count(),
                'max' => MembershipPlan::MAX_RELATIVES,
                /* First-degree only: parents, spouse, children. Said once here so no client words it differently. */
                'degree' => 'first',
                /* A trial card holds the owner alone; relatives go through customer services until the card is paid. */
                'must_contact_support' => $membership?->isTrial() ?? false,
                'can_add' => $membership !== null
                    && ! $membership->isTrial()
                    && $base['valid']
                    && $counted < MembershipPlan::MAX_RELATIVES,
            ],
            'open_subscription' => $open === null ? null : [
                'id' => $open->id,
                'method' => $open->method,
                'amount' => (float) $open->amount,
                'created_at' => $open->created_at?->toAtomString(),
            ],
        ];
    }
}
