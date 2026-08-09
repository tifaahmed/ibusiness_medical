<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Add security headers. Values are mirrored in public/.htaccess so that
        // static files (assets, uploads) get the same treatment as app responses.
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN', false);
        $response->headers->set('X-Content-Type-Options', 'nosniff', false);
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none', false);
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin', false);
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()', false);

        // HSTS only over HTTPS — sending it over plain HTTP is ignored by browsers
        // and pinning the header before a certificate exists locks users out.
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains', false);
        }

        // X-XSS-Protection is deliberately omitted: it is deprecated and its
        // filter can itself introduce vulnerabilities in older browsers.
        $response->headers->remove('X-Powered-By');

        // Report-only for now: violations are logged to the browser console
        // without blocking anything. Once the console is clean, rename the
        // header to Content-Security-Policy to start enforcing it.
        $response->headers->set('Content-Security-Policy-Report-Only', $this->contentSecurityPolicy(), false);

        return $response;
    }

    /**
     * The third-party origins here are the ones the layout actually pulls from:
     * fonts.bunny.net (Figtree), unicons.iconscout.com and cdnjs.cloudflare.com
     * (icon fonts), cdn.tiny.cloud (TinyMCE editor).
     *
     * 'unsafe-inline' is still needed for the inline performance script in
     * app.blade.php and for component styles; 'unsafe-eval' for the PDF/canvas
     * libraries. Both can be dropped later by moving to nonces.
     */
    protected function contentSecurityPolicy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tiny.cloud https://cdnjs.cloudflare.com https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://unicons.iconscout.com https://cdnjs.cloudflare.com https://cdn.tiny.cloud https://cdn.jsdelivr.net",
            "font-src 'self' data: https://fonts.bunny.net https://unicons.iconscout.com https://cdnjs.cloudflare.com https://cdn.tiny.cloud",
            "img-src 'self' data: blob: https://images.unsplash.com https://cdn.tiny.cloud",
            "connect-src 'self' https://cdn.tiny.cloud",
            "frame-src 'self' blob:",
            "worker-src 'self' blob:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }
}
