<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A tap on an invitation link, kept as an IP hash only. See `App\Support\Referrals::recordClick()` / `matchClick()`. */
class ReferralClick extends Model
{
    protected $fillable = ['code', 'ip_hash', 'matched_at'];

    protected function casts(): array
    {
        return ['matched_at' => 'datetime'];
    }
}
