<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Money a member adds to the wallet from the app, paid online through Paymob; credited only by a signed callback. */
class WalletTopup extends Model
{
    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const REJECTED = 'rejected';

    protected $fillable = ['user_id', 'amount', 'status', 'paymob_attempts', 'paymob_reference', 'paymob_order_id', 'paymob_transaction_id', 'paymob_response_code', 'channel', 'paid_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The reference sent to Paymob: `TOP{id}A{attempt}` — never ambiguous with an order code or a `SUB…` one. */
    public function nextPaymobReference(): string
    {
        return 'TOP'.$this->id.'A'.($this->paymob_attempts + 1);
    }

    /** `TOP12A3` or `TOP12` → 12; anything else → null. */
    public static function idFromReference(?string $reference): ?int
    {
        return preg_match('/^TOP(\d+)(?:A\d+)?$/', (string) $reference, $m) ? (int) $m[1] : null;
    }
}
