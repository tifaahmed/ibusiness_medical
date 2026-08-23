<?php

namespace App\Models;

use App\Enums\Address\AddressTypeEnum;
use App\Enums\Order\DeliveryStatusEnum;
use App\Enums\Order\PaymentStatusEnum;
use App\Enums\Order\PaymentTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Order extends Model implements HasMedia
{
    use InteractsWithMedia;

    /** Where a storefront order says it came from. */
    public const SOURCE_STOREFRONT = 'storefront';

    /**
     * The receipt a buyer sends after a wallet transfer.
     *
     * Not `singleFile()`: a transfer sometimes takes two screenshots to
     * evidence, and replacing the first one silently would lose the half the
     * buyer sent first.
     */
    public const RECEIPT_COLLECTION = 'receipt';

    /** How many receipts one order will accept. A bound, not a target. */
    public const MAX_RECEIPTS = 5;

    protected $fillable = [
        'order_code',
        'total_paid',
        'total_amount',
        'total_amount_before_discount',
        'customer_full_name',
        'customer_phone',
        'customer_address',
        'customer_address_type',
        'customer_street',
        'customer_governorate',
        'customer_city',
        'customer_building_number',
        'customer_apartment_number',
        'customer_floor_number',
        'customer_special_mark',
        'notes',
        'membership_number',
        'payment_status',
        'delivery_status',
        'payment_type',
        'cancel_reason',
        'ip_address',
        'user_agent',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'total_paid' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'total_amount_before_discount' => 'decimal:2',
            'payment_status' => PaymentStatusEnum::class,
            'delivery_status' => DeliveryStatusEnum::class,
            'payment_type' => PaymentTypeEnum::class,
            'customer_address_type' => AddressTypeEnum::class,
        ];
    }

    /**
     * The lines of this order — each a snapshot of a product as it was sold.
     */
    public function products(): HasMany
    {
        return $this->hasMany(OrderProduct::class)->orderBy('id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(OrderLog::class)->latest('id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::RECEIPT_COLLECTION);
    }

    /**
     * The receipts sent against this order, oldest first.
     *
     * @return list<array{id: int, url: string, name: string, uploaded_at: ?string}>
     */
    public function receiptFiles(): array
    {
        return $this->getMedia(self::RECEIPT_COLLECTION)
            ->sortBy('id')
            ->map(fn (Media $media) => [
                'id' => $media->id,
                'url' => $media->getUrl(),
                'name' => $media->file_name,
                'uploaded_at' => $media->created_at?->toAtomString(),
            ])
            ->values()
            ->all();
    }

    /**
     * Whether this order still needs a receipt from the buyer.
     *
     * Cash on delivery never does; a wallet transfer does until one arrives.
     * Asked here rather than in each of the three places that answer it — the
     * storefront's order page, the confirmation, and the upload endpoint.
     */
    public function awaitingReceipt(): bool
    {
        return $this->payment_type === PaymentTypeEnum::TRANSFER_WALLET
            && $this->getMedia(self::RECEIPT_COLLECTION)->isEmpty();
    }

    /**
     * A code the buyer can read down a phone and nobody can guess.
     *
     * Guessable is the risk that matters: the code is what a buyer tracks an
     * order with, and a sequential one would let anybody read the order before
     * and after their own. Ambiguous characters are left out so a code read
     * aloud or copied off a screen does not come back as a different order.
     */
    public static function generateCode(): string
    {
        $alphabet = 'ACDEFGHJKLMNPQRTUVWXY34679';

        do {
            $code = 'DL-'.collect(range(1, 8))
                ->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])
                ->implode('');
        } while (self::query()->where('order_code', $code)->exists());

        return $code;
    }

    /**
     * Find an order by the code a buyer typed, however they typed it.
     */
    public static function findByCode(string $code): ?self
    {
        $normalized = Str::upper(trim($code));

        return self::query()->where('order_code', $normalized)->first();
    }
}
