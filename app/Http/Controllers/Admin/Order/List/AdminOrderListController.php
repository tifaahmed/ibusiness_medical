<?php

namespace App\Http\Controllers\Admin\Order\List;

use App\Enums\Order\DeliveryStatusEnum;
use App\Enums\Order\PaymentStatusEnum;
use App\Enums\Order\PaymentTypeEnum;
use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Order\List\AdminOrderListCollection;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminOrderListController extends BaseController
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
     * Display a listing of orders.
     *
     * `orders` carries no created_by column, so the creator-scoping used by
     * other modules does not apply here yet — either permission opens the
     * full list until row ownership is defined.
     */
    public function __invoke(Request $request): Response
    {
        $filters = $this->getFilters($request);

        $orders = Order::query()
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
            ->when(! empty($filters['payment_type']), fn ($q) => $q->where('payment_type', $filters['payment_type']))
            ->latest()
            ->paginate($request->input('per_page', 15))->withQueryString();

        return Inertia::render('Admin/Order/List', [
            'orders' => (new AdminOrderListCollection($orders))->toArray($request),
            'filters' => $filters,
        ]);
    }

    /**
     * Get filters from request. Status filters are validated against their
     * enums so a tampered query string cannot error the page.
     */
    protected function getFilters(Request $request): array
    {
        $paymentStatus = $request->input('payment_status');
        $deliveryStatus = $request->input('delivery_status');
        $paymentType = $request->input('payment_type');

        return [
            'search' => $request->input('search', ''),
            'payment_status' => in_array($paymentStatus, PaymentStatusEnum::values(), true) ? $paymentStatus : '',
            'delivery_status' => in_array($deliveryStatus, DeliveryStatusEnum::values(), true) ? $deliveryStatus : '',
            'payment_type' => in_array($paymentType, PaymentTypeEnum::values(), true) ? $paymentType : '',
        ];
    }
}
