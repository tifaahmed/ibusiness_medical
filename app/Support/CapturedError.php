<?php

namespace App\Support;

use Illuminate\Log\Events\MessageLogged;
use Throwable;

/**
 * The first error-level log line of the current request, kept so a caught
 * exception can reach the admin's "Advanced Error Track" without every
 * controller having to flash it by hand.
 *
 * Why central: ~110 admin controllers catch an exception, `Log::error()` it
 * and answer with a polite "Failed to … Please try again." Only a few of them
 * also flashed {@see ErrorTrace}. {@see \App\Http\Middleware\AttachErrorDebug}
 * now turns this capture into `flash.error_debug` for every one of them.
 *
 * The FIRST error wins: that is the closest to the real fault, later lines are
 * the same exception being logged again by the layers above.
 */
class CapturedError
{
    private static ?array $first = null;

    public static function reset(): void
    {
        self::$first = null;
    }

    public static function get(): ?array
    {
        return self::$first;
    }

    public static function listen(MessageLogged $event): void
    {
        if (self::$first !== null || ! in_array($event->level, ['error', 'critical', 'alert', 'emergency'], true)) {
            return;
        }

        $c = $event->context;

        if (($c['exception'] ?? null) instanceof Throwable) {
            self::$first = ErrorTrace::from($c['exception']) + ['log' => $event->message];

            return;
        }

        // The house style: 'error_message' + 'error_trace' (a string), sometimes 'error' / 'message' / 'reason'.
        $message = $c['error_message'] ?? $c['error'] ?? $c['exception_message'] ?? $c['message'] ?? $c['reason'] ?? null;
        $trace = $c['error_trace'] ?? $c['trace'] ?? null;
        $frames = is_string($trace)
            ? array_slice(array_values(array_filter(array_map('trim', explode("\n", $trace)), fn ($l) => $l !== '' && ! str_contains($l, '/vendor/'))), 0, 12)
            : [];

        self::$first = [
            'exception' => is_string($c['exception'] ?? null) ? $c['exception'] : 'Logged error',
            'message' => is_scalar($message) ? (string) $message : $event->message,
            'code' => null,
            'file' => null,
            'line' => null,
            'trace' => $frames,
            'previous' => null,
            'at' => now()->toIso8601String(),
            'log' => $event->message,
        ];
    }
}
