<?php

namespace App\Actions\Membership;

use App\Actions\Contact\RecordContactMessageAction;
use App\Actions\Payment\StartPaymobPayment;
use App\Enums\Contact\ContactChannelEnum;
use App\Enums\Contact\ContactSourceEnum;
use App\Models\MemberLog;
use App\Models\Membership;
use App\Models\MembershipSubscription;
use App\Services\Paymob\PaymobClient;
use App\Services\Paymob\PaymobException;
use App\Support\MembershipPlan;
use App\Support\PaymentMethods;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * A member (or someone holding their card) starts paying for the membership.
 *
 *  - `paymob`: opens a hosted checkout and returns its URL. The card is only
 *    extended when a SIGNED callback says the money arrived.
 *  - `cash_on_delivery`, `vodafone_cash`, `instapay`: records a pending payment
 *    and rings customer services through the contact inbox. Vodafone Cash and
 *    Instapay carry a receipt, which is a claim an admin checks — it extends
 *    nothing by itself.
 */
class StartSubscription
{
    public function __construct(private readonly RecordContactMessageAction $inbox) {}

    /**
     * @param  list<UploadedFile>  $receipts
     * @return array{subscription: MembershipSubscription, checkout_url: ?string, client_secret: ?string, public_key: ?string, form: ?string}
     *
     * @throws ValidationException
     * @throws PaymobException
     */
    public function handle(Membership $membership, string $method, ?string $email, ?string $returnUrl, array $receipts, ?string $reference, ?Request $request = null): array
    {
        if (! in_array($method, MembershipSubscription::methods(), true)) {
            throw ValidationException::withMessages(['method' => ['Unknown payment method.']]);
        }

        if (! in_array($method, PaymentMethods::subscriptionMethods(), true)) {
            throw ValidationException::withMessages(['method' => ['This payment method is not available right now. Please contact customer service.']]);
        }

        if ($method === MembershipSubscription::METHOD_PAYMOB && ! PaymentMethods::channelAllowed($request?->input('paymob_channel'))) {
            throw ValidationException::withMessages(['method' => ['This way of paying online is not available right now.']]);
        }

        if (in_array($method, MembershipSubscription::RECEIPT_METHODS, true) && $receipts === []) {
            throw ValidationException::withMessages(['receipt' => ['Please attach the transfer receipt.']]);
        }

        /* The plan chosen (`plan` = year|half). A plan that is not on sale is refused, never silently swapped for another price. */
        $plan = MembershipPlan::find($request?->input('plan'));
        if ($plan === null) {
            throw ValidationException::withMessages(['plan' => ['This subscription plan is not available right now.']]);
        }

        return $method === MembershipSubscription::METHOD_PAYMOB
            ? $this->online($membership, $email, $returnUrl, $request, $plan)
            : $this->throughCustomerServices($membership, $method, $email, $receipts, $reference, $request, $plan);
    }

