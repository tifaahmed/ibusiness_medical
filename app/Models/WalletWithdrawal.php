<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One request to cash wallet money out to Vodafone Cash. Rules: `App\Support\WalletWithdrawals`. */
class WalletWithdrawal extends Model
{
    public const PENDING = 'pending';

    public const PROCESSING = 'processing';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const REJECTED = 'rejected';

    public const CANCELED = 'canceled';

    /** Statuses in which money has left (or may have left) — they count against the daily cap. */
    public const COUNTED = [self::PENDING, self::PROCESSING, self::PAID];

    protected $fillable = [
        'user_id', 'amount', 'method', 'msisdn', 'national_id', 'status', 'client_reference', 'provider_transaction_id', 'provider_status',
        'provider_code', 'provider_message', 'decided_by', 'decided_at', 'paid_at', 'refunded_at', 'events',
    ];

    protected $hidden = ['national_id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2', 'national_id' => 'encrypted', 'decided_at' => 'datetime', 'paid_at' => 'datetime',
            'refunded_at' => 'datetime', 'events' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logEvent(string $event, ?int $adminId = null, array $extra = []): void
    {
        $events = $this->events ?? [];
        $events[] = ['at' => now()->toIso8601String(), 'event' => $event, 'admin_id' => $adminId] + $extra;
        $this->events = $events;
    }

    /** `01012345678` → `0101••••678`: enough to recognise, not to copy. */
    public function maskedMsisdn(): string
    {
        return substr($this->msisdn, 0, 4).'••••'.substr($this->msisdn, -3);
    }
}
