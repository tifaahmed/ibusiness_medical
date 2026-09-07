<?php

namespace App\Http\Controllers\Admin\Order\Ship;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Order;
use App\Services\Abs\AbsAddressMatcher;
use App\Services\Abs\AbsClient;
use App\Services\Abs\AbsShipmentPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminOrderShipPreviewController extends BaseController
{
    public function __construct(
        private readonly AbsClient $client,
        private readonly AbsAddressMatcher $matcher,
        private readonly AbsShipmentPayload $payload,
    ) {}

    /**
     * Everything the ship dialog shows before anything is booked.
     *
     * JSON rather than Inertia: this is opened from the order page that is
     * already rendered, and a full page visit to preview a payload would lose
     * whatever the admin was looking at.
     *
     * Nothing here books, charges or changes anything. It reads the ABS
     * dropdowns, guesses the destination, and hands back the exact body the
     * submit would post so the admin can check it rather than trust it.
     */
    public function __invoke(Request $request, string $order): JsonResponse
    {
        $orderModel = Order::withTrashed()
            ->with(['products'])
            ->where('order_code', $order)
            ->firstOrFail();

        if ($orderModel->trashed()) {
            return response()->json([
                'message' => 'A deleted order cannot be shipped. Restore it first.',
            ], 422);
        }

        if ($orderModel->isShipped()) {
            return response()->json([
                'message' => "This order was already shipped under AWB {$orderModel->abs_awb}.",
                'abs_awb' => $orderModel->abs_awb,
            ], 422);
        }

        try {
            $destination = $this->resolveDestination($orderModel);
        } catch (\Throwable $exception) {
            Log::error('ABS ship preview could not be built.', [
                'route' => $request->path(),
                'order_id' => $orderModel->id,
                'order_code' => $orderModel->order_code,
                'admin_id' => Auth::id(),
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'message' => $exception->getMessage(),
            ], 502);
        }

        $body = $this->payload->build($orderModel, [
            'governorate_id' => $destination['governorate']['selected_id'],
            'city_id' => $destination['city']['selected_id'],
        ]);

        return response()->json([
            'order' => [
                'order_code' => $orderModel->order_code,
                'customer_full_name' => $orderModel->customer_full_name,
                /* Both spellings of the phone: what the buyer gave us and what
                   ABS will actually be sent, so a mangled normalisation is
                   visible rather than silent. */
                'customer_phone' => $orderModel->customer_phone,
                'normalized_phone' => $this->payload->phone($orderModel->customer_phone),
                'customer_governorate' => $orderModel->customer_governorate,
                'customer_city' => $orderModel->customer_city,
                'customer_address' => $orderModel->customer_address,
                'customer_street' => $orderModel->customer_street,
                'customer_building_number' => $orderModel->customer_building_number,
                'customer_floor_number' => $orderModel->customer_floor_number,
                'customer_apartment_number' => $orderModel->customer_apartment_number,
                'customer_special_mark' => $orderModel->customer_special_mark,
                'payment_type' => $orderModel->payment_type?->value,
                'payment_type_label' => $orderModel->payment_type?->label(),
                'total_amount' => (float) $orderModel->total_amount,
                'total_paid' => (float) $orderModel->total_paid,
                'cod_amount' => $this->payload->codAmount($orderModel),
            ],
            'destination' => $destination,
            /* The literal body of the POST. The dialog shows it verbatim, so
               nothing reaches the courier that the admin could not have read
               on the way past. */
            'payload' => $body,
            'warnings' => $this->warnings($orderModel, $destination),
            'pickup_booked' => filled(config('services.abs.location_id')),
        ]);
    }

    /**
     * The ABS governorate and city lists, with our best guess preselected.
     *
     * The governorate is resolved first because the city list depends on it:
     * ABS scopes cities by governorate, and asking for a city with no
     * governorate would search the whole country for a name that repeats
     * across it.
     *
     * A guess is only ever a preselection. It is the admin's confirmation in
     * the dialog that authorises the booking — see `ShipOrderRequest`, which
     * takes the ids from the form and never from the order.
     *
     * @return array{governorate: array<string, mixed>, city: array<string, mixed>}
     */
    private function resolveDestination(Order $order): array
    {
        $governorates = $this->client->governorates();
        $matchedGovernorate = $this->matcher->match($order->customer_governorate, $governorates);

        $cities = [];
        $matchedCity = null;

        if ($matchedGovernorate !== null) {
            $cities = $this->client->cities($matchedGovernorate['id']);
            $matchedCity = $this->matcher->match($order->customer_city, $cities);
        }

        return [
            'governorate' => [
                'stored' => $order->customer_governorate,
                'options' => $governorates,
                'selected_id' => $matchedGovernorate['id'] ?? null,
                'matched' => $matchedGovernorate !== null,
            ],
            'city' => [
                'stored' => $order->customer_city,
                'options' => $cities,
                'selected_id' => $matchedCity['id'] ?? null,
                'matched' => $matchedCity !== null,
            ],
        ];
    }

    /**
     * What the admin should look at twice before confirming.
     *
     * These do not block the submit — `ShipOrderRequest` does that, and only
     * for the two ids. These are the softer "are you sure" cases: things that
     * are legal to ship but usually mean somebody typed something wrong.
     *
     * @param  array{governorate: array<string, mixed>, city: array<string, mixed>}  $destination
     * @return list<string>
     */
    private function warnings(Order $order, array $destination): array
    {
        $warnings = [];

        if (! $destination['governorate']['matched']) {
            $warnings[] = 'no_governorate_match';
        }

        if (! $destination['city']['matched']) {
            $warnings[] = 'no_city_match';
        }

        if (blank($this->payload->phone($order->customer_phone))) {
            $warnings[] = 'no_phone';
        }

        if (blank($order->customer_address) && blank($order->customer_street)) {
            $warnings[] = 'no_address';
        }

        /* A courier told to collect nothing on an unpaid cash order is a
           delivery that earns nothing — almost always a data problem. */
        if ($this->payload->codAmount($order) <= 0 && (float) $order->total_paid <= 0) {
            $warnings[] = 'no_cod';
        }

        if (blank(config('services.abs.location_id'))) {
            $warnings[] = 'no_pickup_location';
        }

        return $warnings;
    }
}
