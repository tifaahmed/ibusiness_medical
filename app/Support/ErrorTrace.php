<?php

namespace App\Support;

use Throwable;

/**
 * What actually broke behind a failed admin save, in a shape the "Advanced
 * Error Track" tab of ValidationErrorsDialog.vue can show.
 *
 * A controller that catches an exception still answers the form with a polite
 * "Failed to … Please try again." — that stays the normal tab. Alongside it,
 * it flashes `error_debug` => ErrorTrace::from($e), which HandleInertiaRequests
 * shares as `flash.error_debug`, so the admin can copy the exact exception
 * (class, message, where it was thrown, the app frames that led there) and hand
 * it to a programmer instead of digging through laravel.log.
 *
 * Admin-only: flash it from controllers behind the admin area, never from a
 * public or partner endpoint — the message can carry SQL and row values.
 */
class ErrorTrace
{
    private const MAX_FRAMES = 12;

    public static function from(Throwable $e): array
    {
        $previous = $e->getPrevious();

        return [
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => self::relative($e->getFile()),
            'line' => $e->getLine(),
            'trace' => self::appFrames($e),
            'previous' => $previous ? [
                'exception' => $previous::class,
                'message' => $previous->getMessage(),
                'file' => self::relative($previous->getFile()),
                'line' => $previous->getLine(),
            ] : null,
            'at' => now()->toIso8601String(),
        ];
    }

    /**
     * The frames inside our own code (vendor frames are noise for this), as
     * "app/…/File.php:123 Class->method()".
     */
    private static function appFrames(Throwable $e): array
    {
        $frames = [];

        foreach ($e->getTrace() as $frame) {
            $file = $frame['file'] ?? null;
            if (! $file || str_contains($file, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $call = isset($frame['class'])
                ? $frame['class'].($frame['type'] ?? '->').$frame['function'].'()'
                : ($frame['function'] ?? '').'()';

            $frames[] = self::relative($file).':'.($frame['line'] ?? '?').' '.$call;

            if (count($frames) >= self::MAX_FRAMES) {
                break;
            }
        }

        return $frames;
    }

    private static function relative(string $path): string
    {
        $base = base_path().DIRECTORY_SEPARATOR;

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
