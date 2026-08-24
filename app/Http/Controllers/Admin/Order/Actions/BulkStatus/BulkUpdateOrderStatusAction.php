<?php

namespace App\Http\Controllers\Admin\Order\Actions\BulkStatus;

use App\Enums\Order\OrderStatusEnum;
use App\Models\Order;
use App\Models\OrderLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BulkUpdateOrderStatusAction
{
    /**
     * Move a set of orders to one outcome, and file what moved.
     *
     * The write and its audit rows share a transaction for the same reason a
     * single edit does: a batch of orders that changed with nothing saying who
     * changed them is worse than a batch that failed, because only one of the
     * two gets noticed.
     *
     * Orders already at the target are left out of the update AND out of the
     * log — a bulk click on a mixed page would otherwise file an "unchanged"
     * row against every order that was already there, and an audit trail that
     * records non-changes is one nobody reads.
     *
     * @param  list<int>  $ids
     * @return array{changed: int, skipped: int}
     *
     * @throws \Throwable
     */
    public function execute(array $ids, OrderStatusEnum $status, Request $request): array
    {
        return DB::transaction(function () use ($ids, $status, $request) {
            /* Locked for the read-then-write: two admins moving overlapping
               selections at once would otherwise both log a change from the
               same "before", and one of the two logs would be fiction. */
            $orders = Order::query()
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get(['id', 'order_code', 'order_status']);

            $moving = $orders->reject(fn (Order $order) => $order->order_status === $status);

            if ($moving->isEmpty()) {
                return ['changed' => 0, 'skipped' => $orders->count()];
            }

            Order::query()
                ->whereIn('id', $moving->pluck('id'))
                ->update([
                    'order_status' => $status->value,
                    'updated_at' => now(),
                ]);

            $adminId = Auth::id();

            foreach ($moving as $order) {
                OrderLog::record(
                    $order->id,
                    $adminId,
                    OrderLog::ACTION_ORDER_STATUS_CHANGED,
                    ['order_status' => $order->order_status?->value],
                    ['order_status' => $status->value, 'bulk' => true],
                    $request,
                );
            }

            Log::info('Order status changed in bulk by admin.', [
                'admin_id' => $adminId,
                'order_status' => $status->value,
                'order_codes' => $moving->pluck('order_code')->all(),
                'changed' => $moving->count(),
                'skipped' => $orders->count() - $moving->count(),
            ]);

            return [
                'changed' => $moving->count(),
                'skipped' => $orders->count() - $moving->count(),
            ];
        });
    }
}
