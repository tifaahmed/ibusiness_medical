<?php

namespace App\Actions\Membership;

use App\Models\MemberLog;
use App\Models\MembershipSubscription;
use App\Models\OrderPaymentLog;
use App\Services\Paymob\PaymobTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * What Paymob says about a SUBSCRIPTION payment, applied to the subscription.
 *
 * The counterpart of {@see \App\Actions\Payment\ApplyPaymobTransaction} for
 * memberships, with the same rules: idempotent, a wrong amount is never
 * accepted, and a late failure never undoes an accepted payment. Card time is
 * only ever added through {@see ActivateSubscription}.
 */
class ApplyPaymobSubscriptionTransaction
{
    public function __construct(private readonly ActivateSubscription $activate) {}

    /** @return array{outcome: string, changed: bool, note: string} */
    public function handle(MembershipSubscription $subscription, PaymobTransaction $txn, string $source, string $correlationId, ?Request $request = null): array
    {
        $result = ['outcome' => $txn->outcome(), 'changed' => false, 'note' => ''];
        $expected = (int) round(((float) $subscription->amount) * 100);

        $subscription->forceFill(array_filter([
            'paymob_order_id' => $txn->paymobOrderId,
            'paymob_response_code' => $txn->responseCode,
        ], fn ($v) => $v !== null))->save();

        switch ($txn->outcome()) {
            case PaymobTransaction::PAID:
                if ($txn->amountCents !== $expected) {
                    $result['note'] = "Paid amount {$txn->amountCents} does not match the price ({$expected}). Not accepted.";
                    break;
                }
                if ($subscription->status === MembershipSubscription::STATUS_ACCEPTED) {
                    $result['note'] = 'Already accepted — nothing to do.';
                    break;
                }
                $subscription->forceFill(['paymob_transaction_id' => $txn->id])->save();
                $result['changed'] = $this->activate->handle($subscription, null, $request);
                $result['note'] = 'Subscription paid; the card was extended.';
                break;

            case PaymobTransaction::FAILED:
                if (in_array($subscription->status, [MembershipSubscription::STATUS_ACCEPTED, MembershipSubscription::STATUS_REFUNDED], true)) {
                    $result['note'] = 'A failed attempt was reported for a settled payment — ignored.';
                    break;
                }
                $subscription->forceFill(['status' => MembershipSubscription::STATUS_REJECTED])->save();
                $result['changed'] = true;
                $result['note'] = 'Payment rejected: '.($txn->message ?? $txn->responseCode ?? 'declined').'.';
                break;

            case PaymobTransaction::REFUNDED:
            case PaymobTransaction::VOIDED:
                /* Taking card time back is a decision for a person: flagged, not automated. */
                if ($subscription->status === MembershipSubscription::STATUS_ACCEPTED) {
                    $subscription->forceFill(['status' => MembershipSubscription::STATUS_REFUNDED])->save();
                    $result['changed'] = true;
                    Log::warning('A paid membership subscription was refunded at Paymob — review the card.', ['subscription_id' => $subscription->id]);
                }
                $result['note'] = 'Refund recorded. The card was NOT shortened — review it.';
                break;

            default:
                $result['note'] = 'Payment is still pending at the gateway.';
        }

        $this->record($subscription, $txn, $source, $correlationId, $request, $result);

        return $result;
    }

    /** @param array{outcome: string, changed: bool, note: string} $result */
    private function record(MembershipSubscription $subscription, PaymobTransaction $txn, string $source, string $correlationId, ?Request $request, array $result): void
    {
        try {
            $now = now();
            OrderPaymentLog::create([
                'membership_subscription_id' => $subscription->id,
                'correlation_id' => $correlationId,
                'event' => OrderPaymentLog::EVENT_APPLIED,
                'direction' => OrderPaymentLog::DIRECTION_IN,
                'request_body' => json_encode(['source' => $source, 'outcome' => $result['outcome']]),
                'response_body' => $result['note'],
                'gateway_code' => $txn->responseCode,
                'gateway_ref' => $txn->id,
                'success' => $result['outcome'] === PaymobTransaction::PAID,
                'started_at' => $now,
                'finished_at' => $now,
                'duration_ms' => 0,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ]);

            MemberLog::record($subscription->membership_id, null, MemberLog::ACTION_UPDATED, null, [
                'subscription_id' => $subscription->id,
                'event' => 'payment_'.$result['outcome'],
                'source' => $source,
                'transaction_id' => $txn->id,
                'response_code' => $txn->responseCode,
                'note' => $result['note'],
            ], $request);
        } catch (Throwable $exception) {
            Log::error('Subscription payment result could not be logged.', [
                'subscription_id' => $subscription->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
