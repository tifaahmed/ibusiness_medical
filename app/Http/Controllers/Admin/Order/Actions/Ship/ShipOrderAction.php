<?php

namespace App\Http\Controllers\Admin\Order\Actions\Ship;

use App\Enums\Order\DeliveryStatusEnum;
use App\Models\Order;
use App\Models\OrderLog;
use App\Services\Abs\AbsClient;
use App\Services\Abs\AbsShipmentPayload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ShipOrderAction
{
    public function __construct(
        private readonly AbsClient $client,
        private readonly AbsShipmentPayload $payload,
    ) {}

    /**
     * Hand one order to ABS and record that it went.
     *
     * The ordering here is the whole design, and it is the opposite way round
     * from {@see \App\Http\Controllers\Admin\Order\Actions\Update\UpdateOrderAction}:
     *
     *  1. Refuse an order that already has an AWB. Booking twice sends two
     *     couriers for one parcel and bills two delivery fees, and the button
     *     is one double-click away from doing exactly that.
     *  2. Call ABS OUTSIDE any transaction. An HTTP call that can hang for the
     *     full timeout must not hold row locks open, and a refusal from the
     *     courier must leave our order untouched rather than rolled back from
     *     a half-applied state.
     *  3. Only once an AWB is in hand, write the order and its audit rows in
     *     one transaction — so an order can never claim to have shipped
     *     without a log saying who shipped it and under what number.
     *
     * The one failure this cannot make safe is a booking ABS accepts and we
     * fail to store: the parcel is on its way with an AWB nobody here has. It
     * is logged at error level with everything needed to reconcile it by hand,
     * because a silent one is unfindable.
     *
     * @param  array<string, mixed>  $validated  What the admin confirmed in the preview.
     *
     * @throws RuntimeException when the order cannot or must not be shipped.
     */
    public function execute(Order $order, array $validated, Request $request): Order
    {
        $this->guard($order);

        $body = $this->payload->build($order, $validated);

        $awb = $this->client->createShipment($body);

        try {
            return DB::transaction(function () use ($order, $awb, $body, $request) {
                $previousStatus = $order->delivery_status?->value;

                $order->update([
                    'abs_awb' => $awb,
                    'abs_shipped_at' => now(),
                    /*
                     * The parcel is booked but not yet moving — "processing" is
                     * what that is. It is not pushed to "on-delivery": that is
                     * the courier's news to tell, and inventing it here would
                     * have the order claim a state ABS has not reported.
                     */
                    'delivery_status' => DeliveryStatusEnum::PROCESSING->value,
                ]);

                OrderLog::record(
                    $order->id,
                    Auth::id(),
                    OrderLog::ACTION_SHIPPED,
                    ['abs_awb' => null],
                    [
                        'abs_awb' => $awb,
                        'carrier' => 'ABS',
                        /* The address the parcel was actually booked to, not
                           the text on the order — this is the record of what
                           the admin confirmed, and the only way to tell later
                           whether a misdelivery was a bad match or a bad
                           address. */
                        'governorate_id' => $body['shipment']['governorateId'] ?? null,
                        'city_id' => $body['shipment']['cityId'] ?? null,
                        'cash' => $body['shipment']['cash'] ?? null,
                        /* Whether a pickup was booked with it, or whether
                           somebody still has to arrange collection. */
                        'pickup_location_id' => $body['locationId'] ?? null,
                    ],
                    $request,
                );

                if ($previousStatus !== DeliveryStatusEnum::PROCESSING->value) {
                    OrderLog::record(
                        $order->id,
                        Auth::id(),
                        OrderLog::ACTION_DELIVERY_STATUS_CHANGED,
                        ['delivery_status' => $previousStatus],
                        ['delivery_status' => DeliveryStatusEnum::PROCESSING->value],
                        $request,
                    );
                }

                Log::info('Order shipped with ABS.', [
                    'order_id' => $order->id,
                    'order_code' => $order->order_code,
                    'admin_id' => Auth::id(),
                    'abs_awb' => $awb,
                ]);

                return $order->refresh();
            });
        } catch (\Throwable $exception) {
            /*
             * The courier has the parcel; we failed to write it down. Nothing
             * can be rolled back on their side, so this must never be retried
             * automatically — the AWB is here in the log to be reconciled.
             */
            Log::error('ABS accepted the shipment but the order could not be updated.', [
                'route' => $request->path(),
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'admin_id' => Auth::id(),
                'abs_awb' => $awb,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw new RuntimeException(
                "ABS booked this shipment as {$awb}, but the order could not be updated. "
                .'Do NOT ship it again — record the AWB by hand and check the ABS portal.',
                0,
                $exception,
            );
        }
    }

    /**
     * The states an order must not be shipped from.
     *
     * @throws RuntimeException
     */
    private function guard(Order $order): void
    {
        if ($order->isShipped()) {
            throw new RuntimeException(
                "This order was already shipped under AWB {$order->abs_awb}."
            );
        }

        /* A trashed order is one somebody decided should not exist. Sending a
           courier for it would make that decision real in the world. */
        if ($order->trashed()) {
            throw new RuntimeException('A deleted order cannot be shipped. Restore it first.');
        }
    }
}
