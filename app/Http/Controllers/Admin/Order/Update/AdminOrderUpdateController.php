<?php

namespace App\Http\Controllers\Admin\Order\Update;

use App\Http\Controllers\Admin\Order\Actions\Update\UpdateOrderAction;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Order\UpdateOrderRequest;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminOrderUpdateController extends BaseController
{
    public function __construct(private readonly UpdateOrderAction $updateAction) {}

    /**
     * Update one order, its lines and its statuses.
     */
    public function __invoke(UpdateOrderRequest $request, string $order): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $orderModel = Order::query()
                ->with('products')
                ->where('order_code', $order)
                ->firstOrFail();

            $updated = $this->updateAction->execute($orderModel, $validated, $request);

            return redirect()->route('admin.order.show', $updated->order_code)
                ->with('success', 'Order updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            /* A refused edit (too many receipts) is the form doing its job,
               not a system failure — let it flow back into the field errors
               instead of the generic catch below. */
            throw $exception;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            /*
             * The edit is rolled back by the action's transaction, so the order
             * is untouched — but the attempt itself is worth a line in the app
             * log: a failing save on a paid order is the sort of thing that
             * gets reported as "the status will not stick".
             */
            Log::error('Failed to update order.', [
                'route' => $request->path(),
                'order_code' => $order,
                'admin_id' => Auth::id(),
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()
                ->withErrors(['error' => 'Failed to update order. Please try again.'])
                ->withInput();
        }
    }
}
