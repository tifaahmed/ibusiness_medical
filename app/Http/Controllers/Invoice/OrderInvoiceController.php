<?php

namespace App\Http\Controllers\Invoice;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A printable invoice: one parcel of an order, or the whole order.
 *
 * Public, behind the order code — the code is already the buyer's only
 * credential for the order (random, never sequential). The page is plain HTML
 * laid out for paper ("Save as PDF" from the print dialog gives the file), so
 * Arabic shapes and reads right-to-left without a PDF library.
 */
class OrderInvoiceController extends BaseController
{
    public function __invoke(Request $request, string $orderCode, ?int $shipment = null): View
    {
        $order = Order::query()->where('order_code', $orderCode)->with(['products', 'shipments'])->firstOrFail();
        $lang = $request->query('lang') === 'en' ? 'en' : 'ar';

        $parcels = $order->shipments;
        $only = null;

        if ($shipment !== null) {
            $only = $parcels->firstWhere('id', $shipment);
            abort_if($only === null, 404);
        }

        // A parcel's lines are the order's lines sold by its stores; an old order (no parcels) is one block.
        $blocks = $parcels->isEmpty()
            ? [['title' => null, 'lines' => $order->products, 'delivery' => (float) $order->delivery_price, 'free' => $order->delivery_free_reason]]
            : $parcels->when($only, fn ($c) => $c->where('id', $only->id))->map(fn ($p) => [
                'title' => $p->storeLabel($lang),
                'lines' => $order->products->filter(fn ($l) => in_array($l->store_id, $p->store_ids ?? [], true) || ($l->store_id === null && empty($p->store_ids))),
                'delivery' => (float) $p->delivery_price,
                'free' => $p->delivery_free_reason,
            ])->values()->all();

        return view('invoice.order', [
            'order' => $order,
            'blocks' => $blocks,
            'whole' => $only === null,
            'lang' => $lang,
            'dir' => $lang === 'ar' ? 'rtl' : 'ltr',
            'siteName' => config('app.name'),
        ]);
    }
}
