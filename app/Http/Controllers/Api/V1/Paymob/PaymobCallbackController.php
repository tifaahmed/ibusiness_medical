<?php

namespace App\Http\Controllers\Api\V1\Paymob;

use App\Actions\Membership\ApplyPaymobSubscriptionTransaction;
use App\Actions\Payment\ApplyPaymobTransaction;
use App\Actions\Payment\StartPaymobPayment;
use App\Http\Controllers\Controller;
use App\Models\MembershipSubscription;
use App\Models\Order;
use App\Models\OrderPaymentLog;
use App\Services\Paymob\PaymobClient;
use App\Services\Paymob\PaymobHmac;
use App\Services\Paymob\PaymobTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * What Paymob (and the buyer's browser) tell us after a payment.
 *
 * Public by necessity — Paymob cannot hold our API key — so the HMAC is the
 * only credential, and nothing is applied to an order without it. Every hit is
 * logged first, verbatim, whether or not it is believed: a rejected callback
 * is exactly the one somebody will want to read later.
 */
class PaymobCallbackController extends Controller
{
    public function __construct(
        private readonly ApplyPaymobTransaction $apply,
        private readonly ApplyPaymobSubscriptionTransaction $applySubscription,
    ) {}

    /** POST — the server-to-server "transaction processed" callback. */
    public function callback(Request $request): JsonResponse
    {
        $started = microtime(true);
        $client = new PaymobClient(null, $request);
        $payload = $request->json()->all();
        $obj = is_array($payload['obj'] ?? null) ? $payload['obj'] : null;

        $transaction = $obj !== null ? PaymobTransaction::fromArray($obj) : null;
        /* The reference says what was bought: `SUB12A1` is a membership subscription, anything else an order. */
        $subscription = $transaction?->subscriptionId() !== null ? MembershipSubscription::query()->find($transaction->subscriptionId()) : null;
        $order = $subscription === null && $transaction?->orderCode() !== null ? Order::withTrashed()->where('order_code', $transaction->orderCode())->first() : null;
        $subject = $subscription ?? $order;
        $valid = $obj !== null && PaymobHmac::valid($obj, (string) $request->query('hmac'), config('services.paymob.hmac'));

        $log = $client->recordInbound(
            $subject,
            OrderPaymentLog::EVENT_CALLBACK,
            'POST',
            $request->fullUrl(),
            $request->headers->all(),
            $request->getContent(),
            extra: [
                'gateway_code' => $transaction?->responseCode,
                'gateway_ref' => $transaction?->id,
                'started_at' => \Illuminate\Support\Carbon::createFromTimestamp($started),
            ],
        );

        [$status, $body] = $this->decide($valid, $obj, $subject, $transaction, $request, $client, 'callback');

        $this->close($log, $started, $status, $body, $valid && $status === 200);

        return response()->json($body, $status);
    }

    /** GET — the buyer's browser coming back from the checkout page. */
    public function return(Request $request, string $code): RedirectResponse|Response
    {
        $started = microtime(true);
        $client = new PaymobClient(null, $request);
        $query = $request->query();
        $transaction = PaymobTransaction::fromArray($query);
        $subscriptionId = MembershipSubscription::idFromReference($code);
        $order = $subscriptionId === null ? Order::withTrashed()->where('order_code', $code)->first() : MembershipSubscription::query()->find($subscriptionId);
        $valid = PaymobHmac::valid($query, (string) $request->query('hmac'), config('services.paymob.hmac'));

        $log = $client->recordInbound(
            $order,
            OrderPaymentLog::EVENT_RETURN,
            'GET',
            $request->fullUrl(),
            $request->headers->all(),
            (string) json_encode($query, JSON_UNESCAPED_UNICODE),
            extra: [
                'gateway_code' => $transaction->responseCode,
                'gateway_ref' => $transaction->id,
                'started_at' => \Illuminate\Support\Carbon::createFromTimestamp($started),
            ],
        );

        /* Only a signed return is believed; an unsigned one just sends the buyer home to look. */
        [$status, $body] = $valid
            ? $this->decide(true, $query, $order, $transaction, $request, $client, 'return')
            : [403, ['ok' => false, 'note' => 'Return not signed — shown to the buyer as pending.']];

        $outcome = $valid && $status === 200 ? $transaction->outcome() : 'pending';
        $target = $this->targetUrl($code, $outcome);

        $this->close($log, $started, 302, ['redirect' => $target] + $body, $valid && $status === 200);

        return $target !== null
            ? redirect()->away($target)
            : response('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><body style="font-family:sans-serif;text-align:center;padding:3rem"><h2>'
                .e($outcome === 'paid' ? 'Payment received — thank you.' : 'We are confirming your payment.')
                .'</h2><p>You can close this page and return to the app. Order '.e($code).'</p>');
    }

    /**
     * Believe the notice or not, and apply it. Shared by both entry points.
     *
     * @param  array<string, mixed>|null  $data
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function decide(bool $valid, ?array $data, Order|MembershipSubscription|null $order, ?PaymobTransaction $transaction, Request $request, PaymobClient $client, string $source): array
    {
        if (! $valid) {
            Log::warning('Paymob '.$source.' rejected: bad or missing HMAC.', ['ip' => $request->ip(), 'url' => $request->path()]);

            return [403, ['ok' => false, 'note' => 'Signature check failed.']];
        }

        if ($order === null || $transaction === null) {
            Log::warning('Paymob '.$source.' for an order we do not know.', ['reference' => $transaction?->merchantOrderId]);

            return [404, ['ok' => false, 'note' => 'No such order.']];
        }

        try {
            $result = $order instanceof MembershipSubscription
                ? $this->applySubscription->handle($order, $transaction, $source, $client->correlationId, $request)
                : $this->apply->handle($order, $transaction, $source, $client->correlationId, null, $request);
        } catch (Throwable $exception) {
            return [500, ['ok' => false, 'note' => 'The result could not be applied.']];
        }

        return [200, ['ok' => true] + $result];
    }

    /** @param array<string, mixed> $body */
    private function close(OrderPaymentLog $log, float $started, int $status, array $body, bool $ok): void
    {
        $finished = microtime(true);

        try {
            if ($log->exists) {
                $log->update([
                    'http_status' => $status,
                    'response_body' => json_encode($body, JSON_UNESCAPED_UNICODE),
                    'success' => $ok,
                    'error_message' => $ok ? null : (string) ($body['note'] ?? ''),
                    'finished_at' => \Illuminate\Support\Carbon::createFromTimestamp($finished),
                    'duration_ms' => (int) round(($finished - $started) * 1000),
                ]);
            }
        } catch (Throwable $exception) {
            Log::error('Paymob inbound log could not be closed.', ['message' => $exception->getMessage()]);
        }
    }

    private function targetUrl(string $code, string $outcome): ?string
    {
        $isSubscription = MembershipSubscription::idFromReference($code) !== null;
        $base = Cache::get(StartPaymobPayment::returnKey($code))
            ?: config($isSubscription ? 'services.paymob.subscription_return_url' : 'services.paymob.return_url');

        if (blank($base)) {
            return null;
        }

        $base = str_replace('{code}', rawurlencode($code), (string) $base);

        return $base.(str_contains($base, '?') ? '&' : '?').http_build_query(['order' => $code, 'status' => $outcome]);
    }
}
