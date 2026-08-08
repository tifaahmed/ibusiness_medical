<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CacheControl
{
    /**
     * Handle an incoming request and add appropriate cache control headers.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only apply caching to GET requests
        if (!$request->isMethod('GET')) {
            return $response;
        }

        // Check if response is successful
        if ($response->getStatusCode() !== 200) {
            return $response;
        }

        // Don't cache authenticated user content
        if ($request->user()) {
            $response->headers->set('Cache-Control', 'private, no-cache, no-store, must-revalidate');
            return $response;
        }

        // Static pages - cache for 1 hour
        if ($request->is('about') || $request->is('contact')) {
            $response->headers->set('Cache-Control', 'public, max-age=3600');
            return $response;
        }

        // Homepage is dynamic (offers, contracts) - no browser cache
        if ($request->is('/')) {
            $response->headers->set('Cache-Control', 'no-cache, must-revalidate');
            return $response;
        }

        // API responses - no cache by default
        if ($request->is('api/*')) {
            $response->headers->set('Cache-Control', 'no-cache, must-revalidate');
            return $response;
        }

        return $response;
    }
}
