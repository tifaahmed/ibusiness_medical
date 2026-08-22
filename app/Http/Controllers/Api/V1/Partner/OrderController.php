<?php

namespace App\Http\Controllers\Api\V1\Partner;

use App\Enums\Order\DeliveryStatusEnum;
use App\Enums\Order\PaymentStatusEnum;
use App\Enums\Order\PaymentTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Partner\StoreOrderReceiptRequest;
use App\Http\Requests\Api\V1\Partner\StoreOrderRequest;
use App\Http\Resources\Api\V1\Partner\OrderResource;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\OrderProduct;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Orders placed from a partner storefront (the Deilar site).
 *
 * Key-gated rather than public, for two reasons: these WRITE, and the caller
 * speaks for its visitor — the buyer's own IP address arrives in the request
 * body because `$request->ip()` here is the storefront's server.
 *
 * The order code is what a buyer tracks with and the only thing that opens an
 * order, so it is random rather than sequential — see `Order::generateCode()`.
 */
class OrderController extends Controller
{
    /**
     * Place an order from a basket of slugs and quantities.
     *
     * Prices are read out of the catalogue here and archived onto each line;
     * nothing the caller sends decides what anything costs. The whole write is
     * one transaction, so a basket that half-priced leaves no order behind.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $items = $request->items();

        /** @var \Illuminate\Support\Collection<int, Product> $products */
        $products = Product::query()
            ->whereIn('slug', array_keys($items))
            ->get()
            ->keyBy('slug');

        /*
         * A basket whose products have all been withdrawn is not an order.
         * Reported as a validation failure on `items` so a storefront can say
         * which line went rather than showing a bare 500.
         */
        if ($products->isEmpty()) {
            return response()->json([
                'message' => 'None of these products are available any more.',
                'errors' => ['items' => ['No product in this order is still available.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $order = DB::transaction(function () use ($request, $items, $products) {
                $lines = [];
                $total = 0.0;
                $totalBeforeDiscount = 0.0;

                foreach ($items as $slug => $quantity) {
                    $product = $products->get($slug);

                    /*
                     * A product withdrawn between the basket and the checkout
                     * drops out of the order rather than stopping it: the rest
                     * of the basket is still a sale, and the buyer sees what
                     * was ordered on the confirmation.
                     */
                    if ($product === null) {
                        continue;
                    }

                    $line = OrderProduct::fromProduct($product, $quantity);

                    $total += (float) $line->line_total;
                    $totalBeforeDiscount += (float) ($line->old_price ?? $line->new_price ?? 0) * $quantity;

                    $lines[] = $line;
                }

                $paymentType = PaymentTypeEnum::from((string) $request->input('payment_type'));

                $order = Order::query()->create([
                    'order_code' => Order::generateCode(),
                    'total_amount' => round($total, 2),
                    'total_amount_before_discount' => round($totalBeforeDiscount, 2),
                    /*
                     * Nothing has been paid yet whichever way it will be: cash
                     * on delivery is paid to the courier, and a transfer is
                     * confirmed by an admin once the receipt is checked.
                     */
                    'total_paid' => 0,
                    'customer_full_name' => (string) $request->input('customer_full_name'),
                    'customer_phone' => (string) $request->input('customer_phone'),
                    'customer_address' => (string) $request->input('customer_address'),
                    'notes' => $request->input('notes'),
                    'membership_number' => $request->input('membership_number'),
                    'payment_type' => $paymentType,
                    /*
                     * Both statuses set explicitly rather than left to the
                     * column defaults: a default is applied by the database,
                     * so the model handed back from `create()` would carry
                     * null and the resource would render a status-less order.
                     */
                    'payment_status' => PaymentStatusEnum::PENDING,
                    'delivery_status' => DeliveryStatusEnum::PENDING,
                    'ip_address' => $request->input('ip_address'),
                    'user_agent' => $request->input('user_agent'),
                    'source' => $request->input('source', Order::SOURCE_STOREFRONT),
                ]);

                $order->products()->saveMany($lines);

                return $order;
            });
        } catch (Throwable $exception) {
            Log::error('Storefront order could not be placed.', [
                'route' => $request->path(),
                'items' => $items,
                'payment_type' => $request->input('payment_type'),
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'The order could not be placed.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        OrderLog::record(
            $order->id,
            null,
            OrderLog::ACTION_CREATED,
            null,
            [
                'order_code' => $order->order_code,
                'total_amount' => (float) $order->total_amount,
                'payment_type' => $order->payment_type->value,
                'source' => $order->source,
                /* The buyer's address, not the storefront server's — the log
                   would otherwise record the same IP for every order. */
                'visitor_ip' => $order->ip_address,
            ],
            $request,
        );

        return response()->json([
            'order' => new OrderResource($order->load('products')),
        ], Response::HTTP_CREATED);
    }

    /**
     * One order, by the code the buyer holds.
     *
     * The code is the credential. It is random and eight characters long, and
     * the route is throttled, so guessing one is not a practical way in.
     */
    public function show(Request $request, string $orderCode): JsonResponse
    {
        $order = Order::findByCode($orderCode);

        if ($order === null) {
            return response()->json([
                'message' => 'No order with that code.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'order' => new OrderResource($order->load('products')),
        ]);
    }

    /**
     * Attach the proof of a wallet transfer to an order already placed.
     *
     * Only for orders that are actually waiting on one: a receipt against a
     * cash-on-delivery order is a buyer who has misread the page, and filing it
     * silently would leave an admin looking for a transfer that never happened.
     */
    public function receipt(StoreOrderReceiptRequest $request, string $orderCode): JsonResponse
    {
        $order = Order::findByCode($orderCode);

        if ($order === null) {
            return response()->json([
                'message' => 'No order with that code.',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($order->payment_type !== PaymentTypeEnum::TRANSFER_WALLET) {
            return response()->json([
                'message' => 'This order is paid on delivery and needs no receipt.',
                'errors' => ['receipt' => ['This order is paid on delivery.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /*
         * A cap rather than a single file: a transfer sometimes takes two
         * screenshots to evidence, but an order is not a file store.
         */
        if ($order->getMedia(Order::RECEIPT_COLLECTION)->count() >= Order::MAX_RECEIPTS) {
            return response()->json([
                'message' => 'This order already has all the receipts it can hold.',
                'errors' => ['receipt' => ['Too many receipts on this order.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $order->addMediaFromRequest('receipt')
                ->toMediaCollection(Order::RECEIPT_COLLECTION);
        } catch (Throwable $exception) {
            Log::error('Order receipt could not be stored.', [
                'route' => $request->path(),
                'order_code' => $order->order_code,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'The receipt could not be saved.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        /*
         * The payment stays PENDING: a receipt is a claim, not a confirmation.
         * An admin checks it against the wallet and moves the status.
         */
        OrderLog::record(
            $order->id,
            null,
            OrderLog::ACTION_UPDATED,
            null,
            [
                'receipt_uploaded' => true,
                'reference' => $request->input('reference'),
                'visitor_ip' => $request->input('ip_address'),
            ],
            $request,
        );

        return response()->json([
            'order' => new OrderResource($order->fresh()->load('products')),
        ], Response::HTTP_CREATED);
    }
}
