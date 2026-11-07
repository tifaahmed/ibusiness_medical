<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One member brought by another. See `App\Support\Referrals`. */
class Referral extends Model
{
    public const SOURCE_LINK = 'link';

    public const SOURCE_CARD = 'card';

    protected $fillable = [
        'referrer_user_id', 'referrer_membership_id', 'referrer_number', 'referred_user_id', 'source',
        'signup_bonus', 'subscription_bonus', 'discount_amount', 'subscription_id', 'subscribed_at',
        'trial_started_at', 'trial_rewarded_at', 'trial_bonus',
    ];

    protected function casts(): array
    {
        return [
            'signup_bonus' => 'decimal:2', 'subscription_bonus' => 'decimal:2', 'discount_amount' => 'decimal:2',
            'subscribed_at' => 'datetime', 'trial_started_at' => 'datetime', 'trial_rewarded_at' => 'datetime', 'trial_bonus' => 'decimal:2',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(ReferralReward::class);
    }

    /** Whether the new member has paid for a subscription (the referrer's second reward was booked). */
    public function isSubscribed(): bool
    {
        return $this->subscribed_at !== null;
    }
}
