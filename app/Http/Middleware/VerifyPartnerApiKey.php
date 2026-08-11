<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the partner API — the server-to-server endpoints other properties
 * (the Deilar marketing site) call to read member data.
 *
 * Unlike the rest of `/api/v1`, these return names, dates of birth and family
 * members, so they are not public: the caller must present the shared key in
 * `X-Api-Key`. The key belongs to a server, never to a browser.
 */
class VerifyPartnerApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.partner_api.key');

        /*
         * An unset key locks the endpoint rather than opening it — a deploy
         * that forgets PARTNER_API_KEY should fail closed.
         */
        if (! is_string($expected) || $expected === '') {
            return response()->json([
                'message' => 'The partner API is not configured.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $provided = $request->header('X-Api-Key') ?? '';

        if (! hash_equals($expected, $provided)) {
            return response()->json([
                'message' => 'Invalid API key.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
