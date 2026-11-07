<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One payout to a referrer (a trial reward or a subscription commission). The audit ledger of `App\Support\Referrals`; money lives in `wallet_transactions`. */
class ReferralReward extends Model
{
    public const KIND_TRIAL = 'trial';

    public const KIND_COMMISSION = 'commission';

    public const STATUS_PAID = 'paid';

    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'referral_id', 'referrer_user_id', 'referred_user_id', 'kind', 'reward_key', 'subscription_id', 'plan',
        'base_amount', 'rate', 'amount', 'status', 'reversed_at', 'reversal_reason', 'note',
    ];

    protected function casts(): array
    {
        return ['base_amount' => 'decimal:2', 'rate' => 'decimal:2', 'amount' => 'decimal:2', 'reversed_at' => 'datetime'];
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(MembershipSubscription::class, 'subscription_id');
    }
}
