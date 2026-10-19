<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Membership\ActivateSubscription;
use App\Http\Controllers\Controller;
use App\Models\MemberLog;
use App\Models\MembershipSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Membership payments: every attempt to pay for a card, and the queue of ones
 * customer services must check — a Vodafone Cash or Instapay receipt, a cash
 * payment collected by a courier.
 *
 * Confirming is the ONLY thing here that adds time to a card, and it does so
 * through {@see ActivateSubscription}, the same path a verified Paymob callback
 * takes. A receipt is a claim: it is read against the wallet before this button
 * is pressed.
 */
class MembershipSubscriptionController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->input('status', MembershipSubscription::STATUS_PENDING);
        $method = $request->input('method');
        $search = trim((string) $request->input('search', ''));

        $rows = MembershipSubscription::query()
            ->with(['membership.user:id,name,phone,email', 'confirmer:id,name', 'media'])
            ->when($status !== 'all' && $status !== '', fn ($q) => $q->where('status', $status))
            ->when(filled($method), fn ($q) => $q->where('method', $method))
            ->when($search !== '', fn ($q) => $q->whereHas('membership', fn ($m) => $m
                ->where('membership_number', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Subscription/Index', [
            'subscriptions' => [
                'data' => $rows->getCollection()->map(fn (MembershipSubscription $s) => [
                    'id' => $s->id,
                    'membership_number' => $s->membership?->membership_number,
                    'membership_slug' => $s->membership?->slug,
                    'member_name' => $s->membership?->user?->name,
                    'member_phone' => $s->membership?->user?->phone,
                    'card_state' => $s->membership?->state(),
                    'method' => $s->method,
                    'status' => $s->status,
                    'amount' => (float) $s->amount,
                    'months' => $s->months,
                    'reference' => $s->reference,
                    'paymob_transaction_id' => $s->paymob_transaction_id,
                    'paymob_response_code' => $s->paymob_response_code,
                    'rejection_reason' => $s->rejection_reason,
                    'receipts' => $s->getMedia(MembershipSubscription::RECEIPT_COLLECTION)->map(fn ($m) => [
                        'id' => $m->id, 'url' => $m->getUrl(), 'name' => $m->file_name,
                    ])->values()->all(),
                    'confirmed_by' => $s->confirmer?->name,
                    'confirmed_at' => $s->confirmed_at?->format('Y-m-d H:i'),
                    'created_at' => $s->created_at?->format('Y-m-d H:i'),
                ])->all(),
                'links' => $rows->linkCollection()->all(),
                'total' => $rows->total(),
            ],
            'filters' => ['status' => $status, 'method' => $method, 'search' => $search],
            'counts' => MembershipSubscription::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'statuses' => [
                MembershipSubscription::STATUS_PENDING, MembershipSubscription::STATUS_ACCEPTED,
                MembershipSubscription::STATUS_REJECTED, MembershipSubscription::STATUS_REFUNDED, 'all',
            ],
            'methods' => MembershipSubscription::methods(),
        ]);
    }

    public function confirm(Request $request, MembershipSubscription $subscription, ActivateSubscription $activate): RedirectResponse
    {
        try {
            $changed = $activate->handle($subscription, Auth::id(), $request);
        } catch (Throwable $exception) {
            Log::error('Membership subscription could not be confirmed.', [
                'route' => $request->path(), 'subscription_id' => $subscription->id, 'admin_id' => Auth::id(),
                'message' => $exception->getMessage(), 'trace' => $exception->getTraceAsString(),
            ]);

            return back()->with('error', 'The payment could not be confirmed. See the application log.');
        }

        return back()->with($changed ? 'success' : 'error', $changed ? 'Payment confirmed — the card is extended.' : 'This payment was already confirmed.');
    }

    public function reject(Request $request, MembershipSubscription $subscription): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        if ($subscription->status === MembershipSubscription::STATUS_ACCEPTED) {
            return back()->with('error', 'An accepted payment cannot be rejected here.');
        }

        $subscription->forceFill([
            'status' => MembershipSubscription::STATUS_REJECTED,
            'confirmed_by' => Auth::id(),
            'confirmed_at' => now(),
            'rejection_reason' => $validated['reason'] ?? null,
        ])->save();

        MemberLog::record($subscription->membership_id, Auth::id(), MemberLog::ACTION_UPDATED, null, [
            'subscription_id' => $subscription->id,
            'event' => 'payment_rejected',
            'reason' => $validated['reason'] ?? null,
        ], $request);

        return back()->with('success', 'Payment rejected.');
    }
}
