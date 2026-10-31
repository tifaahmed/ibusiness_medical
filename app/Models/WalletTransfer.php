<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A request to send loyalty points or wallet money to another member; it moves only when the recipient accepts. */
class WalletTransfer extends Model
{
    public const KIND_POINTS = 'points';

    public const KIND_WALLET = 'wallet';

    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const DECLINED = 'declined';

    public const CANCELLED = 'cancelled';

    public const EXPIRED = 'expired';

    protected $fillable = ['from_user_id', 'to_user_id', 'kind', 'amount', 'status', 'client_id', 'note', 'expires_at', 'responded_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'expires_at' => 'datetime', 'responded_at' => 'datetime'];
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::PENDING && $this->expires_at->isFuture();
    }
}
