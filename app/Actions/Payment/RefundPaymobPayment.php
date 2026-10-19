<?php

namespace App\Actions\Payment;

use App\Enums\Order\PaymentStatusEnum;
use App\Models\Order;
use App\Models\OrderLog;
use App\Services\Paymob\PaymobClient;
use App\Services\Paymob\PaymobException;
use App\Services\Paymob\PaymobTransaction;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Returns money to the buyer through the gateway, in full or in part.
 *
 * The order is only marked refunded when Paymob says the refund happened; a
 * refusal leaves the order alone and goes on the audit trail as a failed
 * attempt, with who tried and why.
 */
class RefundPaymobPayment
{
    public function __construct(private readonly ApplyPaymobTransaction $apply) {}

    /**
     * @return array{outcome: string, changed: bool, note: string}
     *
     * @throws ValidationException when the order cannot be refunded
     * @throws PaymobException
     */
    public function handle(Order $order, float $amount, ?string $reason, int $adminId, Request $request): array
    {
        $refundable = round((float) $order->total_paid - (float) $order->refunded_amount, 2);

        if (! $order->isOnlinePayment() || $order->payment_status !== PaymentStatusEnum::ACCEPTED || blank($order->paymob_transaction_id)) {
            throw ValidationException::withMessages(['amount' => 'Only a paid Paymob order can be refunded here.']);
        }

        if ($amount <= 0 || $amount > $refundable) {
            throw ValidationException::withMessages(['amount' => "The amount must be between 0.01 and {$refundable}."]);
        }

        $client = new PaymobClient($adminId, $request);

        try {
            $response = $client->refund($order, (string) $order->paymob_transaction_id, (int) round($amount * 100));
        } catch (PaymobException $exception) {
            OrderLog::record($order->id, $adminId, OrderLog::ACTION_REFUND_FAILED, null, [
                'amount' => $amount, 'reason' => $reason, 'error' => $exception->getMessage(), 'correlation_id' => $client->correlationId,
            ], $request);

            throw $exception;
        }

        return $this->apply->handle(
            $order,
            PaymobTransaction::fromArray($response),
            'refund',
            $client->correlationId,
            $adminId,
            $request,
            ['reason' => $reason, 'amount' => $amount],
        );
    }
}
