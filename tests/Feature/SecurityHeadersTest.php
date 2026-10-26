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
 * The HSTS cases are separate test methods on purpose. An https call leaves `$_SERVER['HTTPS']='on'`
 * set for every later request in the same test — measured 2026-10-17 — so combined, the forwarded-header
 * case passed on the previous case's leftover state and proved nothing. The pair added on 2026-10-26
 * (`naming_the_peer_address…` and `an_address_that_is_not_the_peer…`) is what makes the default-state case a
 * measurement rather than an absence: the same request shape, with only the trusted address differing.
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
        // The default state of an install: nothing is named in TRUSTED_PROXIES, so config/trustedproxy.php answers
        // null, Illuminate\Http\Middleware\TrustProxies therefore trusts no address, and Symfony discards
        // X-Forwarded-Proto. The forwarded header on its own is not enough — which is what the two cases after this
        // one measure from the other side, so that this case cannot be passing merely because the header never
        // reached the request in the first place. Recorded as §28.2 G-13, ruled on 2026-10-17 and turned into a
        // setting on 2026-10-26: the operator names the terminator's address and nothing else.
        config()->set('trustedproxy.proxies', null);

        $this->get('/login', ['X-Forwarded-Proto' => 'https'])
            ->assertOk()
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    #[Test]
    public function naming_the_peer_address_in_trustedproxy_config_lets_the_forwarded_scheme_through(): void
    {
        // The wiring claim in config/trustedproxy.php: the middleware that runs on this route reads that key at
        // request time (vendor/laravel/framework/src/Illuminate/Http/Middleware/TrustProxies.php:69), so an
        // installer who sets one value in .env gets HTTPS-only headers without editing a line of PHP. Trusting
        // 127.0.0.1 is safe *in a test* — the test peer is that address — and the same value on a server that
        // answers on the LAN would be the `REMOTE_ADDR` shape ems:preflight refuses.
        config()->set('trustedproxy.proxies', '127.0.0.1');

        $this->get('/login', ['X-Forwarded-Proto' => 'https'])
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        config()->set('trustedproxy.proxies', null);
    }

    #[Test]
    public function an_address_that_is_not_the_peer_still_discards_the_forwarded_scheme(): void
    {
        // The other half of the pair, and the reason the case above is a measurement: the trust is scoped to the
        // named address, so naming some other machine changes nothing for this caller. Were this to send HSTS,
        // the setting would be a wildcard wearing an address.
        config()->set('trustedproxy.proxies', '10.9.9.9');

        $this->get('/login', ['X-Forwarded-Proto' => 'https'])
            ->assertOk()
            ->assertHeaderMissing('Strict-Transport-Security');

        config()->set('trustedproxy.proxies', null);
    }
}
