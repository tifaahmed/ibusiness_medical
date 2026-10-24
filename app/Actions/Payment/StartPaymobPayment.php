<?php

namespace App\Actions\Payment;

use App\Models\Order;
use App\Models\OrderLog;
use App\Services\Paymob\PaymobClient;
use App\Services\Paymob\PaymobException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Opens (or re-opens) a Paymob payment for an order and returns the hosted
 * checkout URL the buyer is sent to.
 *
 * Each attempt uses its own `special_reference` (`CODE`, `CODE-2`, …) because
 * Paymob will not take the same merchant reference twice — which is also what
 * lets a buyer whose card was declined simply try again.
 */
class StartPaymobPayment
{
    /** How long the "send the buyer back here" choice is remembered. */
    private const RETURN_TTL_HOURS = 48;

    /**
     * @return array{checkout_url: string, client_secret: string, form: string, reference: string}
     *
     * @throws PaymobException
     */
    public function handle(Order $order, ?string $returnUrl = null, ?int $adminId = null, ?Request $request = null): array
    {
        $order->loadMissing('products');

        $attempt = (int) $order->paymob_attempts + 1;
        $reference = $attempt === 1 ? $order->order_code : $order->order_code.'-'.$attempt;

        if ($returnUrl !== null) {
            Cache::put(self::returnKey($order->order_code), $returnUrl, now()->addHours(self::RETURN_TTL_HOURS));
        }

        $client = new PaymobClient($adminId, $request);

        try {
            $intention = $client->createIntention(
                $order,
                $reference,
                route('api.v1.paymob.callback'),
                route('api.v1.paymob.return', ['code' => $order->order_code]),
            );
        } catch (PaymobException $exception) {
            $order->forceFill(['paymob_attempts' => $attempt, 'paymob_reference' => $reference, 'paymob_response_code' => 'intention_failed'])->save();
            OrderLog::record($order->id, $adminId, OrderLog::ACTION_PAYMENT_INITIATED, null, [
                'ok' => false, 'reference' => $reference, 'correlation_id' => $client->correlationId, 'error' => $exception->getMessage(),
            ], $request);

            throw $exception;
        }

        $order->forceFill([
            'paymob_attempts' => $attempt,
            'paymob_reference' => $reference,
            'paymob_order_id' => $intention['intention_id'] ?? $order->paymob_order_id,
        ])->save();

        OrderLog::record($order->id, $adminId, OrderLog::ACTION_PAYMENT_INITIATED, null, [
            'ok' => true,
            'reference' => $reference,
            'attempt' => $attempt,
            'amount' => (float) $order->total_amount,
            'correlation_id' => $client->correlationId,
            'duration_ms' => $intention['log']->duration_ms,
        ], $request);

        return ['checkout_url' => $intention['checkout_url'], 'client_secret' => $intention['client_secret'], 'form' => $intention['form'], 'reference' => $reference];
    }

    public static function returnKey(string $orderCode): string
    {
        return 'paymob:return:'.$orderCode;
    }

    /**
     * A client may only ask to be returned to a URL this install trusts —
     * otherwise the return redirect is an open redirect off our own domain.
     */
    public static function allowedReturnUrl(?string $candidate): ?string
    {
        if (blank($candidate)) {
            return null;
        }

        foreach (config('services.paymob.allowed_return_urls', []) as $prefix) {
            if (str_starts_with($candidate, $prefix)) {
                return $candidate;
            }
        }

        return null;
    }
}
