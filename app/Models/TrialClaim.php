<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The record that a phone and a national ID took a free trial. It only counts
 * while the card it issued still exists: once that card is deleted (soft or
 * hard) the claim is stale and the phone / ID may start a trial again — see
 * `StartTrialCard`.
 */
class TrialClaim extends Model
{
    protected $fillable = ['national_id', 'phone', 'membership_id', 'user_id', 'ip_address', 'user_agent'];

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    /** Claims whose card is still there (not deleted, not soft-deleted). */
    public function scopeHoldingCard(Builder $query): Builder
    {
        return $query->whereHas('membership');
    }

    /** Claims whose card is gone — they block nothing and are released on the next trial. */
    public function scopeStale(Builder $query): Builder
    {
        return $query->whereDoesntHave('membership');
    }
}
