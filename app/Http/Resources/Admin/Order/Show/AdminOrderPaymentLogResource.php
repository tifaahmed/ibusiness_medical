<?php

namespace App\Http\Resources\Admin\Order\Show;

use App\Models\OrderPaymentLog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * One gateway exchange for the order's "Payment" tab, with the pause since the
 * exchange before it — so the page can say not just how long Paymob took to
 * answer, but how long the buyer (or the gateway's callback) took to come back.
 */
class AdminOrderPaymentLogResource
{
    /**
     * @param  Collection<int, OrderPaymentLog>  $logs  oldest first
     * @return list<array<string, mixed>>
     */
    public static function collection(Collection $logs): array
    {
        $previous = null;
        $rows = [];

        foreach ($logs as $log) {
            $rows[] = [
                'id' => $log->id,
                'correlation_id' => $log->correlation_id,
                'event' => $log->event,
                'direction' => $log->direction,
                'method' => $log->method,
                'url' => $log->url,
                'request_headers' => $log->request_headers,
                'request_body' => $log->request_body,
                'http_status' => $log->http_status,
                'response_headers' => $log->response_headers,
                'response_body' => $log->response_body,
                'gateway_code' => $log->gateway_code,
                'gateway_ref' => $log->gateway_ref,
                'success' => $log->success,
                'error_message' => $log->error_message,
                'started_at' => $log->started_at?->format('Y-m-d H:i:s.v'),
                'finished_at' => $log->finished_at?->format('Y-m-d H:i:s.v'),
                'duration_ms' => $log->duration_ms,
                /* Milliseconds from the end of the previous exchange to the start of this one. */
                'gap_ms' => $previous?->finished_at && $log->started_at
                    ? max(0, (int) round(($log->started_at->getTimestamp() - $previous->finished_at->getTimestamp()) * 1000
                        + ((int) $log->started_at->format('v') - (int) $previous->finished_at->format('v'))))
                    : null,
                'admin' => $log->admin?->name,
                'ip_address' => $log->ip_address,
            ];

            $previous = $log;
        }

        return $rows;
    }
}
