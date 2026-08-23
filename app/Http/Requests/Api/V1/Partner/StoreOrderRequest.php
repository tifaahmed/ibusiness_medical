<?php

namespace App\Http\Requests\Api\V1\Partner;

use App\Enums\Address\AddressTypeEnum;
use App\Enums\Order\PaymentTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A basket arriving from a storefront, on its way to becoming an order.
 *
 * Notably absent: any price. What the lines cost is read out of the catalogue
 * when the order is written — a total posted by a caller is a total that can be
 * argued with.
 */
class StoreOrderRequest extends FormRequest
{
    /** How many different products one order may carry. */
    public const MAX_ITEMS = 50;

    /** Per line. More than this is an order taken over the phone. */
    public const MAX_QUANTITY = 99;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_full_name' => ['required', 'string', 'max:190'],
            'customer_phone' => ['required', 'string', 'max:32'],
            'customer_address' => ['required', 'string', 'max:1000'],

            /*
             * The delivery address in detail, as far as the storefront collected
             * it. All optional so an older storefront build keeps posting; the
             * free-text `customer_address` stays the one required line.
             */
            'customer_address_type' => ['nullable', Rule::enum(AddressTypeEnum::class)],
            'customer_street' => ['nullable', 'string', 'max:255'],
            'customer_governorate' => ['nullable', 'string', 'max:255'],
            'customer_city' => ['nullable', 'string', 'max:255'],
            'customer_building_number' => ['nullable', 'string', 'max:50'],
            'customer_apartment_number' => ['nullable', 'string', 'max:50'],
            'customer_floor_number' => ['nullable', 'string', 'max:50'],
            'customer_special_mark' => ['nullable', 'string', 'max:500'],

            'membership_number' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payment_type' => ['required', Rule::in(PaymentTypeEnum::values())],

            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*.slug' => ['required', 'string', 'max:190'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.self::MAX_QUANTITY],

            /*
             * The BUYER's address and browser, forwarded by the storefront —
             * `$request->ip()` here is that storefront's server. Accepted only
             * because this endpoint is key-gated: a caller that can present the
             * partner key is already trusted to say who its visitor was.
             */
            'ip_address' => ['nullable', 'string', 'max:45'],
            'user_agent' => ['nullable', 'string', 'max:1000'],
            'source' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * The basket as `slug => quantity`, with repeats of one product merged.
     *
     * @return array<string, int>
     */
    public function items(): array
    {
        $items = [];

        /** @var array<int, array{slug: string, quantity: int}> $rows */
        $rows = $this->input('items', []);

        foreach ($rows as $row) {
            $slug = trim((string) ($row['slug'] ?? ''));

            if ($slug === '') {
                continue;
            }

            $quantity = (int) ($row['quantity'] ?? 0);

            $items[$slug] = min(
                ($items[$slug] ?? 0) + $quantity,
                self::MAX_QUANTITY,
            );
        }

        return $items;
    }
}
