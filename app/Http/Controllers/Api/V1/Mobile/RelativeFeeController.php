<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Actions\Payment\SendOrderReceipt;
use App\Enums\FamilyMember\ApprovalStatusEnum;
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
use App\Support\RelativeFee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Paying a relative's fee (5 EGP a month, paid for the whole period at once). The relative is created first through
 * /family/members or /family/children (waiting); this makes an ordinary `orders` row (`source = relative_fee`, one line
 * per relative, the `membership` payment context) and, once the payment is ACCEPTED (a verified Paymob callback, or an
 * admin confirming a receipt), `RelativeFee::activateFor` switches the relatives on with an expiry date a period ahead.
 * Prices come from `RelativeFee` (/admin/setting), never from the client.
 */
class RelativeFeeController extends Controller
{
    public function __construct(private readonly SendOrderReceipt $sendReceipt) {}

    /**
     * What the screen draws: the fee plans, the ways to pay, who can be paid for. A PRIMARY card sees every relative (waiting, active,
     * expired); a RELATIVE who signed in sees only themself. `can_pay` is false (with a `reason`) when the card behind is a trial or has
     * lapsed — then a relative has to buy a normal plan of their own instead.
     */
    public function show(Request $request): JsonResponse
    {
        $card = $this->cardOf($request->user());
        $reason = $this->refusalFor($card);

        return response()->json(['data' => [
            'fee' => RelativeFee::fee($card),
            'can_pay' => $reason === null,
            'reason' => $reason,
            'payment_methods' => PaymentMethods::flags(PaymentMethods::CONTEXT_MEMBERSHIP),
            'waiting' => $reason !== null || $card === null ? [] : $this->payable($card)->map(fn (Membership $m) => [
                'id' => $m->id,
                'name' => $m->user?->name,
                'relationship' => $m->relationship,
                'state' => $m->state(),
                'expiration_date' => $m->expiration_date?->toDateString(),
            ])->values()->all(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $card = $this->cardOf($user);

        if ($reason = $this->refusalFor($card)) {
            return response()->json(['reason' => $reason, 'message' => $reason === 'parent_not_paid'
                ? 'The card you belong to has not paid, so you buy a normal subscription of your own.'
                : 'Relatives are paid for by a paid, current card.'], Response::HTTP_FORBIDDEN);
        }

        $waiting = $this->payable($card)->keyBy('id');

        if (RelativeFee::plans() === []) {
            return response()->json(['reason' => 'free', 'message' => 'Relatives carry no fee right now.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->validate([
            'relative_ids' => ['required', 'array', 'min:1', 'max:15'],
            'relative_ids.*' => ['integer', Rule::in($waiting->keys()->all())],
            'plan' => ['nullable', Rule::in([RelativeFee::PLAN_MONTH, RelativeFee::PLAN_YEAR])],
            'use_points' => ['nullable', 'boolean'],
            'use_wallet' => ['nullable', 'boolean'],
            'payment_type' => ['required', Rule::in(PaymentTypeEnum::values()), function (string $attribute, mixed $value, \Closure $fail) {
                if (! in_array($value, PaymentMethods::orderTypes(PaymentMethods::CONTEXT_MEMBERSHIP), true)) {
                    $fail('This payment method is not available right now.');
                }
            }],
            'buyer_ip' => ['nullable', 'ip'],
        ]);

        $plan = RelativeFee::plan($data['plan'] ?? null);
        if ($plan === null) {
            return response()->json(['reason' => 'plan_not_sold', 'message' => 'This plan is not available right now.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $perRelative = $plan['price'];

        $ids = array_values(array_unique(array_map('intval', $data['relative_ids'])));
        $paymentType = PaymentTypeEnum::from($data['payment_type']);
        $total = round($perRelative * count($ids), 2);

        try {
            $order = DB::transaction(function () use ($ids, $waiting, $perRelative, $total, $paymentType, $user, $card, $request, $data, $plan) {
                $order = Order::query()->create([
                    'order_code' => Order::generateCode(),
                    'user_id' => $user->id,
                    'total_amount' => $total,
                    'total_amount_before_discount' => $total,
                    'cod_fee' => 0,
                    'delivery_cost' => 0,
                    'delivery_price' => 0,
                    'delivery_profit' => 0,
                    'total_paid' => 0,
                    'customer_full_name' => (string) $user->name,
                    'customer_phone' => (string) $user->phone,
                    'customer_email' => $user->email,
                    'customer_address' => '—',
                    'membership_number' => $card->membership_number,
                    'payment_type' => $paymentType,
                    'payment_status' => PaymentStatusEnum::PENDING,
                    'delivery_status' => DeliveryStatusEnum::PENDING,
                    'ip_address' => $data['buyer_ip'] ?? $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                    'source' => RelativeFee::SOURCE,
                ]);

                $order->products()->saveMany(array_map(function (int $id) use ($waiting, $perRelative, $plan) {
                    $relative = $waiting->get($id);
                    $who = (string) $relative?->user?->name;

                    return new OrderProduct([
                        'name' => ['ar' => "اشتراك قريب — {$who}", 'en' => "Relative subscription — {$who}"],
                        'slug' => RelativeFee::lineSlug($id, $plan['key']),
                        'quantity' => 1,
                        'old_price' => null,
                        'new_price' => $perRelative,
                        'line_total' => $perRelative,
                    ]);
                }, $ids));

                Membership::query()->whereIn('id', $ids)->update(['fee_order_id' => $order->id]);

                // Points are spent only when the member presses «pay with points» (never automatically); the backend counts the pounds.
                $pointsAmount = 0.0;
                if ($request->boolean('use_points')) {
                    $pointsAmount = \App\Support\PointsPayments::applyToOrder($order, $user, \App\Support\PointsPayments::eligible([['amount' => $total, 'cap' => null]]));
                }

                // The wallet likewise only on a press (`use_wallet`), on what the points left to pay.
                if ($request->boolean('use_wallet')) {
                    \App\Support\WalletPayments::applyToOrder($order, $user, max(0.0, round($total - $pointsAmount, 2)));
                }

                return $order->refresh();
            });
        } catch (Throwable $exception) {
            Log::error('Relative fee order could not be placed.', ['user_id' => $user->id, 'message' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()]);

            return response()->json(['message' => 'The order could not be placed.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        OrderLog::record($order->id, null, OrderLog::ACTION_CREATED, null, [
            'order_code' => $order->order_code,
            'source' => RelativeFee::SOURCE,
            'relatives' => $ids,
            'unit_price' => $perRelative,
            'plan' => $plan['key'],
            'months' => $plan['months'],
            'total_amount' => (float) $order->total_amount,
            'payment_type' => $order->payment_type->value,
            'membership_number' => $order->membership_number,
        ], $request);

        if (! $order->isOnlinePayment()) {
            $this->sendReceipt->handle($order, null, $request);
        }

        return response()->json(['order' => new OrderResource($order->fresh()->load('products', 'shipments'))], Response::HTTP_CREATED);
    }

    /**
     * Who the signed-in card may pay for: a primary pays for any of its relatives that are not refused (waiting, active or lapsed — so the
     * same screen renews); a relative pays only for themself. A fee already sitting in a PENDING order is not offered twice.
     */
    private function payable(Membership $card): \Illuminate\Support\Collection
    {
        $query = $card->isRelative()
            ? Membership::query()->with('user')->whereKey($card->id)
            : $card->relatives()->with('user')->where('approval_status', '!=', ApprovalStatusEnum::REJECTED->value);

        return $query->orderBy('id')->get()->filter(fn (Membership $m) => $m->fee_order_id === null
            || ! Order::query()->whereKey($m->fee_order_id)->where('payment_status', PaymentStatusEnum::PENDING)->exists())->values();
    }

    /** Why this card may not pay relative fees, or null. A relative on an unpaid card pays a normal plan instead. */
    private function refusalFor(?Membership $card): ?string
    {
        if ($card === null) {
            return 'no_card';
        }

        $governing = $card->governingCard();

        if ($governing->isTrial()) {
            return 'trial';
        }

        if (! $governing->isCurrent()) {
            return $card->isRelative() ? 'parent_not_paid' : 'not_current';
        }

        return null;
    }

    private function cardOf(User $user): ?Membership
    {
        return $user->memberships()->latest('id')->first();
    }
}
