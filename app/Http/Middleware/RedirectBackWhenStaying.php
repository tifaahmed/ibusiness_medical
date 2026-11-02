<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Save & stay": the dashboard's save button sends `X-Save-Stay: 1`. A save that
 * succeeded then lands back on the edit page it came from instead of the list the
 * controller redirects to. One place for every form, so no controller changes.
 * Failures (validation errors) are left alone — they already go back.
 */
class RedirectBackWhenStaying
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->headers->get('X-Save-Stay') || $request->isMethod('GET') || $request->isMethod('DELETE')) {
            return $response;
        }
        if (! $response instanceof RedirectResponse) {
            return $response;
        }
        if ($request->session()->get('errors')?->any()) {
            return $response;
        }

        $referer = $request->headers->get('referer');
        $parts = $referer ? parse_url($referer) : null;
        // Same host only, and only an edit page — never a bounce anywhere else.
        if (! $parts || ($parts['host'] ?? null) !== $request->getHost() || ! str_ends_with(rtrim($parts['path'] ?? '', '/'), '/edit')) {
            return $response;
        }

        $response->setTargetUrl($referer);

        return $response;
    }
}
