<?php

namespace App\Http\Controllers\Admin\Order\Delete;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Order;
use App\Models\OrderLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminOrderDeleteController extends BaseController
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
     * Move one order to the trash.
     *
     * Bound by `order_code`, the key the order is known by in every other admin
     * URL — an admin who can read the code off the row can read it off this
     * request too.
     *
     * The delete and its audit row go in one transaction for the same reason
     * the edit does: an order that left the list with nothing saying who took
     * it out is the one case nobody can notice afterwards.
     *
     * `orders` carries no `created_by`, so there is no row ownership to scope
     * by yet and either permission opens the action.
     */
    public function __invoke(Request $request, string $order): RedirectResponse
    {
        $orderModel = Order::query()->where('order_code', $order)->firstOrFail();

        try {
            DB::transaction(function () use ($orderModel, $request) {
                /* Filed before the delete: the log row is what the trash page
                   and the order's own timeline read back, and writing it after
                   would leave a failed delete claiming to have happened. */
                OrderLog::record(
                    $orderModel->id,
                    Auth::id(),
                    OrderLog::ACTION_DELETED,
                    ['deleted_at' => null],
                    ['deleted_at' => now()->toDateTimeString()],
                    $request,
                );

                $orderModel->delete();
            });

            Log::info('Order moved to trash by admin.', [
                'order_id' => $orderModel->id,
                'order_code' => $orderModel->order_code,
                'admin_id' => Auth::id(),
                'ip_address' => $request->ip(),
            ]);

            return redirect()->route('admin.order.list')
                ->with('success', __('admin.order.deleted_success'));
        } catch (\Throwable $exception) {
            Log::error('Failed to move order to trash.', [
                'order_id' => $orderModel->id,
                'order_code' => $orderModel->order_code,
                'admin_id' => Auth::id(),
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => __('admin.order.deleted_failed')]);
        }
    }
}
