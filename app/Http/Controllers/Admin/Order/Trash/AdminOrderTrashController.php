<?php

namespace App\Http\Controllers\Admin\Order\Trash;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Admin\Order\List\AdminOrderListController;
use App\Http\Resources\Admin\Order\List\AdminOrderListCollection;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The deleted orders, newest deletion first.
 *
 * Extends the list controller so the search box and the four status filters
 * behave here exactly as they do on the list — same validation against the
 * enums, same handling of a tampered query string. The only differences are
 * the scope (`onlyTrashed`), the ordering (by when it was deleted, which is
 * what somebody looking for "the one I just deleted" is sorting by) and the
 * page it renders.
 */
class AdminOrderTrashController extends AdminOrderListController
{
    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_ORDERS;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_ORDERS;
    }

    public function __invoke(Request $request): Response
    {
        $filters = $this->getFilters($request);

        $orders = Order::onlyTrashed()
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $term = '%'.$filters['search'].'%';
                $q->where(function ($query) use ($term) {
                    $query->where('order_code', 'like', $term)
                        ->orWhere('customer_full_name', 'like', $term)
                        ->orWhere('customer_phone', 'like', $term)
                        ->orWhere('membership_number', 'like', $term);
                });
            })
            ->when(! empty($filters['payment_status']), fn ($q) => $q->where('payment_status', $filters['payment_status']))
            ->when(! empty($filters['delivery_status']), fn ($q) => $q->where('delivery_status', $filters['delivery_status']))
            ->when(! empty($filters['order_status']), fn ($q) => $q->where('order_status', $filters['order_status']))
            ->when(! empty($filters['payment_type']), fn ($q) => $q->where('payment_type', $filters['payment_type']))
            ->when(! empty($filters['created_from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['created_from']))
            ->when(! empty($filters['created_to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['created_to']))
            ->orderByDesc('deleted_at')
            ->paginate($request->input('per_page', 15))->withQueryString();

        return Inertia::render('Admin/Order/Trash', [
            'orders' => (new AdminOrderListCollection($orders))->toArray($request),
            'filters' => $filters,
        ]);
    }
}
