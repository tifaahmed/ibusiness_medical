<?php

namespace App\Http\Controllers\Admin\Order\Show;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Order\Show\AdminOrderShowResource;
use App\Models\Order;
use App\Models\OrderLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class AdminOrderShowController extends BaseController
{
    /**
     * Display one order, everything attached to it, and its audit trail.
     *
     * Bound by `order_code` rather than id: it is the key the order is known by
     * on the phone, in the table and on the buyer's side, so an admin reading a
     * URL out loud is reading the same code the customer holds.
     *
     * `orders` carries no `created_by`, so — as on the list — there is no row
     * ownership to scope by yet and either permission opens the order.
     *
     * Trashed orders open here too. The trash page offers a "view" beside its
     * restore and erase, and an admin deciding between those two needs to read
     * the order first — sending them to a 404 would make the decision blind.
     * The page marks it as deleted; the edit form still refuses.
     */
    public function __invoke(Request $request, string $order): Response
    {
        $orderModel = Order::withTrashed()
            ->with(['products.product:id,slug', 'logs.admin:id,name,email', 'media'])
            ->where('order_code', $order)
            ->firstOrFail();

        $this->recordVisit($orderModel, $request);

        return Inertia::render('Admin/Order/Show', [
            'order' => (new AdminOrderShowResource($orderModel))->toArray($request),
        ]);
    }

    /**
     * File the visit itself in `order_logs`.
     *
     * A read is worth recording — who opened a customer's phone number and
     * address, and when — but it must never cost the admin the page: a log
     * table that is full, locked or missing is a reason to alert, not a reason
     * to refuse to show an order.
     */
    private function recordVisit(Order $order, Request $request): void
    {
        try {
            OrderLog::recordVisit(
                $order->id,
                Auth::id(),
                OrderLog::ACTION_VIEWED,
                $request,
            );
        } catch (\Throwable $exception) {
            Log::warning('Order view could not be logged.', [
                'route' => $request->path(),
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'admin_id' => Auth::id(),
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }
}
