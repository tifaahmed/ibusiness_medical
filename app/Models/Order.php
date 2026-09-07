<?php

namespace App\Models;

use App\Enums\Address\AddressTypeEnum;
use App\Enums\Order\DeliveryStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Order\PaymentStatusEnum;
use App\Enums\Order\PaymentTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Order extends Model implements HasMedia
{
    use InteractsWithMedia;

    /*
     * A deleted order keeps its lines, its receipts and its audit trail, and
     * an admin can put it back. See the `deleted_at` migration for why an
     * order is never dropped outright by the admin screens.
     */
    use SoftDeletes;

    /** Where a storefront order says it came from. */
    public const SOURCE_STOREFRONT = 'storefront';

    /**
     * Delivery was not charged because the basket reached the total the
     * storefront's shop settings say earns free delivery.
     *
     * The only reason there is today. It is a slug rather than a sentence
     * because the admin screens render it in the reader's own language, and
     * because a report grouping orders by it should not be grouping by English
     * prose. `free_delivery_threshold` records the figure that was in force.
     */
    public const DELIVERY_FREE_THRESHOLD_REACHED = 'order_total_reached_threshold';

    /**
     * The receipts a buyer sends after a wallet transfer.
     *
     * Not `singleFile()`, and deliberately uncapped: a transfer sometimes takes
     * several screenshots to evidence, a buyer may pay in instalments, and one
     * sent today does not close the order to one sent tomorrow. Replacing the
     * first silently would lose the half the buyer sent first.
     *
     * The collection is APPEND-ONLY. Nothing in the application deletes or
     * replaces a receipt — not the buyer's page, not the admin's edit form.
     * A receipt is evidence of what somebody claims they paid, and evidence
     * that can be quietly withdrawn is worth much less than evidence that
     * cannot. What an admin decides about it lives in `payment_status` and in
     * `order_logs`, where the decision is attributed and dated.
     */
    public const RECEIPT_COLLECTION = 'receipt';

    protected $fillable = [
        'order_code',
        'user_id',
        'total_paid',
        'total_amount',
        'total_amount_before_discount',
        'delivery_cost',
        'delivery_price',
        'delivery_profit',
        /* Why delivery was not charged, and the basket total that earned it. */
        'delivery_free_reason',
        'free_delivery_threshold',
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
        'order_status',
        'payment_type',
        'cancel_reason',
        'ip_address',
        'user_agent',
        'source',
        'abs_awb',
        'abs_shipped_at',
    ];

    protected function casts(): array
    {
        return [
            'total_paid' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'total_amount_before_discount' => 'decimal:2',
            'delivery_cost' => 'decimal:2',
            'delivery_price' => 'decimal:2',
            'delivery_profit' => 'decimal:2',
            /*
             * Nullable, and the cast leaves null alone — which matters here:
             * null means no threshold was in force when this order was placed,
             * while 0.00 would mean the storefront said every basket earns free
             * delivery. Two different things to read off an old order.
             */
            'free_delivery_threshold' => 'decimal:2',
            'payment_status' => PaymentStatusEnum::class,
            'delivery_status' => DeliveryStatusEnum::class,
            'order_status' => OrderStatusEnum::class,
            'payment_type' => PaymentTypeEnum::class,
            'customer_address_type' => AddressTypeEnum::class,
            'abs_shipped_at' => 'datetime',
        ];
    }

    /**
     * The member who placed this order, when it was placed by somebody signed
     * in on the storefront.
     *
     * Null for every guest checkout, which is most of them and will stay that
     * way: the order code is still the credential that opens an order, and
     * signing in is what turns a browser's list of codes into a history that
     * follows the member to another device. See
     * `Api\V1\Partner\OrderController::claim()`.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
     * Whether this order still needs a FIRST receipt from the buyer.
     *
     * Cash on delivery never does; a wallet transfer does until one arrives.
     * This is the "chase the buyer" question — the badge in the admin list and
     * the prompt on the storefront's order page — and it stops being true the
     * moment anything arrives.
     *
     * It is NOT the question of whether more may be sent: see
     * `acceptsReceipts()`. The two were one method for as long as an order
     * took exactly one receipt, and collapsing them again would put the upload
     * box away the moment the buyer used it once.
     */
    public function awaitingReceipt(): bool
    {
        return $this->payment_type === PaymentTypeEnum::TRANSFER_WALLET
            && $this->getMedia(self::RECEIPT_COLLECTION)->isEmpty();
    }

    /**
     * Whether another receipt may be added to this order, ever.
     *
     * True for the whole life of a wallet-transfer order, however many it
     * already holds: a buyer who pays the balance a week later has something
     * new to send, and an order that stopped accepting evidence the moment it
     * held one piece would send them to the phone instead.
     *
     * Cash on delivery is the one no: money handed to a courier leaves no
     * receipt to send, and filing one against such an order would have an
     * admin looking for a transfer that never happened.
     */
    public function acceptsReceipts(): bool
    {
        return $this->payment_type === PaymentTypeEnum::TRANSFER_WALLET;
    }

    /**
     * Whether this order has already been handed to ABS.
     *
     * The AWB is the whole answer: it exists only because ABS accepted the
     * shipment and gave us a number to track it by. An order that has one must
     * never be offered the ship button again — a second booking is a second
     * courier sent for one parcel, and a second delivery fee.
     */
    public function isShipped(): bool
    {
        return filled($this->abs_awb);
    }

    /**
     * A code the buyer can read down a phone and nobody can guess.
     *
     * Guessable is the risk that matters: the code is what a buyer tracks an
     * order with, and a sequential one would let anybody read the order before
     * and after their own. Ambiguous characters are left out so a code read
     * aloud or copied off a screen does not come back as a different order.
     *
     * The uniqueness check runs `withTrashed` because the unique index on
     * `order_code` does not care about `deleted_at`: a code handed out again
     * because its first order sits in the trash would fail on insert, and
     * would collide with the original the moment somebody restored it.
     */
    public static function generateCode(): string
    {
        $alphabet = 'ACDEFGHJKLMNPQRTUVWXY34679';

        do {
            $code = 'DL-'.collect(range(1, 8))
                ->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])
                ->implode('');
        } while (self::withTrashed()->where('order_code', $code)->exists());

        return $code;
    }

    /**
     * Find an order by the code a buyer typed, however they typed it.
     *
     * Trashed orders stay hidden here on purpose: this is what the buyer's
     * tracking page asks, and a deleted order has no answer to give them. The
     * admin screens that must see one ask `withTrashed` for themselves.
     */
    public static function findByCode(string $code): ?self
    {
        $normalized = Str::upper(trim($code));

        return self::query()->where('order_code', $normalized)->first();
    }
}
