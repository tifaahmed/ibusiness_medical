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

    /** What the screen draws: the fee, the ways to pay and the relatives still waiting for their payment. */
    public function show(Request $request): JsonResponse
    {
        $card = $this->cardOf($request->user());

        return response()->json(['data' => [
            'fee' => RelativeFee::fee($card),
            'payment_methods' => PaymentMethods::flags(PaymentMethods::CONTEXT_MEMBERSHIP),
            'waiting' => $card === null || $card->isRelative() ? [] : $this->waiting($card)->map(fn (Membership $m) => [
                'id' => $m->id,
                'name' => $m->user?->name,
                'relationship' => $m->relationship,
            ])->values()->all(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $card = $this->cardOf($user);

        if ($card === null || $card->isRelative() || ! $card->isCurrent() || $card->isTrial()) {
            return response()->json(['reason' => 'not_current', 'message' => 'Relatives are paid for by a primary member with a paid, current card.'], Response::HTTP_FORBIDDEN);
        }

        $waiting = $this->waiting($card)->keyBy('id');
        $perRelative = RelativeFee::perRelative();

        if ($perRelative <= 0) {
            return response()->json(['reason' => 'free', 'message' => 'Relatives carry no fee right now.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->validate([
            'relative_ids' => ['required', 'array', 'min:1', 'max:15'],
            'relative_ids.*' => ['integer', Rule::in($waiting->keys()->all())],
            'payment_type' => ['required', Rule::in(PaymentTypeEnum::values()), function (string $attribute, mixed $value, \Closure $fail) {
                if (! in_array($value, PaymentMethods::orderTypes(PaymentMethods::CONTEXT_MEMBERSHIP), true)) {
                    $fail('This payment method is not available right now.');
                }
            }],
            'buyer_ip' => ['nullable', 'ip'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $data['relative_ids'])));
        $paymentType = PaymentTypeEnum::from($data['payment_type']);
        $total = round($perRelative * count($ids), 2);

        try {
            $order = DB::transaction(function () use ($ids, $waiting, $perRelative, $total, $paymentType, $user, $card, $request, $data) {
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

                $order->products()->saveMany(array_map(function (int $id) use ($waiting, $perRelative) {
                    $relative = $waiting->get($id);
                    $who = (string) $relative?->user?->name;

                    return new OrderProduct([
                        'name' => ['ar' => "اشتراك قريب — {$who}", 'en' => "Relative subscription — {$who}"],
                        'slug' => 'relative-fee-'.$id,
                        'quantity' => 1,
                        'old_price' => null,
                        'new_price' => $perRelative,
                        'line_total' => $perRelative,
                    ]);
                }, $ids));

                Membership::query()->whereIn('id', $ids)->update(['fee_order_id' => $order->id]);

                return $order;
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
            'months' => RelativeFee::months(),
            'total_amount' => (float) $order->total_amount,
            'payment_type' => $order->payment_type->value,
            'membership_number' => $order->membership_number,
        ], $request);

        if (! $order->isOnlinePayment()) {
            $this->sendReceipt->handle($order, null, $request);
        }

        return response()->json(['order' => new OrderResource($order->fresh()->load('products'))], Response::HTTP_CREATED);
    }

    /** The primary's relatives that were added by the member and still wait for their fee (no paid order covers them). */
    private function waiting(Membership $card): \Illuminate\Support\Collection
    {
        return $card->relatives()->with('user')
            ->where('is_active', false)
            ->where('approval_status', ApprovalStatusEnum::PENDING->value)
            ->where('added_by', Membership::ADDED_BY_MEMBER)
            ->orderBy('id')->get()
            ->filter(fn (Membership $m) => $m->fee_order_id === null
                || ! Order::query()->whereKey($m->fee_order_id)->whereIn('payment_status', [PaymentStatusEnum::PENDING, PaymentStatusEnum::ACCEPTED])->exists())
            ->values();
    }

    private function cardOf(User $user): ?Membership
    {
        return $user->memberships()->latest('id')->first();
    }
}
