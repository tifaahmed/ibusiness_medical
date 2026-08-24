<?php

namespace App\Http\Controllers\Admin\Order\ForceDelete;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Order;
use App\Models\OrderLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminOrderForceDeleteController extends BaseController
{
    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_ORDERS;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_ORDERS;
    }

    /**
     * Erase one order for good.
     *
     * Only reachable for an order already in the trash: a permanent delete is
     * the second of two deliberate steps, never something a mis-click on the
     * list can reach.
     *
     * What goes with it: `order_products` cascades, and the receipt media are
     * removed by the media library's own delete. What survives: the rows in
     * `order_logs`, whose `order_id` is nulled rather than cascaded — the
     * record of who did what to this order outlives the order itself, which is
     * the point of an audit trail. The log filed here is written BEFORE the
     * delete and deliberately carries the order code and customer in its
     * payload, because after this call there is no row left to join back to.
     */
    public function __invoke(Request $request, int $order): RedirectResponse
    {
        $orderModel = Order::onlyTrashed()->findOrFail($order);

        $orderId = $orderModel->id;
        $orderCode = $orderModel->order_code;

        try {
            /* Not wrapped in a transaction with the log on purpose: the media
               library deletes files off disk, which no rollback can undo. The
               log is committed first so a failed erase leaves a record of the
               attempt rather than a silent one. */
            OrderLog::record(
                $orderId,
                Auth::id(),
                OrderLog::ACTION_DELETED,
                [
                    'order_code' => $orderCode,
                    'customer_full_name' => $orderModel->customer_full_name,
                    'customer_phone' => $orderModel->customer_phone,
                    'total_amount' => (float) $orderModel->total_amount,
                    'total_paid' => (float) $orderModel->total_paid,
                ],
                ['permanently_deleted' => true],
                $request,
            );

            $orderModel->forceDelete();

            Log::info('Order permanently deleted by admin.', [
                'order_id' => $orderId,
                'order_code' => $orderCode,
                'admin_id' => Auth::id(),
                'ip_address' => $request->ip(),
            ]);

            return redirect()->route('admin.order.trash')
                ->with('success', __('admin.order.force_deleted_success'));
        } catch (\Throwable $exception) {
            Log::error('Failed to permanently delete order.', [
                'order_id' => $orderId,
                'order_code' => $orderCode,
                'admin_id' => Auth::id(),
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => __('admin.order.force_deleted_failed')]);
        }
    }
}
