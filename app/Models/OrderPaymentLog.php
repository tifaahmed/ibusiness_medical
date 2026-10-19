<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One exchange with the payment gateway — a call we made, or one it (or the
 * buyer's browser) made to us — kept verbatim with its timing.
 *
 * Bodies are stored as received, not summarised: when a payment goes wrong
 * the exact bytes are the evidence. Only credentials are masked, see
 * `App\Services\Paymob\PaymobClient::maskHeaders()`.
 */
class OrderPaymentLog extends Model
{
    public const DIRECTION_OUT = 'out';

    public const DIRECTION_IN = 'in';

    public const EVENT_INTENTION = 'intention_create';

    public const EVENT_AUTH_TOKEN = 'auth_token';

    public const EVENT_CALLBACK = 'callback_received';

    public const EVENT_RETURN = 'return_redirect';

    public const EVENT_INQUIRY = 'transaction_inquiry';

    public const EVENT_REFUND = 'refund';

    /** Not an HTTP exchange: our own decision about what a transaction means. */
    public const EVENT_APPLIED = 'result_applied';

    protected $fillable = [
        'order_id', 'membership_subscription_id', 'admin_id', 'correlation_id', 'event', 'direction', 'method', 'url',
        'request_headers', 'request_body', 'http_status', 'response_headers', 'response_body',
        'gateway_code', 'gateway_ref', 'success', 'error_message',
        'started_at', 'finished_at', 'duration_ms', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'request_headers' => 'array',
            'response_headers' => 'array',
            'success' => 'boolean',
            'started_at' => 'datetime:Y-m-d H:i:s.u',
            'finished_at' => 'datetime:Y-m-d H:i:s.u',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
