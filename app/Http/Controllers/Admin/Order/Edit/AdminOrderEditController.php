<?php

namespace App\Http\Controllers\Admin\Order\Edit;

use App\Enums\Order\DeliveryStatusEnum;
use App\Enums\Order\PaymentStatusEnum;
use App\Enums\Order\PaymentTypeEnum;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Order\Edit\AdminOrderEditResource;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class AdminOrderEditController extends BaseController
{
    /**
     * Show the form for editing one order.
     */
    public function __invoke(Request $request, string $order): Response
    {
        $orderModel = Order::query()
            ->with(['products.product:id,slug', 'media'])
            ->where('order_code', $order)
            ->firstOrFail();

        $this->recordVisit($orderModel, $request);

        return Inertia::render('Admin/Order/Edit/OrderEditView', [
            'order' => (new AdminOrderEditResource($orderModel))->toArray($request),
            'paymentStatuses' => array_values(PaymentStatusEnum::getOptions()),
            'deliveryStatuses' => array_values(DeliveryStatusEnum::getOptions()),
            'paymentTypes' => array_values(PaymentTypeEnum::getOptions()),
            'addressTypeOptions' => array_values(\App\Enums\Address\AddressTypeEnum::getOptions()),
            'products' => $this->productPicker(),
        ]);
    }

    /**
     * The catalogue, for adding a line the order did not arrive with.
     *
     * Prices ride along so the form can seed a new line at today's price the
     * way `OrderProduct::fromProduct()` would — the admin can still overwrite
     * every figure before saving, because an order added to by hand is usually
     * being corrected, not re-sold.
     */
    private function productPicker(): array
    {
        return Product::query()
            ->with('media')
            ->orderBy('name->'.app()->getLocale())
            ->get(['id', 'name', 'slug', 'old_price', 'new_price', 'cost_price', 'profit_price'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->getTranslations('name'),
                'slug' => $product->slug,
                'image' => $product->getFirstMediaUrl('small_image')
                    ?: ($product->getFirstMediaUrl('large_image') ?: null),
                'old_price' => $product->old_price === null ? null : (float) $product->old_price,
                'new_price' => $product->new_price === null ? null : (float) $product->new_price,
                'cost_price' => $product->cost_price === null ? null : (float) $product->cost_price,
                'profit_price' => $product->profit_price === null ? null : (float) $product->profit_price,
            ])
            ->all();
    }

    /**
     * Opening the edit form is an action in its own right: it says who was
     * about to change the order, even when they closed it without saving.
     * Logged the same best-effort way as a view — never at the cost of the page.
     */
    private function recordVisit(Order $order, Request $request): void
    {
        try {
            OrderLog::recordVisit(
                $order->id,
                Auth::id(),
                OrderLog::ACTION_EDIT_VIEWED,
                $request,
            );
        } catch (\Throwable $exception) {
            Log::warning('Order edit visit could not be logged.', [
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
