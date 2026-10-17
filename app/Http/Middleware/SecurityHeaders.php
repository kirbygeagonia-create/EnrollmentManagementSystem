<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Audit §4.2: baseline security headers for every response.
 *
 * Laravel does not send any of these by default. HSTS is emitted only when this application itself
 * sees the request as HTTPS: bootstrap/app.php never calls trustProxies() and no trustedproxy config
 * exists, so X-Forwarded-Proto from a terminating proxy is discarded and the header is not sent.
 * Measured 2026-10-17 and pinned by tests/Feature/SecurityHeadersTest.php.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Audit §4.3: baseline Content-Security-Policy header.
        // Fonts and images are self-hosted (public/fonts/, public/images/) — no external CDN and no
        // remote image host, so every source here is 'self', a data: / blob: form, or the dev-server
        // origins in connect-src. img-src carried a bare `https:` until 2026-10-17, when a sweep of
        // resources/js and resources/views found no remote image to justify it; the whole policy
        // string is pinned by tests/Feature/SecurityHeadersTest.php.
        $cspDirectives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "font-src 'self' data:",
            "img-src 'self' data: blob:",
            "connect-src 'self' ws: wss: http://127.0.0.1:* http://localhost:* ws://127.0.0.1:* ws://localhost:*",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ];
        $response->headers->set('Content-Security-Policy', implode('; ', $cspDirectives));

        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }
}
