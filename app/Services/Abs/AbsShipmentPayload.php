<?php

namespace App\Services\Abs;

use App\Enums\Order\PaymentTypeEnum;
use App\Models\Order;
use App\Models\OrderProduct;

/**
 * Builds the body ABS receives for one order.
 *
 * This is the ONE place the mapping from our columns to their fields lives.
 * The preview dialog and the submit both build through it, so what an admin
 * approves on screen is byte-for-byte what gets posted — a second mapping
 * written "just for the preview" is a preview that can lie.
 *
 * Nothing here talks to the network, so the whole mapping is testable without
 * a courier: see tests/Feature/Admin/OrderShipTest.php.
 *
 * Three fields are deliberately never sent, because ABS derives them itself
 * and the spec says not to: `awb`, `businessLocationId`, and every branch id
 * (`deliveryBranchId`, `toBranchId`, `currentBranchId`, `fromBranchId`).
 */
class AbsShipmentPayload
{
    /** Forward logistics: our warehouse to the customer. */
    public const SERVICE_DELIVERY = 'DELIVERY';

    /**
     * The body for `POST /api/v1/create-shipment`.
     *
     * `$overrides` is what the admin confirmed or corrected in the preview.
     * `governorate_id` and `city_id` are required there rather than read off
     * the order: our columns hold free Arabic text the buyer typed, ABS wants
     * numeric ids, and no automatic match is trusted enough to send a courier
     * on unseen.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function build(Order $order, array $overrides = []): array
    {
        $shipment = array_filter([
            /* Our order code, so a parcel in their portal can be traced back
               to an order in ours without a lookup table. */
            'ref' => $order->order_code,
            'packageType' => 'PARCEL',

            'consigneeName' => $order->customer_full_name,
            'consigneePhone' => $this->phone($order->customer_phone),
            'consigneeNotes' => $order->notes,

            'governorateId' => $this->intOrNull($overrides['governorate_id'] ?? null),
            'cityId' => $this->intOrNull($overrides['city_id'] ?? null),

            'streetName' => $order->customer_street,
            'buildingNumber' => $order->customer_building_number,
            'floor' => $order->customer_floor_number,
            'apartment' => $order->customer_apartment_number,
            'landmarkNearby' => $order->customer_special_mark,
            /*
             * ABS's own "address resolution pyramid": when `streetName` or
             * `buildingNumber` is present it builds the address text from the
             * structured parts and ignores this. It is sent anyway as the
             * fallback for an order that has only the one free-text field.
             */
            'addressText' => $order->customer_address,

            'contents' => $overrides['contents'] ?? $this->contents($order),
            'noOfPcs' => $this->pieces($order),
            'itemValue' => $this->money($order->total_amount),
            'specialInstructions' => $overrides['special_instructions'] ?? null,

            'productId' => $this->intOrNull(config('services.abs.product_id')),
        ], fn ($value) => $value !== null && $value !== '');

        /*
         * COD is set outside the filter above on purpose: zero is a meaningful
         * value here — "collect nothing, this one is already paid" — and
         * array_filter would strip it, turning a prepaid order into one with
         * no COD instruction at all.
         */
        $shipment['cash'] = array_key_exists('cash', $overrides)
            ? $this->money($overrides['cash'])
            : $this->codAmount($order);

        return array_filter([
            'shipment' => $shipment,
            'cmpService' => self::SERVICE_DELIVERY,
            'subAccountId' => config('services.abs.sub_account') ?: null,
            /*
             * The pickup. With it ABS books the collection as part of creating
             * the shipment and it starts AWAITING_PICKUP; without it the
             * shipment starts NEW and somebody arranges collection by hand.
             */
            'locationId' => $this->intOrNull(config('services.abs.location_id')),
        ], fn ($value) => $value !== null);
    }

    /**
     * What the courier collects at the door, in EGP.
     *
     * Only a cash-on-delivery order has anything to collect. A wallet transfer
     * was settled before the parcel moved, and sending its total here would
     * have the customer charged for the same order twice — so it is explicitly
     * zero rather than absent.
     *
     * Whatever the buyer has already paid comes off, and the result is floored
     * at zero: an overpaid order asks the courier for nothing, it does not ask
     * for a negative amount.
     */
    public function codAmount(Order $order): float
    {
        if ($order->payment_type !== PaymentTypeEnum::COD) {
            return 0.0;
        }

        $outstanding = (float) $order->total_amount - (float) $order->total_paid;

        return $this->money(max($outstanding, 0));
    }

    /**
     * A local Egyptian number as ABS wants it: E.164.
     *
     * Our orders hold what the buyer typed — `01070274943`, sometimes with
     * spaces or dashes. ABS rejects anything that is not `+20…`, so the
     * leading trunk `0` is dropped and the country code prefixed. A number
     * already in international form is left exactly as it is, so a foreign
     * number is never mangled into an Egyptian one.
     */
    public function phone(?string $phone): ?string
    {
        $phone = trim((string) $phone);

        if ($phone === '') {
            return null;
        }

        if (str_starts_with($phone, '+')) {
            return '+'.preg_replace('/\D/', '', substr($phone, 1));
        }

        $digits = preg_replace('/\D/', '', $phone);

        if ($digits === '') {
            return null;
        }

        /* Already carries the country code, just without the plus. */
        if (str_starts_with($digits, '20')) {
            return '+'.$digits;
        }

        return '+20'.ltrim($digits, '0');
    }

    /**
     * What is in the parcel, for the courier's manifest.
     *
     * Read from the archived line names rather than the catalogue: a product
     * renamed or deleted since the sale must not change what the parcel of
     * that sale is described as. Arabic first — the manifest is read in Egypt.
     */
    private function contents(Order $order): ?string
    {
        $names = $order->products
            ->map(function (OrderProduct $line) {
                $translations = $line->getTranslations('name');

                return $translations['ar'] ?? $translations['en'] ?? reset($translations) ?: null;
            })
            ->filter()
            ->unique()
            ->values();

        return $names->isEmpty() ? null : $names->implode('، ');
    }

    private function pieces(Order $order): int
    {
        return max(1, (int) $order->products->sum('quantity'));
    }

    private function money(mixed $value): float
    {
        return round((float) $value, 2);
    }

    private function intOrNull(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
