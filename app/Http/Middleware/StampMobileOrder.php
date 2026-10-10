<?php

namespace App\Http\Middleware;

use App\Support\ShopDelivery;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes the key-gated partner order endpoint safe to reach from a public app.
 *
 * That endpoint believes its caller about the buyer's IP, the delivery fee and
 * where the order came from. A phone can say anything, so here those fields
 * are overwritten with what this server knows: the real client IP and agent,
 * the delivery arrangement from this application's settings, and `mobile_app` as the source.
 * Product prices were never read from the request in the first place.
 */
class StampMobileOrder
{
    public function handle(Request $request, Closure $next): Response
    {
        $delivery = ShopDelivery::current();

        $request->merge([
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'source' => 'mobile_app',
            'delivery_cost' => $delivery['cost'],
            'delivery_price' => $delivery['price'],
            'free_delivery_threshold' => $delivery['threshold'],
        ]);
        $request->request->remove('delivery_profit');

        return $next($request);
    }
}