    /** @param array{key: string, price: float, months: int} $plan */
    private function online(Membership $membership, ?string $email, ?string $returnUrl, ?Request $request, array $plan): array
    {
        /* A new attempt on the same open payment, rather than a pile of abandoned ones. */
        $subscription = $membership->subscriptions()
            ->where('method', MembershipSubscription::METHOD_PAYMOB)
            ->where('status', MembershipSubscription::STATUS_PENDING)
            ->latest('id')->first()
            ?? $membership->subscriptions()->create([
                'method' => MembershipSubscription::METHOD_PAYMOB,
                'status' => MembershipSubscription::STATUS_PENDING,
                'amount' => $plan['price'],
                'months' => $plan['months'],
                'email' => $email,
                'channel' => $request ? ContactChannelEnum::fromRequest($request)->value : null,
                'ip_address' => $request?->ip(),
            ]);

        /*
         * Nothing is paid on an open attempt yet, so it follows today's price: a
         * reused payment kept the amount it was opened with, and a price edited in
         * between was charged at the old figure.
         */
        $price = $plan['price'];
        $months = $plan['months'];
        if ((float) $subscription->amount !== $price || (int) $subscription->months !== $months) {
            $subscription->forceFill(['amount' => $price, 'months' => $months])->save();
        }

        if (filled($email) && $subscription->email !== $email) {
            $subscription->forceFill(['email' => $email])->save();
        }

        $reference = $subscription->nextPaymobReference();
        $key = StartPaymobPayment::returnKey('SUB'.$subscription->id);

        $allowed = StartPaymobPayment::allowedReturnUrl($returnUrl);
        if ($allowed !== null) {
            Cache::put($key, $allowed, now()->addHours(48));
        }

        $client = new PaymobClient(null, $request);

        try {
            $intention = $client->createSubscriptionIntention(
                $subscription,
                $reference,
                route('api.v1.paymob.callback'),
                route('api.v1.paymob.return', ['code' => 'SUB'.$subscription->id]),
            );
        } catch (PaymobException $exception) {
            $subscription->forceFill([
                'paymob_attempts' => $subscription->paymob_attempts + 1,
                'paymob_reference' => $reference,
                'paymob_response_code' => 'intention_failed',
            ])->save();

            throw $exception;
        }

        $subscription->forceFill([
            'paymob_attempts' => $subscription->paymob_attempts + 1,
            'paymob_reference' => $reference,
            'paymob_order_id' => $intention['intention_id'] ?? $subscription->paymob_order_id,
        ])->save();

        MemberLog::record($membership->id, null, MemberLog::ACTION_UPDATED, null, [
            'subscription_id' => $subscription->id,
            'event' => 'payment_opened',
            'method' => MembershipSubscription::METHOD_PAYMOB,
            'amount' => (float) $subscription->amount,
            'correlation_id' => $client->correlationId,
        ], $request);

        return [
            'subscription' => $subscription,
            'checkout_url' => $intention['checkout_url'],
            'client_secret' => $intention['client_secret'],
            'public_key' => config('services.paymob.public_key'),
            'form' => $intention['form'],
        ];
    }

    /** @param list<UploadedFile> $receipts */
    /** @param array{key: string, price: float, months: int} $plan */
    private function throughCustomerServices(Membership $membership, string $method, ?string $email, array $receipts, ?string $reference, ?Request $request, array $plan): array
    {
        $subscription = $membership->subscriptions()
            ->where('method', $method)
            ->where('status', MembershipSubscription::STATUS_PENDING)
            ->latest('id')->first()
            ?? $membership->subscriptions()->create([
                'method' => $method,
                'status' => MembershipSubscription::STATUS_PENDING,
                'amount' => $plan['price'],
                'months' => $plan['months'],
                'reference' => $reference,
                'email' => $email,
                'channel' => $request ? ContactChannelEnum::fromRequest($request)->value : null,
                'ip_address' => $request?->ip(),
            ]);

        /* An open claim follows the plan chosen now, like the online attempt above. */
        if ((float) $subscription->amount !== $plan['price'] || (int) $subscription->months !== $plan['months']) {
            $subscription->forceFill(['amount' => $plan['price'], 'months' => $plan['months']])->save();
        }

        /* Append-only, like order receipts: more evidence never replaces earlier evidence. */
        foreach ($receipts as $file) {
            $subscription->addMedia($file)->toMediaCollection(MembershipSubscription::RECEIPT_COLLECTION);
        }

        $user = $membership->user;
        $label = str_replace('_', ' ', $method);

        $this->inbox->handle([
            'phone' => (string) $user?->phone,
            'name' => $user?->name,
            'email' => $email ?: $user?->email,
            'subject' => 'Membership payment — '.$label,
            'message' => "Card {$membership->membership_number} wants to pay {$subscription->amount} EGP for {$subscription->months} months by {$label}."
                .($reference ? " Reference: {$reference}." : '')
                .($receipts !== [] ? ' Receipt attached to subscription #'.$subscription->id.'.' : ' Please call to arrange it.'),
            'source' => ContactSourceEnum::SUBSCRIPTION_REQUEST->value,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 1000) : null,
        ]);

        MemberLog::record($membership->id, null, MemberLog::ACTION_UPDATED, null, [
            'subscription_id' => $subscription->id,
            'event' => 'payment_requested',
            'method' => $method,
            'receipts' => count($receipts),
        ], $request);

        return ['subscription' => $subscription, 'checkout_url' => null, 'client_secret' => null, 'public_key' => null, 'form' => null];
    }
}
