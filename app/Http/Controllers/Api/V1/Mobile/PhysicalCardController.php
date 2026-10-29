<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Actions\Payment\SendOrderReceipt;
use App\Enums\Address\AddressTypeEnum;
use App\Enums\Order\DeliveryStatusEnum;
use App\Enums\Order\PaymentStatusEnum;
use App\Enums\Order\PaymentTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Partner\OrderResource;
use App\Models\Membership;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\OrderProduct;
use App\Models\User;
use App\Support\PaymentMethods;
use App\Support\PhysicalCard;
use App\Support\ShopDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Ordering physical cards: a primary member with a paid card asks for plastic cards for themselves and their
 * relatives (the same number may be ordered several times). It is an ordinary `orders` row — so payment (Paymob,
 * wallet / Instapay receipts, cash on delivery), the receipt e-mail and the admin order screens all work as they do for
 * the shop — whose lines name the card numbers. Prices come from `PhysicalCard` (/admin/setting), never from the client.
 */
class PhysicalCardController extends Controller
{
    public function __construct(private readonly SendOrderReceipt $sendReceipt) {}

    /** What the screen draws: may this member order, the two prices, and the cards that can be ordered. */
    public function show(Request $request): JsonResponse
    {
        $card = $this->cardOf($request->user());
        $eligibility = PhysicalCard::eligibility($card);

        return response()->json(['data' => [
            'eligible' => $eligibility['ok'],
            'reason' => $eligibility['reason'],
            'price' => PhysicalCard::price(),
            'shipping_price' => PhysicalCard::shipping(),
            'cod_fee' => ShopDelivery::codFee(),
            'max_per_card' => PhysicalCard::MAX_PER_CARD,
            'payment_methods' => PaymentMethods::flags(PaymentMethods::CONTEXT_CARDS),
            'cards' => $eligibility['ok'] ? $this->orderable($card)->map(fn (Membership $m) => [
                'membership_number' => $m->membership_number,
                'name' => $m->user?->name,
                'relationship' => $m->relationship,
                'is_me' => $m->id === $card->id,
            ])->values()->all() : [],
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $card = $this->cardOf($user);
        $eligibility = PhysicalCard::eligibility($card);

        if (! $eligibility['ok']) {
            return response()->json(['reason' => $eligibility['reason'], 'message' => 'Physical cards are for primary members with a paid, current card.'], Response::HTTP_FORBIDDEN);
        }

        $orderable = $this->orderable($card)->keyBy('membership_number');

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.membership_number' => ['required', 'string', Rule::in($orderable->keys()->all())],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.PhysicalCard::MAX_PER_CARD],
            'customer_full_name' => ['required', 'string', 'max:190'],
            'customer_phone' => ['required', 'string', 'max:32'],
            'customer_address' => ['required', 'string', 'max:1000'],
            'customer_email' => ['nullable', 'email:rfc', 'max:190'],
            'customer_address_type' => ['nullable', Rule::enum(AddressTypeEnum::class)],
            'customer_street' => ['nullable', 'string', 'max:255'],
            'customer_governorate' => ['nullable', 'string', 'max:255'],
            'customer_city' => ['nullable', 'string', 'max:255'],
            'customer_building_number' => ['nullable', 'string', 'max:50'],
            'customer_apartment_number' => ['nullable', 'string', 'max:50'],
            'customer_floor_number' => ['nullable', 'string', 'max:50'],
            'customer_special_mark' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payment_type' => ['required', Rule::in(PaymentTypeEnum::values()), function (string $attribute, mixed $value, \Closure $fail) {
                if (! in_array($value, PaymentMethods::orderTypes(PaymentMethods::CONTEXT_CARDS), true)) {
                    $fail('This payment method is not available right now.');
                }
            }],
        ]);

        // One line per card number, copies added together (the same number can be bought several times).
        $copies = [];

        foreach ($data['items'] as $row) {
            $copies[$row['membership_number']] = min(($copies[$row['membership_number']] ?? 0) + (int) $row['quantity'], PhysicalCard::MAX_PER_CARD);
        }

