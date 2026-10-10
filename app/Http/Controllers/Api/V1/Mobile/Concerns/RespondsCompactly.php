<?php

namespace App\Http\Controllers\Api\V1\Mobile\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The mobile app's response envelope.
 *
 * - Unescaped unicode: Arabic costs 2 bytes a letter instead of the 6 that
 *   `\uXXXX` escapes take, which is most of the payload on a text-heavy list.
 * - A strong ETag over the body: a repeat request the app already holds is
 *   answered `304` with no body at all (the browser's HTTP cache does the
 *   `If-None-Match` dance without any client code).
 * - `Cache-Control: public`, short `max-age` plus `stale-while-revalidate`, so
 *   going back to a screen paints from cache instantly and refreshes behind it.
 */
trait RespondsCompactly
{
    /**
     * @param  array<mixed>  $payload
     */
    protected function respond(Request $request, array $payload, int $maxAge = 60): JsonResponse
    {
        $response = response()->json($payload, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response->setEtag(md5((string) $response->getContent()));
        $response->headers->set(
            'Cache-Control',
            "public, max-age={$maxAge}, stale-while-revalidate=".($maxAge * 5),
        );
        $response->headers->set('Vary', 'Accept-Language, X-Locale');
        $response->isNotModified($request);

        return $response;
    }
}
