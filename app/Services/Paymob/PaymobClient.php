<?php

namespace App\Services\Paymob;

use App\Models\MembershipSubscription;
use App\Models\Order;
use App\Models\OrderPaymentLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Paymob (Accept) over Laravel's HTTP client, the same thin shape as
 * {@see \App\Services\Abs\AbsClient}.
 *
 * EVERY call goes through {@see self::exchange()}, which writes one
 * `order_payment_logs` row per HTTP exchange: URL, headers, the request body
 * exactly as sent, the status code, the response body exactly as received, the
 * gateway's own code, and start / finish / duration to the millisecond. That
 * is the whole point of this class — a payment that went wrong must be
 * reconstructable from the log alone. Only credentials are masked.
 */
class PaymobClient
{
    /**
     * Paymob insists on a syntactically valid email in the billing data, and many buyers
     * leave it blank. A neutral address stands in for them; nothing is ever sent to it,
     * and the receipt email still goes only to a real address (see `Order::receiptEmail()`).
     */
    private const NO_EMAIL = 'noemail@deilar.com';

    public readonly string $correlationId;

    public function __construct(
        private readonly ?int $adminId = null,
        private readonly ?Request $request = null,
        ?string $correlationId = null,
    ) {
        $this->correlationId = $correlationId ?? (string) Str::uuid();
    }

    public static function isConfigured(): bool
    {
        return filled(config('services.paymob.secret_key'))
            && filled(config('services.paymob.public_key'))
            && config('services.paymob.integration_ids') !== [];
    }

    public const CHANNEL_CARD = 'card';

    public const CHANNEL_WALLET = 'wallet';

    public const CHANNELS = [self::CHANNEL_CARD, self::CHANNEL_WALLET];

    /**
     * The Paymob integration ids behind one way of paying online. Card falls
     * back to the whole list for installs that only ever set PAYMOB_INTEGRATION_IDS.
     *
     * @return list<string>
     */
    public static function integrationIds(string $channel): array
    {
        $all = array_values(config('services.paymob.integration_ids') ?? []);

        return $channel === self::CHANNEL_WALLET
            ? array_values(config('services.paymob.wallet_integration_ids') ?? [])
            : (array_values(config('services.paymob.card_integration_ids') ?? []) ?: $all);
    }

    /** Whether a channel can be opened at all: gateway keys plus an integration id behind it. */
    public static function channelConfigured(string $channel): bool
    {
        return filled(config('services.paymob.secret_key'))
            && filled(config('services.paymob.public_key'))
            && self::integrationIds($channel) !== [];
    }

    /**
     * The ids a payment is offered with: the channel the buyer chose, or — when
     * the client names none — every channel the admin has switched on.
     *
     * @return list<int>
     */
    private function paymentMethods(): array
    {
        $requested = $this->request?->input('paymob_channel');
        $channels = in_array($requested, self::CHANNELS, true) ? [$requested] : \App\Support\PaymentMethods::onlineChannels();
        $ids = array_merge(...array_map(fn (string $c) => self::integrationIds($c), $channels ?: self::CHANNELS));

        return array_values(array_unique(array_map('intval', $ids ?: config('services.paymob.integration_ids'))));
    }

