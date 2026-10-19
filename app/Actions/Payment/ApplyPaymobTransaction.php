<?php

namespace App\Actions\Payment;

use App\Enums\Order\OrderStatusEnum;
use App\Enums\Order\PaymentStatusEnum;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\OrderPaymentLog;
use App\Services\Paymob\PaymobTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turns what Paymob says about a transaction into the state of an order.
 *
 * The ONE place that does it, for all three ways the news arrives: the server
 * callback, the buyer's browser coming back, and an admin pressing "check with
 * Paymob". They can all arrive for the same payment, in any order and more than
 * once, so this is idempotent and never downgrades a settled order:
 *
 *  - a late "failed" never un-pays a paid order;
 *  - a repeated "paid" for the same transaction changes nothing and mails nothing;
 *  - a paid amount that is not the order's total is NOT accepted, whatever the
 *    flag says — the callback is signed, but a wrong sum is still a wrong sum.
 */
class ApplyPaymobTransaction
{
    public function __construct(private readonly SendOrderReceipt $receipt) {}

    /**
     * @param  string  $source  callback | return | inquiry — where the news came from
     * @return array{outcome: string, changed: bool, note: string}
     */
    public function handle(Order $order, PaymobTransaction $txn, string $source, string $correlationId, ?int $adminId = null, ?Request $request = null, array $context = []): array
    {
        $result = ['outcome' => $txn->outcome(), 'changed' => false, 'note' => ''];
        $sendReceipt = false;
        $oldStatus = null;
        $action = OrderLog::ACTION_PAYMENT_CALLBACK;

        try {
            DB::transaction(function () use ($order, $txn, &$result, &$sendReceipt, &$oldStatus, &$action) {
                $fresh = Order::query()->lockForUpdate()->findOrFail($order->id);
                $oldStatus = $fresh->payment_status;
                $total = (int) round(((float) $fresh->total_amount) * 100);

                $attributes = array_filter([
                    'paymob_order_id' => $txn->paymobOrderId,
                    'paymob_response_code' => $txn->responseCode,
                ], fn ($value) => $value !== null);

                switch ($txn->outcome()) {
                    case PaymobTransaction::PAID:
                        if ($txn->amountCents !== $total || ($txn->currency !== null && strcasecmp($txn->currency, (string) config('services.paymob.currency')) !== 0)) {
                            $result['note'] = "Paid amount {$txn->amountCents} {$txn->currency} does not match the order ({$total}). Not accepted.";
                            $action = OrderLog::ACTION_PAYMENT_CHECKED;
                            break;
                        }
                        if ($fresh->payment_status === PaymentStatusEnum::ACCEPTED && $fresh->paymob_transaction_id === $txn->id) {
                            $result['note'] = 'Already accepted for this transaction — nothing to do.';
                            break;
                        }
                        if ($fresh->payment_status === PaymentStatusEnum::REFUNDED) {
                            $result['note'] = 'Order already refunded — a paid notice for it is ignored.';
                            break;
                        }
                        $attributes += [
                            'payment_status' => PaymentStatusEnum::ACCEPTED,
                            'order_status' => OrderStatusEnum::SUCCESS,
                            'total_paid' => $fresh->total_amount,
                            'paid_at' => $fresh->paid_at ?? now(),
                            'paymob_transaction_id' => $txn->id,
                        ];
                        $result['changed'] = true;
                        $result['note'] = 'Payment accepted.';
                        $action = OrderLog::ACTION_PAYMENT_ACCEPTED;
                        $sendReceipt = true;
                        break;

                    case PaymobTransaction::FAILED:
                        if (in_array($fresh->payment_status, [PaymentStatusEnum::ACCEPTED, PaymentStatusEnum::REFUNDED], true)) {
                            $result['note'] = 'A failed attempt was reported for an order that is already settled — ignored.';
                            break;
                        }
                        $attributes += [
                            'payment_status' => PaymentStatusEnum::REJECTED,
                            'order_status' => OrderStatusEnum::FAILED,
                        ];
                        $result['changed'] = $fresh->payment_status !== PaymentStatusEnum::REJECTED;
                        $result['note'] = 'Payment rejected: '.($txn->message ?? $txn->responseCode ?? 'declined').'.';
                        $action = OrderLog::ACTION_PAYMENT_REJECTED;
                        break;

                    case PaymobTransaction::REFUNDED:
                    case PaymobTransaction::VOIDED:
                        if ($txn->parentTransaction !== null && OrderPaymentLog::query()
                            ->where('order_id', $fresh->id)->where('event', OrderPaymentLog::EVENT_APPLIED)
                            ->where('gateway_ref', $txn->id)->where('response_body', 'like', 'Refund of%')->exists()) {
                            $result['note'] = 'This refund transaction was already recorded — nothing to do.';
                            break;
                        }
                        $refunded = $this->refundedAmount($fresh, $txn, $total);
                        if ($refunded <= (float) $fresh->refunded_amount) {
                            $result['note'] = 'Refund already recorded — nothing to do.';
                            break;
                        }
                        $attributes += ['refunded_amount' => $refunded, 'refunded_at' => now()];
                        if ($refunded >= (float) $fresh->total_amount) {
                            $attributes['payment_status'] = PaymentStatusEnum::REFUNDED;
                        }
                        $result['changed'] = true;
                        $result['note'] = 'Refund of '.number_format($refunded, 2).' recorded.';
                        $action = OrderLog::ACTION_PAYMENT_REFUNDED;
                        break;

                    default:
                        $result['note'] = 'Payment is still pending at the gateway.';
                }

                $fresh->forceFill($attributes)->save();
            });
        } catch (Throwable $exception) {
            Log::error('Paymob result could not be applied to the order.', [
                'order_code' => $order->order_code,
                'source' => $source,
                'transaction_id' => $txn->id,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }

        $order->refresh();

        $this->record($order, $txn, $source, $correlationId, $adminId, $request, $result, $action, $oldStatus, $context);

        if ($sendReceipt) {
            $this->receipt->handle($order, $adminId, $request);
        }

        return $result;
    }

    /** What the order has been refunded in total, per what Paymob reported. */
    private function refundedAmount(Order $order, PaymobTransaction $txn, int $totalCents): float
    {
        $reported = PaymobTransaction::pick($txn->raw, 'refunded_amount_cents');

        if ($txn->parentTransaction !== null && $txn->amountCents !== null) {
            /* This IS a refund transaction: its amount is what this refund moved. */
            return round((float) $order->refunded_amount + $txn->amountCents / 100, 2);
        }

        /* No figure from Paymob: a void is whole by nature, and a first refund is taken as whole; but with
           a refund already on record an unlabelled "refunded" must not be read as the rest. */
        $cents = is_numeric($reported)
            ? (int) $reported
            : ($txn->isVoided || (float) $order->refunded_amount <= 0 ? $totalCents : (int) round((float) $order->refunded_amount * 100));

        return round($cents / 100, 2);
    }

    /** @param array{outcome: string, changed: bool, note: string} $result */
    private function record(Order $order, PaymobTransaction $txn, string $source, string $correlationId, ?int $adminId, ?Request $request, array $result, string $action, ?PaymentStatusEnum $oldStatus, array $context): void
    {
        if ($source === 'inquiry' && $action === OrderLog::ACTION_PAYMENT_CALLBACK) {
            $action = OrderLog::ACTION_PAYMENT_CHECKED;
        }

        try {
            $now = now();
            OrderPaymentLog::create([
                'order_id' => $order->id,
                'admin_id' => $adminId,
                'correlation_id' => $correlationId,
                'event' => OrderPaymentLog::EVENT_APPLIED,
                'direction' => OrderPaymentLog::DIRECTION_IN,
                'request_body' => json_encode(['source' => $source, 'outcome' => $result['outcome']], JSON_UNESCAPED_UNICODE),
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

            OrderLog::record($order->id, $adminId, $action, [
                'payment_status' => $oldStatus?->value,
            ], [
                'payment_status' => $order->payment_status->value,
                'source' => $source,
                'outcome' => $result['outcome'],
                'transaction_id' => $txn->id,
                'response_code' => $txn->responseCode,
                'amount_cents' => $txn->amountCents,
                'note' => $result['note'],
                ...$context,
            ], $request);

            if ($oldStatus !== null && $oldStatus !== $order->payment_status) {
                OrderLog::record($order->id, $adminId, OrderLog::ACTION_PAYMENT_STATUS_CHANGED,
                    ['payment_status' => $oldStatus->value],
                    ['payment_status' => $order->payment_status->value],
                    $request,
                );
            }
        } catch (Throwable $exception) {
            Log::error('Paymob result was applied but could not be logged.', [
                'order_code' => $order->order_code,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }
}
