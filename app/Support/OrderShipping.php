<?php

namespace App\Support;

use App\Models\Governorate;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Store;
use App\Services\Abs\AbsAddressMatcher;
use Illuminate\Support\Collection;

/**
 * Splits a basket by the store that sells each line and prices each part's
 * delivery on its own.
 *
 *  - The store's own price (and free-delivery amount) for the buyer's
 *    governorate wins — see `Store::shippingFor()`.
 *  - A store with no price set falls back to the shop-wide delivery
 *    (`ShopDelivery`), charged once per store parcel.
 *  - Free delivery is judged against THAT store's subtotal, not the basket's.
 *
 * `orders.delivery_*` become the sum of the parts; the parts are archived in
 * `order_shipments`.
 */
class OrderShipping
{
    /**
     * @param  Collection<int, OrderProduct>  $lines  built with OrderProduct::fromProduct (store_id set)
     * @param  array{cost: float, price: float, threshold: ?float}  $shop
     * @return list<array<string, mixed>>
     */
    public static function plan(Collection $lines, ?string $governorateText, array $shop): array
    {
        $single = ShopDelivery::single();
        $storeIds = $lines->pluck('store_id')->filter()->unique()->values()->all();
        $stores = Store::query()->with(['shippingRates', 'shippingGroup'])->whereIn('id', $storeIds)->get()->keyBy('id');
        $governorateId = self::governorateId($governorateText);

        // One parcel for the whole order when the shop turned "one shipping price" on.
        if ($single['enabled'] && $lines->isNotEmpty()) {
            return [self::shipment($lines, $stores, null, $single['price'], 0.0, $single['free_from'], 'single')];
        }

        // Stores sharing a warehouse (one shipping group) travel together; every other store is its own parcel.
        $groups = $lines->groupBy(function (OrderProduct $l) use ($stores) {
            $group = $l->store_id ? $stores->get($l->store_id)?->shipping_group_id : null;

            return $group ? 'g'.$group : 's'.($l->store_id ?? 0);
        });
        $shipments = [];

        foreach ($groups as $key => $group) {
            $store = $stores->get($group->first()->store_id);
            $shippingGroup = str_starts_with($key, 'g') ? $store?->shippingGroup : null;
            $own = $shippingGroup
                ? ['price' => $shippingGroup->price !== null ? (float) $shippingGroup->price : null, 'free_threshold' => $shippingGroup->free_threshold !== null ? (float) $shippingGroup->free_threshold : null]
                : $store?->shippingFor($governorateId);

            if ($own !== null && $own['price'] !== null) {
                $shipments[] = self::shipment($group, $stores, $shippingGroup?->id, $own['price'], 0.0, $own['free_threshold'], $shippingGroup ? 'group' : 'store');
            } else {
                $shipments[] = self::shipment($group, $stores, $shippingGroup?->id, (float) $shop['price'], (float) $shop['cost'], $shop['threshold'], 'shop');
            }
        }

        return $shipments;
    }

    /**
     * One parcel: the lines it carries, priced at `$price` (free once the parcel's goods reach `$threshold`).
     *
     * @param  Collection<int, OrderProduct>  $lines
     * @return array<string, mixed>
     */
    private static function shipment(Collection $lines, Collection $stores, ?int $groupId, float $price, float $cost, ?float $threshold, string $source): array
    {
        $subtotal = round((float) $lines->sum(fn (OrderProduct $l) => (float) $l->line_total), 2);
        $earned = $threshold !== null && $subtotal >= $threshold && $price > 0;
        $charged = $earned ? 0.0 : round($price, 2);
        $ids = $lines->pluck('store_id')->filter()->unique()->values()->all();

        // The parcel's name in each language: its stores' names joined ("A + B").
        $names = [];
        foreach ($lines->unique('store_id') as $l) {
            foreach ((array) ($l->store_name ?: $stores->get($l->store_id)?->getTranslations('title')) as $locale => $title) {
                $names[$locale][] = $title;
            }
        }
        $name = array_map(fn ($list) => implode(' + ', array_unique(array_filter($list))), $names) ?: null;

        return [
            'store_id' => count($ids) === 1 ? $ids[0] : null,
            'store_ids' => $ids,
            'shipping_group_id' => $groupId,
            'store_name' => $name,
            'subtotal' => $subtotal,
            'delivery_cost' => round($cost, 2),
            'delivery_price' => $charged,
            'delivery_profit' => round($charged - $cost, 2),
            'free_delivery_threshold' => $threshold,
            'delivery_free_reason' => $earned ? Order::DELIVERY_FREE_THRESHOLD_REACHED : null,
            'price_source' => $source,
        ];
    }

    /**
     * The order's own delivery columns, as the sum of its shipments.
     *
     * @param  list<array<string, mixed>>  $shipments
     * @return array{delivery_cost: float, delivery_price: float, delivery_profit: float, delivery_free_reason: ?string, free_delivery_threshold: ?float}
     */
    public static function totals(array $shipments): array
    {
        $one = count($shipments) === 1 ? $shipments[0] : null;

        return [
            'delivery_cost' => round(array_sum(array_column($shipments, 'delivery_cost')), 2),
            'delivery_price' => round(array_sum(array_column($shipments, 'delivery_price')), 2),
            'delivery_profit' => round(array_sum(array_column($shipments, 'delivery_profit')), 2),
            // One parcel reads exactly as before; with several, only "every parcel earned it" is worth saying.
            'delivery_free_reason' => $one
                ? $one['delivery_free_reason']
                : (collect($shipments)->every(fn ($s) => $s['delivery_free_reason'] !== null) ? Order::DELIVERY_FREE_THRESHOLD_REACHED : null),
            'free_delivery_threshold' => $one['free_delivery_threshold'] ?? null,
        ];
    }

    /** Which of our governorates the typed text means (null when it does not identify one). */
    public static function governorateId(?string $text): ?int
    {
        if (trim((string) $text) === '') {
            return null;
        }

        $options = [];
        foreach (Governorate::query()->get() as $g) {
            foreach (array_filter((array) $g->getTranslations('name')) as $label) {
                $options[] = ['id' => $g->id, 'label' => (string) $label];
            }
        }

        $hit = app(AbsAddressMatcher::class)->match($text, $options);

        return $hit['id'] ?? null;
    }
}
