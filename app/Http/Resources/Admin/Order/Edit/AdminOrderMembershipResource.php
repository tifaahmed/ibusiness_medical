<?php

namespace App\Http\Resources\Admin\Order\Edit;

use App\Models\Membership;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Whether this order was bought on a membership card, and which card.
 *
 * Wrapped around the ORDER, not the membership: the interesting answers are
 * the ones where there is no membership to wrap. A number nobody can resolve
 * and an empty box both mean "paid full price", but they are very different
 * things to be looking at — one is a customer without a card, the other is a
 * customer who believes they have one.
 *
 * The lookup is `Membership::cardFor()`, which is what priced the order at the
 * checkout. Reading it back through the same call is the point: the badge on
 * the edit form cannot claim a discount the pricing never gave.
 *
 * Note this reports the card AS IT STANDS TODAY, not as it stood when the
 * order was placed — a card that has since expired shows as expired against an
 * order that was correctly discounted at the time. The amounts archived on the
 * order are the record of what was actually charged; this is the record of who
 * the customer is now, which is what an admin editing the order needs.
 */
class AdminOrderMembershipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $number = trim((string) $this->membership_number);

        if ($number === '') {
            return [
                'status' => 'none',
                'number' => null,
                'earns_member_price' => false,
                'card' => null,
            ];
        }

        $card = Membership::cardFor($number, ['user']);

        if ($card === null) {
            /* Typed, but resolves to nothing — unknown, or hidden, which the
               checkout treats the same way. Worth showing loudly: it is the
               state where a customer thinks they are a member and is not. */
            return [
                'status' => 'unknown',
                'number' => $number,
                'earns_member_price' => false,
                'card' => null,
            ];
        }

        $expired = $card->expiration_date?->isPast() ?? false;

        return [
            'status' => match (true) {
                ! $card->is_active => 'inactive',
                $expired => 'expired',
                default => 'valid',
            },
            'number' => $number,
            'earns_member_price' => $card->isCurrent(),
            'card' => [
                'id' => $card->id,
                'membership_number' => $card->membership_number,
                'slug' => $card->slug,
                'job_title' => $card->getTranslation('job_title', app()->getLocale())
                    ?: $card->getTranslation('job_title', 'ar')
                    ?: $card->getTranslation('job_title', 'en'),
                'registration_date' => $card->registration_date?->format('Y-m-d'),
                'expiration_date' => $card->expiration_date?->format('Y-m-d'),
                'is_active' => (bool) $card->is_active,
                'is_expired' => $expired,
                /* Days left, so a card about to lapse reads as one before it
                   becomes an argument at the door. Negative once it has. */
                'days_to_expiry' => $card->expiration_date
                    ? (int) now()->startOfDay()->diffInDays($card->expiration_date->startOfDay(), false)
                    : null,
                'user' => $card->user === null ? null : [
                    'id' => $card->user->id,
                    /* The user SLUG: it is what `admin.user.membership.show`
                       binds on, so the link from here opens the member. */
                    'slug' => $card->user->slug,
                    'name' => $card->user->name,
                    'email' => $card->user->email,
                    'phone' => $card->user->phone,
                    'avatar_url' => get_image_url($card->user, 'avatar'),
                ],
            ],
        ];
    }
}
