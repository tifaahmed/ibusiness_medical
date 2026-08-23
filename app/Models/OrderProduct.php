<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * One line of an order: a product as it was sold, not as it is now.
 *
 * Everything a receipt needs is copied onto the row — see the migration for
 * why. Reading a line's price back through `product()` is always wrong.
 */
class OrderProduct extends Model
{
    use HasTranslations;

    /**
     * The name is archived as the whole translation map, so an order reads
     * back in whichever language it is opened in.
     *
     * @var array<int, string>
     */
    public $translatable = ['name'];

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'product_id',
        'name',
        'slug',
        'image',
        'quantity',
        'old_price',
        'new_price',
        'line_total',
        'cost_price',
        'profit_price',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'old_price' => 'decimal:2',
            'new_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'profit_price' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The catalogue row this line was sold from, while it still exists.
     *
     * Nullable on purpose: a deleted product must not take the order's history
     * with it.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Build a line from a product and a quantity, pricing it at today's price.
     *
     * The one place a line is created, so an order taken over the API and one
     * entered by an admin archive the same fields.
     *
     * `new_price` on a product is the MEMBER price, so `$memberPrice` decides
     * which of the two the buyer is charged: a buyer with no valid card pays
     * the marked-down-from price and the line archives no markdown, because
     * none was given. The argument has no default on purpose — an order priced
     * without deciding is how a discount gets handed out for free.
     */
    public static function fromProduct(Product $product, int $quantity, bool $memberPrice): self
    {
        $markdown = self::markdownPrice($product);
        $memberUnit = $product->new_price ?? $product->old_price;

        /*
         * Falling back to the member price when there is no markdown is not a
         * loophole: a product that has only ever had one price has no member
         * discount to withhold. Going through `markdownPrice()` also means a
         * mis-keyed `old_price` below the selling price can never make the
         * full price the CHEAPER of the two.
         */
        $unitPrice = $memberPrice ? $memberUnit : ($markdown ?? $memberUnit);
        $price = $unitPrice === null ? 0.0 : (float) $unitPrice;

        return new self([
            'product_id' => $product->id,
            /* The raw attribute, not the accessor: `getTranslations()` is the
               whole map, while `$product->name` is only the request's locale. */
            'name' => $product->getTranslations('name'),
            'slug' => $product->slug,
            'image' => $product->getFirstMediaUrl('small_image')
                ?: ($product->getFirstMediaUrl('large_image') ?: null),
            'quantity' => $quantity,
            /* Only when it is a real markdown — an `old_price` at or above the
               selling price is a data slip, and archiving it would print a
               struck-through price on the receipt for a product nobody
               discounted. A full-price line archives none either: the buyer
               was charged that figure, so striking it through would show them
               a saving they did not get. */
            'old_price' => $memberPrice ? $markdown : null,
            'new_price' => $unitPrice,
            'line_total' => round($price * $quantity, 2),
            'cost_price' => $product->cost_price,
            'profit_price' => $product->profit_price,
        ]);
    }

    /**
     * The struck-through price, or nothing when this was not a markdown.
     */
    private static function markdownPrice(Product $product): ?string
    {
        $old = $product->old_price === null ? null : (float) $product->old_price;
        $new = $product->new_price === null ? null : (float) $product->new_price;

        if ($old === null || $new === null || $old <= 0.0 || $new <= 0.0 || $new >= $old) {
            return null;
        }

        return (string) $product->old_price;
    }
}
