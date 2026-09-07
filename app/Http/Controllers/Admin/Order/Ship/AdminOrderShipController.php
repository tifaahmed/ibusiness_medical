<?php

namespace App\Http\Controllers\Admin\Order\Ship;

use App\Http\Controllers\Admin\Order\Actions\Ship\ShipOrderAction;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Order\ShipOrderRequest;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminOrderShipController extends BaseController
{
    public function __construct(
        private readonly ShipOrderAction $shipAction,
    ) {}

    /**
     * Book one order with ABS, on the strength of what the admin confirmed in
     * the preview dialog.
     *
     * A failure comes back as a field error rather than a flash: the dialog
     * stays open on it, so an admin who picked the wrong city can fix that one
     * thing and press again, instead of finding the whole page reloaded and
     * their corrections gone.
     */
    public function __invoke(ShipOrderRequest $request, string $order): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $orderModel = Order::withTrashed()
                ->with('products')
                ->where('order_code', $order)
                ->firstOrFail();

            $shipped = $this->shipAction->execute($orderModel, $validated, $request);

            return redirect()
                ->route('admin.order.show', $shipped->order_code)
                ->with('success', "Order shipped with ABS. AWB {$shipped->abs_awb}.");
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            /*
             * Everything that can go wrong here has already been logged with
             * its own context — a refusal by ABS in `AbsClient`, a write that
             * failed after a successful booking in `ShipOrderAction`. This
             * line records the attempt itself: who tried to ship what, and
             * when, which is the trail somebody follows when a customer says
             * their parcel never moved.
             */
            Log::error('Failed to ship order with ABS.', [
                'route' => $request->path(),
                'order_code' => $order,
                'admin_id' => Auth::id(),
                'governorate_id' => $validated['governorate_id'] ?? null,
                'city_id' => $validated['city_id'] ?? null,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            /* The message is ABS's own where there is one ("consigneeName
               should not be empty" names the field to fix); a generic
               "shipping failed" would send the admin back to us to find out
               what the courier actually objected to. */
            return back()
                ->withErrors(['ship' => $exception->getMessage()])
                ->withInput();
        }
    }
}