    /**
     * Open a payment for an order and return the hosted checkout URL.
     *
     * Amounts are in piasters. The items must add up to the amount or Paymob
     * refuses the intention, so they are built from the archived order lines
     * plus one line each for delivery and the cash fee, and fall back to a
     * single line if rounding ever makes them disagree.
     *
     * @return array{checkout_url: string, client_secret: string, intention_id: ?string, log: OrderPaymentLog}
     *
     * @throws PaymobException
     */
    public function createIntention(Order $order, string $reference, string $notificationUrl, string $redirectionUrl): array
    {
        $amount = (int) round(((float) $order->total_amount) * 100);

        $body = [
            'amount' => $amount,
            'currency' => config('services.paymob.currency'),
            'payment_methods' => $this->paymentMethods(),
            'items' => $this->items($order, $amount),
            'billing_data' => $this->billing($order),
            'customer' => [
                'first_name' => $this->nameParts($order)[0],
                'last_name' => $this->nameParts($order)[1],
                'email' => $order->receiptEmail() ?? self::NO_EMAIL,
            ],
            'special_reference' => $reference,
            'notification_url' => $notificationUrl,
            'redirection_url' => $redirectionUrl,
            'extras' => $this->origin('order', [
                'order_code' => $order->order_code,
                /* storefront / mobile_app / admin: which of our clients placed it. */
                'channel' => (string) ($order->source ?? 'unknown'),
                'payment_type' => $order->payment_type?->value,
                'items' => $order->products->count(),
                'attempt' => (int) $order->paymob_attempts + 1,
            ]),
        ];

        return $this->openIntention($order, $body);
    }

