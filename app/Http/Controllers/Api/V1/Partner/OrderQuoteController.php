<?php

namespace App\Http\Controllers\Api\V1\Partner;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Membership;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Support\OrderShipping;
use App\Support\ShopDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What the delivery of a basket would cost, split by parcel — exactly what
 * `OrderController::store` would charge, but writing nothing. Clients draw this
 * in the cart and checkout instead of working a shipping price out themselves.
 */
class OrderQuoteController extends BaseController
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:60'],
            'items.*.slug' => ['required', 'string', 'max:190'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'membership_number' => ['nullable', 'string', 'max:64'],
            'customer_governorate' => ['nullable', 'string', 'max:255'],
        ]);

        $quantities = [];
        foreach ($data['items'] as $row) {
            $quantities[$row['slug']] = ($quantities[$row['slug']] ?? 0) + (int) $row['quantity'];
        }

        $memberPrice = Membership::earnsMemberPrice($data['membership_number'] ?? null);
        $products = Product::query()->with('store')->whereIn('slug', array_keys($quantities))->where('is_purchasable', true)->get()->keyBy('slug');

        $lines = [];
        foreach ($quantities as $slug => $quantity) {
            if ($product = $products->get($slug)) {
                $lines[] = OrderProduct::fromProduct($product, $quantity, $memberPrice);
            }
        }

        $shop = ShopDelivery::current();
        $shipments = OrderShipping::plan(collect($lines), $data['customer_governorate'] ?? null, $shop);
        $totals = OrderShipping::totals($shipments);
        $lang = app()->getLocale() === 'en' ? 'en' : 'ar';

        return response()->json(['data' => [
            'goods_total' => round(array_sum(array_column($shipments, 'subtotal')), 2),
            'delivery_total' => $totals['delivery_price'],
            'shipments' => array_map(fn ($s) => [
                'store_ids' => $s['store_ids'],
                'name' => (string) (($s['store_name'][$lang] ?? null) ?: (reset($s['store_name']) ?: '')),
                'subtotal' => $s['subtotal'],
                'delivery_price' => $s['delivery_price'],
                'free_delivery_threshold' => $s['free_delivery_threshold'],
                'free' => $s['delivery_free_reason'] !== null,
                /* How much more of THIS parcel's goods earns free delivery (null = no such offer / already earned). */
                'remaining_for_free' => ($s['free_delivery_threshold'] !== null && $s['delivery_free_reason'] === null && $s['delivery_price'] > 0)
                    ? round(max(0, $s['free_delivery_threshold'] - $s['subtotal']), 2) : null,
            ], $shipments),
        ]]);
    }
}
