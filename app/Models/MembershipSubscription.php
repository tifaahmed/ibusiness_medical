<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * One attempt to pay for a membership — online through Paymob, or through
 * customer services (cash to a courier, Vodafone Cash, Instapay) with a
 * receipt that an admin checks before the card is extended.
 *
 * A receipt is a CLAIM and never extends a card by itself; only
 * {@see \App\Actions\Membership\ActivateSubscription} does, and it is called by
 * a verified Paymob callback or by an admin pressing confirm.
 */
class MembershipSubscription extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const METHOD_PAYMOB = 'paymob';

    public const METHOD_COD = 'cash_on_delivery';

    public const METHOD_VODAFONE = 'vodafone_cash';

    public const METHOD_INSTAPAY = 'instapay';

    /** Paid from the member's wallet (yearly plan only). */
    public const METHOD_WALLET = 'wallet';

    /** Methods that hand customer services a receipt to verify. */
    public const RECEIPT_METHODS = [self::METHOD_VODAFONE, self::METHOD_INSTAPAY];

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_CANCELED = 'canceled';

    public const RECEIPT_COLLECTION = 'receipt';

    protected $fillable = [
        'membership_id', 'method', 'channel', 'status', 'amount', 'months', 'reference', 'notes', 'email',
        'paymob_attempts', 'paymob_reference', 'paymob_order_id', 'paymob_transaction_id', 'paymob_response_code',
        'paid_at', 'confirmed_by', 'confirmed_at', 'rejection_reason', 'receipt_emailed_at', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'receipt_emailed_at' => 'datetime',
        ];
    }

    public static function methods(): array
    {
        return [self::METHOD_PAYMOB, self::METHOD_COD, self::METHOD_VODAFONE, self::METHOD_INSTAPAY, self::METHOD_WALLET];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::RECEIPT_COLLECTION);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function paymentLogs(): HasMany
    {
        return $this->hasMany(OrderPaymentLog::class)->orderBy('id');
    }

    /** The reference sent to Paymob: `SUB{id}A{attempt}` — never ambiguous with an order code. */
    public function nextPaymobReference(): string
    {
        return 'SUB'.$this->id.'A'.($this->paymob_attempts + 1);
    }

    /** `SUB12A3` or `SUB12` → 12; anything else → null. */
    public static function idFromReference(?string $reference): ?int
    {
        return preg_match('/^SUB(\d+)(?:A\d+)?$/', (string) $reference, $m) ? (int) $m[1] : null;
    }
}