    /**
     * Open a payment for a membership subscription. Same gateway call and the
     * same logging as an order; only the thing being bought differs.
     *
     * @return array{checkout_url: string, client_secret: string, intention_id: ?string, log: OrderPaymentLog}
     *
     * @throws PaymobException
     */
    public function createSubscriptionIntention(MembershipSubscription $subscription, string $reference, string $notificationUrl, string $redirectionUrl): array
    {
        $subscription->loadMissing('membership.user');
        $user = $subscription->membership?->user;
        $amount = (int) round(((float) $subscription->amount) * 100);
        $name = preg_split('/\s+/u', trim((string) ($user?->name ?: 'Member')), 2) ?: [];
        $first = $name[0] ?: 'Member';
        $last = $name[1] ?? 'NA';
        $email = filled($subscription->email) ? $subscription->email : ($user?->email ?: self::NO_EMAIL);

        $body = [
            'amount' => $amount,
            'currency' => config('services.paymob.currency'),
            'payment_methods' => $this->paymentMethods(),
            'items' => [[
                'name' => 'Deilar membership '.$subscription->months.' months',
                'amount' => $amount,
                'description' => 'Card '.$subscription->membership?->membership_number,
                'quantity' => 1,
            ]],
            'billing_data' => [
                'first_name' => $first, 'last_name' => $last,
                'phone_number' => $user?->phone ?: 'NA', 'email' => $email,
                'country' => 'EG', 'state' => 'NA', 'city' => 'NA', 'street' => 'NA',
                'building' => 'NA', 'floor' => 'NA', 'apartment' => 'NA',
            ],
            'customer' => ['first_name' => $first, 'last_name' => $last, 'email' => $email],
            'special_reference' => $reference,
            'notification_url' => $notificationUrl,
            'redirection_url' => $redirectionUrl,
            'extras' => $this->origin('membership', [
                'membership_number' => (string) $subscription->membership?->membership_number,
                'subscription_id' => $subscription->id,
                'months' => $subscription->months,
                'card_plan_before' => (string) $subscription->membership?->plan,
                'attempt' => $subscription->paymob_attempts + 1,
            ]),
        ];

        return $this->openIntention($subscription, $body);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{checkout_url: string, client_secret: string, intention_id: ?string, log: OrderPaymentLog}
     */
    private function openIntention(Order|MembershipSubscription $order, array $body): array
    {
        [$response, $log] = $this->exchange(
            $order,
            OrderPaymentLog::EVENT_INTENTION,
            'POST',
            '/v1/intention/',
            $body,
            ['Authorization' => 'Token '.config('services.paymob.secret_key')],
        );

        $secret = $response->json('client_secret');

        if (! $response->successful() || ! is_string($secret) || $secret === '') {
            $reason = $this->describe($response);
            $log->update(['success' => false, 'error_message' => $reason]);

            throw new PaymobException($reason);
        }

        $log->update(['success' => true, 'gateway_ref' => (string) ($response->json('id') ?? $response->json('intention_order_id') ?? '')]);

        return [
            'checkout_url' => rtrim((string) config('services.paymob.base_url'), '/')
                .'/unifiedcheckout/?publicKey='.urlencode((string) config('services.paymob.public_key'))
                .'&clientSecret='.urlencode($secret),
            'client_secret' => $secret,
            'intention_id' => $response->json('id') !== null ? (string) $response->json('id') : null,
            'log' => $log,
        ];
    }

    /**
     * Ask Paymob what happened to an order's payment — the "check with Paymob"
     * button. Needs the legacy API key (it trades it for a short-lived token).
     *
     * @return array<string, mixed> the transaction as Paymob describes it
     *
     * @throws PaymobException
     */
    public function inquire(Order|MembershipSubscription $order): array
    {
        if (blank(config('services.paymob.api_key'))) {
            throw new PaymobException('PAYMOB_API_KEY is not set, so the payment cannot be looked up. Add it to .env.');
        }

        [$tokenResponse, $tokenLog] = $this->exchange(
            $order,
            OrderPaymentLog::EVENT_AUTH_TOKEN,
            'POST',
            '/api/auth/tokens',
            ['api_key' => config('services.paymob.api_key')],
            [],
            maskBody: ['api_key'],
            maskResponse: ['token'],
        );

        $token = $tokenResponse->json('token');

        if (! $tokenResponse->successful() || ! is_string($token)) {
            $reason = $this->describe($tokenResponse);
            $tokenLog->update(['success' => false, 'error_message' => $reason]);

            throw new PaymobException($reason);
        }

        $tokenLog->update(['success' => true]);

        $body = array_filter([
            'merchant_order_id' => $order->paymob_reference ?? ($order instanceof Order ? $order->order_code : null),
            'order_id' => $order->paymob_order_id,
        ], fn ($value) => filled($value));

        // Prefer the reference we sent: the Paymob order id is only known after a callback.
        if (isset($body['merchant_order_id'])) {
            unset($body['order_id']);
        }

        [$response, $log] = $this->exchange(
            $order,
            OrderPaymentLog::EVENT_INQUIRY,
            'POST',
            '/api/ecommerce/orders/transaction_inquiry',
            $body,
            ['Authorization' => 'Bearer '.$token],
        );

        if (! $response->successful() || ! is_array($response->json())) {
            $reason = $this->describe($response);
            $log->update(['success' => false, 'error_message' => $reason]);

            throw new PaymobException($reason);
        }

        $transaction = PaymobTransaction::fromArray($response->json());
        $log->update([
            'success' => true,
            'gateway_code' => $transaction->responseCode,
            'gateway_ref' => $transaction->id,
        ]);

        return $response->json();
    }

    /**
     * Refund (all or part of) a captured transaction.
     *
     * @return array<string, mixed> the refund transaction
     *
     * @throws PaymobException
     */
    public function refund(Order $order, string $transactionId, int $amountCents): array
    {
        [$response, $log] = $this->exchange(
            $order,
            OrderPaymentLog::EVENT_REFUND,
            'POST',
            '/api/acceptance/void_refund/refund',
            ['transaction_id' => (int) $transactionId, 'amount_cents' => $amountCents],
            ['Authorization' => 'Token '.config('services.paymob.secret_key')],
        );

        $json = $response->json();
        $transaction = is_array($json) ? PaymobTransaction::fromArray($json) : null;

        $log->update(['gateway_code' => $transaction?->responseCode, 'gateway_ref' => $transaction?->id]);

        if (! $response->successful() || ! is_array($json) || ! ($transaction->success ?? false)) {
            $reason = $this->describe($response);
            $log->update(['success' => false, 'error_message' => $reason]);

            throw new PaymobException($reason);
        }

        $log->update(['success' => true]);

        return $json;
    }

    /**
     * One HTTP exchange, timed and logged. The row is written even when the
     * gateway cannot be reached, so an outage is on the record too.
     *
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     * @param  list<string>  $maskBody  body keys whose VALUE is a credential
     * @param  list<string>  $maskResponse  response keys whose VALUE is a credential
     * @return array{0: Response, 1: OrderPaymentLog}
     *
     * @throws PaymobException when the gateway cannot be reached
     */
    private function exchange(
        Order|MembershipSubscription $order,
        string $event,
        string $method,
        string $path,
        array $body,
        array $headers,
        array $maskBody = [],
        array $maskResponse = [],
    ): array {
        $url = rtrim((string) config('services.paymob.base_url'), '/').$path;
        $started = microtime(true);
        $startedAt = Carbon::createFromTimestamp($started, config("app.timezone"));

        $log = $this->write([
            'order_id' => $order instanceof Order ? $order->id : null,
            'membership_subscription_id' => $order instanceof MembershipSubscription ? $order->id : null,
            'event' => $event,
            'direction' => OrderPaymentLog::DIRECTION_OUT,
            'method' => $method,
            'url' => $url,
            'request_headers' => $this->maskHeaders($headers),
            'request_body' => $this->encode($this->maskValues($body, $maskBody)),
            'started_at' => $startedAt,
        ]);

        try {
            $response = Http::withHeaders($headers)
                ->timeout((int) config('services.paymob.timeout', 30))
                ->acceptJson()
                ->asJson()
                ->send($method, $url, ['json' => $body]);
        } catch (ConnectionException $exception) {
            $this->finish($log, $started, [
                'success' => false,
                'error_message' => 'Could not reach Paymob: '.$exception->getMessage(),
            ]);

            throw new PaymobException('Could not reach Paymob.', previous: $exception);
        }

        $this->finish($log, $started, [
            'http_status' => $response->status(),
            'response_headers' => $this->maskHeaders($response->headers()),
            'response_body' => $maskResponse === []
                ? $response->body()
                : $this->encode($this->maskValues((array) $response->json(), $maskResponse)),
            'success' => $response->successful(),
        ]);

        return [$response, $log];
    }

    /**
     * What Paymob's dashboard shows next to every payment, so a transaction can be
     * traced to where it came from without opening our admin: which application,
     * which environment (a test run on a laptop must never look like production),
     * what was being bought and the key to find it by. Short, non-personal values
     * only — no names, phones or addresses leave for the gateway's reports.
     *
     * @param  array<string, scalar|null>  $details
     * @return array<string, scalar|null>
     */
    private function origin(string $kind, array $details): array
    {
        return [
            'app' => 'Deilar',
            'kind' => $kind,
            'env' => (string) config('app.env'),
            'host' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: (string) config('app.url'),
        ] + $details;
    }

    /** Record an exchange that arrived at our own door (callback / browser return). */
    public function recordInbound(Order|MembershipSubscription|null $order, string $event, string $method, string $url, array $headers, string $rawBody, ?int $status = null, ?string $responseBody = null, array $extra = []): OrderPaymentLog
    {
        $now = Carbon::now();

        return $this->write([
            'order_id' => $order instanceof Order ? $order->id : null,
            'membership_subscription_id' => $order instanceof MembershipSubscription ? $order->id : null,
            'event' => $event,
            'direction' => OrderPaymentLog::DIRECTION_IN,
            'method' => $method,
            'url' => $url,
            'request_headers' => $this->maskHeaders($headers),
            'request_body' => $rawBody,
            'http_status' => $status,
            'response_body' => $responseBody,
            'started_at' => $now,
            'finished_at' => $now,
            'duration_ms' => 0,
            ...$extra,
        ]);
    }

    /**
     * A log write must never cost the payment flow: if the table is locked or
     * missing, say so in the application log and carry on with an unsaved row.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function write(array $attributes): OrderPaymentLog
    {
        $attributes += [
            'correlation_id' => $this->correlationId,
            'admin_id' => $this->adminId,
            'ip_address' => $this->request?->ip(),
            'user_agent' => $this->request?->userAgent(),
        ];

        try {
            return OrderPaymentLog::create($attributes);
        } catch (Throwable $exception) {
            Log::error('Paymob exchange could not be logged.', [
                'event' => $attributes['event'] ?? null,
                'order_id' => $attributes['order_id'] ?? null,
                'membership_subscription_id' => $attributes['membership_subscription_id'] ?? null,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return new OrderPaymentLog($attributes);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function finish(OrderPaymentLog $log, float $started, array $attributes): void
    {
        $finished = microtime(true);

        try {
            $log->update($attributes + [
                'finished_at' => Carbon::createFromTimestamp($finished, config("app.timezone")),
                'duration_ms' => (int) round(($finished - $started) * 1000),
            ]);
        } catch (Throwable $exception) {
            Log::error('Paymob exchange result could not be logged.', [
                'log_id' => $log->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /** What went wrong, in a line an admin can act on. */
    private function describe(Response $response): string
    {
        $json = $response->json();
        $detail = is_array($json)
            ? ($json['detail'] ?? $json['message'] ?? $json['error'] ?? json_encode($json, JSON_UNESCAPED_UNICODE))
            : $response->body();

        return "Paymob answered {$response->status()}: ".(is_string($detail) ? $detail : json_encode($detail, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    private function maskHeaders(array $headers): array
    {
        foreach ($headers as $name => $value) {
            if (in_array(strtolower((string) $name), ['authorization', 'x-api-key', 'cookie', 'set-cookie'], true)) {
                $text = is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
                $headers[$name] = strlen($text) > 14 ? substr($text, 0, 10).'…[masked]' : '[masked]';
            }
        }

        return $headers;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function maskValues(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = '[masked]';
            }
        }

        return $data;
    }

    private function encode(array $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @return array{0: string, 1: string} */
    private function nameParts(Order $order): array
    {
        $parts = preg_split('/\s+/u', trim($order->customer_full_name), 2) ?: [];

        return [$parts[0] ?: 'NA', $parts[1] ?? 'NA'];
    }

    /** @return array<string, string> */
    private function billing(Order $order): array
    {
        [$first, $last] = $this->nameParts($order);
        $or = fn (?string $value) => filled($value) ? (string) $value : 'NA';

        return [
            'first_name' => $first,
            'last_name' => $last,
            'phone_number' => $or($order->customer_phone),
            'email' => $order->receiptEmail() ?? self::NO_EMAIL,
            'country' => 'EG',
            'state' => $or($order->customer_governorate),
            'city' => $or($order->customer_city),
            'street' => $or($order->customer_street ?? $order->customer_address),
            'building' => $or($order->customer_building_number),
            'floor' => $or($order->customer_floor_number),
            'apartment' => $or($order->customer_apartment_number),
        ];
    }

    /**
     * @return list<array{name: string, amount: int, description: string, quantity: int}>
     */
    private function items(Order $order, int $amount): array
    {
        $locale = app()->getLocale();
        $items = [];

        foreach ($order->products as $line) {
            $name = $line->name;
            $name = is_array($name) ? ($name[$locale] ?? $name['en'] ?? reset($name) ?: $line->slug) : (string) ($name ?? $line->slug);

            $items[] = [
                'name' => Str::limit((string) $name, 100, ''),
                /* line total with quantity 1: avoids a per-unit amount that does not multiply back exactly. */
                'amount' => (int) round(((float) $line->line_total) * 100),
                'description' => 'x'.$line->quantity,
                'quantity' => 1,
            ];
        }

        foreach ([['Delivery', $order->delivery_price], ['Cash on delivery fee', $order->cod_fee]] as [$label, $value]) {
            if ((float) $value > 0) {
                $items[] = ['name' => $label, 'amount' => (int) round(((float) $value) * 100), 'description' => $label, 'quantity' => 1];
            }
        }

        if (array_sum(array_column($items, 'amount')) !== $amount) {
            return [['name' => 'Order '.$order->order_code, 'amount' => $amount, 'description' => $order->order_code, 'quantity' => 1]];
        }

        return $items;
    }
}
