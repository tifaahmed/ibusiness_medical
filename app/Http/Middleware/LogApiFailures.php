<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Files every API answer of 400 or above in `storage/logs/failures.log` — the
 * permanent file (never rotated, never pruned) that is read back with
 * `php artisan logs:failures`. The daily log only keeps today, so this is the
 * one place a failure from last week can still be found.
 *
 * Only the NAMES of the fields sent are kept, never their values: phones,
 * national IDs, emails and passwords stay out of a file nobody prunes.
 */
class LogApiFailures
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            if ($request->is('api/*') && $response->getStatusCode() >= 400 && $request->method() !== 'OPTIONS') {
                $this->record($request, $response);
            }
        } catch (\Throwable $e) {
            // A broken log line must never cost the caller its answer.
            Log::warning('LogApiFailures could not write', ['error' => $e->getMessage()]);
        }

        return $response;
    }

    private function record(Request $request, Response $response): void
    {
        $status = $response->getStatusCode();

        $context = [
            'kind' => 'api',
            'status' => $status,
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'route' => $request->route()?->getName(),
            'user_id' => $request->user('sanctum')?->id,
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'fields' => array_keys($request->except(['password', 'password_confirmation', 'token', 'otp', 'code'])),
        ];

        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);
            $context['message'] = is_array($data) ? mb_substr((string) ($data['message'] ?? ''), 0, 500) : null;
            if (is_array($data) && isset($data['errors']) && is_array($data['errors'])) {
                $context['errors'] = $data['errors'];
            }
            if (is_array($data) && isset($data['reason'])) {
                $context['reason'] = $data['reason'];
            }
        }

        $exception = $response->exception ?? null;
        if ($exception instanceof \Throwable) {
            $context['exception'] = get_class($exception);
            $context['exception_message'] = mb_substr($exception->getMessage(), 0, 1000);
            if ($status >= 500) {
                $context['at'] = $exception->getFile().':'.$exception->getLine();
                $context['trace'] = mb_substr($exception->getTraceAsString(), 0, 4000);
            }
        }

        $message = "API {$status} {$context['method']} {$context['path']}";

        $status >= 500
            ? Log::channel('failures')->error($message, $context)
            : Log::channel('failures')->warning($message, $context);
    }
}
