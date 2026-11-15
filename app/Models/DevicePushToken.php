<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One phone (one app install) that can receive a push. Keyed by the sha256 of its FCM token. */
class DevicePushToken extends Model
{
    protected $fillable = ['user_id', 'token_hash', 'token', 'platform', 'locale', 'app_version', 'last_seen_at'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
