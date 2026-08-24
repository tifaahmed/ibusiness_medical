<?php

namespace App\Http\Controllers\Admin\Order\Restore;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Order;
use App\Models\OrderLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminOrderRestoreController extends BaseController
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
     * Put one order back on the list.
     *
     * Bound by id rather than `order_code`: the trash page is the only screen
     * that offers this, and it holds the ids it just listed. A restore is
     * filed like the delete was, so the order's timeline reads as a round trip
     * rather than an unexplained reappearance.
     */
    public function __invoke(Request $request, int $order): RedirectResponse
    {
        $orderModel = Order::onlyTrashed()->findOrFail($order);

        try {
            DB::transaction(function () use ($orderModel, $request) {
                OrderLog::record(
                    $orderModel->id,
                    Auth::id(),
                    OrderLog::ACTION_UPDATED,
                    ['deleted_at' => $orderModel->deleted_at?->toDateTimeString()],
                    ['deleted_at' => null, 'restored' => true],
                    $request,
                );

                $orderModel->restore();
            });

            Log::info('Order restored from trash by admin.', [
                'order_id' => $orderModel->id,
                'order_code' => $orderModel->order_code,
                'admin_id' => Auth::id(),
                'ip_address' => $request->ip(),
            ]);

            return redirect()->route('admin.order.trash')
                ->with('success', __('admin.order.restored_success'));
        } catch (\Throwable $exception) {
            Log::error('Failed to restore order.', [
                'order_id' => $orderModel->id,
                'order_code' => $orderModel->order_code,
                'admin_id' => Auth::id(),
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => __('admin.order.restored_failed')]);
        }
    }
}
