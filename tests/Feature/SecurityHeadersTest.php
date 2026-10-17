<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The baseline security headers `App\Http\Middleware\SecurityHeaders` puts on every web response.
 *
 * Audit §4.2 added the middleware and the Round-3 assessment (2026-09-05) recorded the CSP as its one
 * open item — a claim that was already false in the commit that stored it (`d29653e` set the header in
 * the same batch). Nothing pinned any of these headers before this file, which is why the report could
 * be wrong about its own build and stay wrong for six weeks.
 *
 * The CSP is asserted as the whole string, not as a substring: a later edit that quietly widens
 * `script-src` or drops `frame-ancestors` has to be a deliberate change here.
 *
 * The three HSTS cases are separate test methods on purpose. An https call leaves `$_SERVER['HTTPS']='on'`
 * set for every later request in the same test — measured 2026-10-17 — so combined, the forwarded-header
 * case passed on the previous case's leftover state and proved nothing.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    private const CSP = "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
        ."style-src 'self' 'unsafe-inline'; font-src 'self' data:; img-src 'self' data: blob:; "
        ."connect-src 'self' ws: wss: http://127.0.0.1:* http://localhost:* ws://127.0.0.1:* ws://localhost:*; "
        ."frame-ancestors 'none'; form-action 'self'; base-uri 'self'; object-src 'none'";

    #[Test]
    public function a_guest_page_carries_the_four_unconditional_headers_and_the_csp(): void
    {
        $this->get('/login')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('Content-Security-Policy', self::CSP);
    }

    #[Test]
    public function hsts_is_absent_over_plain_http(): void
    {
        $this->get('/login')->assertOk()->assertHeaderMissing('Strict-Transport-Security');
    }

    #[Test]
    public function hsts_is_sent_when_the_app_itself_terminates_tls(): void
    {
        // Request::create() derives HTTPS from the URI scheme, so an https URL is how to ask for a
        // secure request; passing SERVER['HTTPS'] to $this->call() is overwritten by it.
        $this->call('GET', 'https://localhost/login')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    #[Test]
    public function a_proxy_forwarding_https_does_not_by_itself_enable_hsts(): void
    {
        // bootstrap/app.php never calls trustProxies(), and Illuminate\Http\Middleware\TrustProxies
        // leaves $proxies null and config('trustedproxy.proxies') is unset, so Symfony treats no address
        // as a proxy and discards X-Forwarded-Proto. The middleware's comment says the forwarded header
        // is enough; measured on this build, it is not — so behind a proxy that terminates TLS, HSTS is
        // never emitted and an operator has to trust the proxy explicitly. Recorded as an open item
        // rather than fixed here, because "trust every caller that claims to be my proxy" is a security
        // decision for the owner, not a documentation fix.
        $this->get('/login', ['X_FORWARDED_PROTO' => 'https'])
            ->assertOk()
            ->assertHeaderMissing('Strict-Transport-Security');
    }
}
