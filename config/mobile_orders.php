<?php

/*
 * Delivery for orders placed from the mobile app.
 *
 * The partner order endpoint takes the delivery fee from its caller, which is
 * safe there because that caller is a key-gated server. The app is a public
 * client, so its figures are never read: these are applied instead.
 */
return [
    // What the courier costs the shop, and what the buyer is charged.
    'delivery_cost' => (float) env('MOBILE_ORDER_DELIVERY_COST', 35),
    'delivery_price' => (float) env('MOBILE_ORDER_DELIVERY_PRICE', 35),
    // A basket (after member prices) at or above this ships free. null = never free.
    'free_delivery_threshold' => env('MOBILE_ORDER_FREE_DELIVERY_THRESHOLD', 500) === null
        ? null
        : (float) env('MOBILE_ORDER_FREE_DELIVERY_THRESHOLD', 500),
];
