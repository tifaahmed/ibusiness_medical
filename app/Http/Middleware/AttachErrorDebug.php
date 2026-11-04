<?php

namespace App\Http\Middleware;

use App\Support\CapturedError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin saves that fail inside a try/catch answer with a polite
 * "Failed to … Please try again." and nothing else, so nobody could say WHY.
 * When such a redirect carries an `error` message AND an error was logged
 * during the request, flash the details as `error_debug` (shared to the page as
 * `flash.error_debug`, drawn and copied by ValidationErrorsDialog via
 * recordResponse in utils/errorTrack.js).
 *
 * Admin area only: the message can carry SQL and row values.
 */
class AttachErrorDebug
{
    public function handle(Request $request, Closure $next): Response
    {
        CapturedError::reset();

        $response = $next($request);

        try {
            if ($request->is('admin/*') && $request->user() && $response->isRedirection() && $request->hasSession()) {
                $session = $request->session();
                $errors = $session->get('errors');
                $failed = ($errors instanceof \Illuminate\Support\ViewErrorBag && $errors->getBag('default')->has('error')) || $session->has('error');
                $captured = CapturedError::get();

                if ($failed && $captured && ! $session->has('error_debug')) {
                    $session->flash('error_debug', $captured + [
                        'request' => $request->method().' '.$request->path(),
                        'user_id' => $request->user()->id,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Diagnostics must never cost the admin the page.
        }

        return $response;
    }
}
