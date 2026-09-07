<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Partner\OrderResource;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A signed-in member's own orders.
 *
 * Token-gated rather than key-gated: the caller here speaks AS the member, not
 * merely for a storefront, and `$request->user()` is the whole authorisation
 * story — every query is scoped to that id and nothing takes an id from the
 * request.
 *
 * The storefront still has no accounts in the sense the shop was built with:
 * the order code remains the credential that opens an order for a guest. What
 * signing in adds is a history that survives clearing a browser, which is what
 * `claim()` is for.
 */
class OrderController extends Controller
{
    /**
     * Every order belonging to this member, newest first.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $member */
        $member = $request->user();

        $orders = Order::query()
            ->where('user_id', $member->id)
            ->with('products')
            ->latest('id')
            ->paginate((int) $request->integer('per_page', 20));

        return response()->json([
            'data' => OrderResource::collection($orders->items())->toArray($request),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Attach orders this browser placed as a guest to the member who has just
     * signed in.
     *
     * The storefront keeps the codes it placed in an encrypted cookie, so a
     * member signing in on the machine they shopped from can be handed their
     * own history rather than an empty page.
     *
     * Two rules make that safe. An order that already belongs to somebody is
     * never re-pointed — otherwise a code shared in a WhatsApp group would move
     * the order to whoever pasted it first. And a code is only claimable while
     * it is UNCLAIMED, which is exactly the guest state the code was already
     * the sole credential for: knowing it has always been enough to read the
     * order, so knowing it is enough to keep it.
     */
    public function claim(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'codes' => ['required', 'array', 'max:50'],
            'codes.*' => ['required', 'string', 'max:32'],
        ]);

        /** @var User $member */
        $member = $request->user();

        $codes = array_values(array_unique(array_map(
            fn ($code) => trim((string) $code),
            $validated['codes'],
        )));

        $orders = Order::query()
            ->whereIn('order_code', $codes)
            ->whereNull('user_id')
            ->get();

        foreach ($orders as $order) {
            $order->user_id = $member->id;
            $order->save();

            /*
             * Logged with a null admin id on purpose: `admin_id` means a member
             * of staff acted, and this was the buyer claiming their own order.
             * Who it went to is in the new values, where a reader looking at
             * the trail will find it.
             */
            OrderLog::record(
                $order->id,
                null,
                OrderLog::ACTION_UPDATED,
                ['user_id' => null],
                ['user_id' => $member->id, 'claimed_by_member' => true],
            );
        }

        return response()->json([
            'data' => [
                'claimed' => $orders->pluck('order_code')->values()->all(),
                /*
                 * How many of this member's orders there are afterwards, so the
                 * storefront can update its badge without a second call.
                 */
                'total' => Order::query()->where('user_id', $member->id)->count(),
            ],
        ]);
    }

    /**
     * One of this member's orders in full.
     */
    public function show(Request $request, string $orderCode): JsonResponse
    {
        /** @var User $member */
        $member = $request->user();

        $order = Order::query()
            ->where('user_id', $member->id)
            ->where('order_code', $orderCode)
            ->with('products')
            ->first();

        if (! $order instanceof Order) {
            return response()->json([
                'message' => 'No order of yours has that code.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'data' => (new OrderResource($order))->toArray($request),
        ]);
    }
}
