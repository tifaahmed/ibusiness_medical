<?php

namespace App\Http\Middleware;

use App\Support\ShopDelivery;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writes every order with the delivery arrangement set in THIS application's
 * settings, whatever the caller says.
 *
 * A storefront used to send its own delivery figures with the order. They now
 * live here, once (`App\Support\ShopDelivery`, edited at /admin/setting), so a
 * caller's copy — the website's, a phone's — is overwritten rather than
 * believed, and the website and the app cannot quote different shipping.
 */
class ApplyShopDelivery
{
    public function handle(Request $request, Closure $next): Response
    {
        $delivery = ShopDelivery::current();

        $request->merge([
            'delivery_cost' => $delivery['cost'],
            'delivery_price' => $delivery['price'],
            'free_delivery_threshold' => $delivery['threshold'],
        ]);
        $request->request->remove('delivery_profit');

        return $next($request);
    }
}