        $price = PhysicalCard::price();
        $shipping = PhysicalCard::shipping();
        $paymentType = PaymentTypeEnum::from($data['payment_type']);
        $codFee = $paymentType === PaymentTypeEnum::COD ? ShopDelivery::codFee() : 0.0;

        try {
            $order = DB::transaction(function () use ($data, $copies, $orderable, $price, $shipping, $paymentType, $codFee, $user, $card, $request) {
                $subtotal = round(array_sum($copies) * $price, 2);

                $order = Order::query()->create([
                    'order_code' => Order::generateCode(),
                    'user_id' => $user->id,
                    'total_amount' => round($subtotal + $shipping + $codFee, 2),
                    'total_amount_before_discount' => round($subtotal + $shipping + $codFee, 2),
                    'cod_fee' => $codFee,
                    // Shipping is what the admin set for it; the courier's own cost is not tracked for this order.
                    'delivery_cost' => 0,
                    'delivery_price' => $shipping,
                    'delivery_profit' => $shipping,
                    'total_paid' => 0,
                    'customer_full_name' => $data['customer_full_name'],
                    'customer_phone' => $data['customer_phone'],
                    'customer_email' => $data['customer_email'] ?? null,
                    'customer_address' => $data['customer_address'],
                    'customer_address_type' => ! empty($data['customer_address_type']) ? AddressTypeEnum::from($data['customer_address_type']) : null,
                    'customer_street' => $data['customer_street'] ?? null,
                    'customer_governorate' => $data['customer_governorate'] ?? null,
                    'customer_city' => $data['customer_city'] ?? null,
                    'customer_building_number' => $data['customer_building_number'] ?? null,
                    'customer_apartment_number' => $data['customer_apartment_number'] ?? null,
                    'customer_floor_number' => $data['customer_floor_number'] ?? null,
                    'customer_special_mark' => $data['customer_special_mark'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'membership_number' => $card->membership_number,
                    'payment_type' => $paymentType,
                    'payment_status' => PaymentStatusEnum::PENDING,
                    'delivery_status' => DeliveryStatusEnum::PENDING,
                    'ip_address' => $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                    'source' => PhysicalCard::SOURCE,
                ]);

                $lines = [];

                foreach ($copies as $number => $quantity) {
                    $who = (string) $orderable->get($number)?->user?->name;
                    $lines[] = new OrderProduct([
                        'name' => ['ar' => "كارت فيزيكال — {$number} — {$who}", 'en' => "Physical card — {$number} — {$who}"],
                        'slug' => 'physical-card-'.$number,
                        'quantity' => $quantity,
                        'old_price' => null,
                        'new_price' => $price,
                        'line_total' => round($price * $quantity, 2),
                    ]);
                }

                $order->products()->saveMany($lines);

                return $order;
            });
        } catch (Throwable $exception) {
            Log::error('Physical card order could not be placed.', ['user_id' => $user->id, 'message' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()]);

            return response()->json(['message' => 'The order could not be placed.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        OrderLog::record($order->id, null, OrderLog::ACTION_CREATED, null, [
            'order_code' => $order->order_code,
            'source' => PhysicalCard::SOURCE,
            'cards' => $copies,
            'unit_price' => $price,
            'shipping_price' => $shipping,
            'total_amount' => (float) $order->total_amount,
            'payment_type' => $order->payment_type->value,
            'membership_number' => $order->membership_number,
        ], $request);

        // Online payment is opened by the client with POST /mobile/orders/{code}/pay, like any shop order; the others get their receipt now.
        if (! $order->isOnlinePayment()) {
            $this->sendReceipt->handle($order, null, $request);
        }

        return response()->json(['order' => new OrderResource($order->fresh()->load('products'))], Response::HTTP_CREATED);
    }

    private function cardOf(User $user): ?Membership
    {
        return $user->memberships()->latest('id')->first();
    }

    /**
     * The card numbers that may be ordered: the holder's own and every APPROVED, active relative on it.
     *
     * @return \Illuminate\Support\Collection<int, Membership>
     */
    private function orderable(Membership $card): \Illuminate\Support\Collection
    {
        return collect([$card->loadMissing('user')])->merge(
            $card->relatives()->with('user')->where('is_active', true)->where('approval_status', 'approved')->orderBy('id')->get(),
        );
    }
}
