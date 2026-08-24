<?php

namespace App\Http\Controllers\Admin\Order\BulkStatus;

use App\Enums\Order\OrderStatusEnum;
use App\Http\Controllers\Admin\Order\Actions\BulkStatus\BulkUpdateOrderStatusAction;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Order\BulkUpdateOrderStatusRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminOrderBulkStatusController extends BaseController
{
    public function __construct(
        private readonly BulkUpdateOrderStatusAction $bulkAction,
    ) {}

    /**
     * Move the order status of everything the admin ticked on the list page.
     *
     * Redirects back rather than rendering: the caller is the list itself, and
     * `back()` returns to it carrying whatever filters and page it was showing.
     */
    public function __invoke(BulkUpdateOrderStatusRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $status = OrderStatusEnum::from($validated['order_status']);

        try {
            $result = $this->bulkAction->execute(
                array_map('intval', $validated['ids']),
                $status,
                $request,
            );
        } catch (\Throwable $exception) {
            /* The transaction rolled the batch back, so nothing moved — but a
               bulk status change that silently does nothing is exactly what
               gets reported as "the table will not update". */
            Log::error('Failed to bulk-update order status.', [
                'route' => $request->path(),
                'admin_id' => Auth::id(),
                'order_status' => $status->value,
                'order_ids' => $validated['ids'],
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to update the selected orders. Please try again.']);
        }

        /* The admin's own wording for the status, not the enum's English
           label — the flash lands beside a table that is already translated. */
        $label = __('admin.order.order_status_'.$status->value);

        if ($result['changed'] === 0) {
            return back()->with('success', __('admin.order.bulk_status_none', ['status' => $label]));
        }

        return back()->with('success', __('admin.order.bulk_status_done', [
            'count' => $result['changed'],
            'status' => $label,
        ]));
    }
}
