<?php

/*
|--------------------------------------------------------------------------
| Which addresses may tell this application what a visitor really sent
|--------------------------------------------------------------------------
|
| This is the install-time half of §28.2's G-13, turned into a setting on 2026-10-26 so that whoever provisions
| the server answers it in `.env` instead of editing PHP.
|
| The middleware that consumes this key — `Illuminate\Http\Middleware\TrustProxies` — is already in the framework's
| default global stack, so nothing has to be registered to use it. At request time it prefers whatever was passed to
| `trustProxies(at: …)`, and when — as here — the application never calls that method, it falls back to
| `config('trustedproxy.proxies')` and splits a comma-separated string on the comma, trimming each part. That is why
| one line in `.env` is enough: `TRUSTED_PROXIES=10.0.0.5`, or two addresses separated by a comma. Measured on this
| build, a blank `TRUSTED_PROXIES=` arrives here as `''` rather than null, and Symfony matches an empty pattern
| against no address and throws nothing — the `?: null` below says "no proxy" in the open rather than leaving it to a
| comparison with an empty string. (The line numbers of those three framework behaviours are carried in `AGENTS.md`,
| where the citation sweeps can re-measure them; they are deliberately not repeated here, because a pointer into
| `vendor/` goes stale on a framework update and nothing in this repository would notice.)
|
| Why this is a WARN-and-name-the-address question and not a switch to flip: `Strict-Transport-Security` is sent
| only when `SecurityHeaders` sees `$request->isSecure()`, and behind a TLS terminator that is answerable only if
| the terminator's own address is trusted. **Never set `*`.** A bare star is turned into the catchalls `0.0.0.0/0`
| and `::/0`, which makes every `X-Forwarded-*` header believable from any client: the login throttle builds its key
| from `$this->ip()` (`app/Http/Requests/Auth/LoginRequest.php:97`) and every audit row stores `Request::ip()`
| (`app/Observers/AuditLogObserver.php:102`) into `auditlogs.ipAddress`, so a stranger would get to choose both its
| own rate-limit bucket and the address written into the record. A `*` written *inside* a list, and a hostname, are
| the quieter failure: they are accepted, match no client address, and leave the header discarded — so the setting
| looks filled in while nothing is trusted. `ems:preflight` refuses both.
|
| `php artisan ems:preflight`'s "Forwarded scheme" row reads this same value and reports which state this install is
| in — addresses named, a star named, a value that can match nothing, or nothing named while the host speaks HTTP.
*/

return [
    'proxies' => env('TRUSTED_PROXIES') ?: null,
];
