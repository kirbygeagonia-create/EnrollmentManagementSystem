<?php

/**
 * Read-only: what each TRUSTED_PROXIES spelling actually does, and what ems:preflight says about it.
 *
 * G-13 became a setting on 2026-10-26 (lane 73b): `config/trustedproxy.php` answers `env('TRUSTED_PROXIES')`, and
 * `Illuminate\Http\Middleware\TrustProxies` — which is already in the global stack — falls back to that key at
 * request time. Two things had to be measured rather than assumed, because both decide what the preflight row is
 * allowed to promise an installer:
 *
 *  1. that a value in `.env` really reaches the request. If this table's first trusted row does NOT flip
 *     `isSecure()`, the setting is inert and the row's OK verdict is decoration. The probe refuses on that.
 *  2. which spellings do nothing. Symfony validates nothing when the list is set (`Request::setTrustedProxies`
 *     stores the array and `IpUtils::checkIp` just never matches), so a hostname, a `*` inside a list, or a stray
 *     space is accepted and silently trusts nobody — the state that reads like a fixed install and is not.
 *
 * The two columns are printed side by side on purpose: a setting whose effect and whose reported verdict disagree
 * is the finding, and this is the only place both are read from the same value in the same run.
 *
 *   php storage/app/scratch/lane73b-probe-proxy-shapes.php
 */

// The imports sit ABOVE the bootstrap on purpose. Pint's fully_qualified_strict_types shortens
// `Illuminate\Contracts\Console\Kernel::class` in the bootstrap line to `Kernel::class` and files the import it
// needs BELOW that call, where it did not apply to it — measured: `ReflectionException: Class "Kernel" does not
// exist`. The file parses, `pint --test` calls it clean, and it fatals at runtime (lane 65's trap, met a third
// time, by this file, on the first format pass). Run the script, not just the formatter.
use App\Console\Commands\Preflight;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

require __DIR__.'/../../../vendor/autoload.php';
$app = require_once __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$cases = [
    'unset (null)' => null,
    'blank string' => '',
    'bare star *' => '*',
    'bare star-pair **' => '**',
    'one address' => '10.0.0.5',
    'two, comma string' => '10.0.0.5, 10.0.0.6',
    'one address as array' => ['10.0.0.5'],
    'CIDR block' => '10.0.0.0/29',
    'star inside a list' => '10.0.0.5,*',
    'a hostname' => 'proxy-server',
    'PRIVATE_SUBNETS' => 'PRIVATE_SUBNETS',
    'REMOTE_ADDR' => 'REMOTE_ADDR',
];

/**
 * The two peers worth asking about. 8.8.8.8 is used as the stranger rather than a documentation-range address,
 * because Symfony's PRIVATE_SUBNETS list contains 192.0.2.0/24, 198.51.100.0/24 AND 203.0.113.0/24 — measured,
 * `vendor/symfony/http-foundation/IpUtils.php:21-41` — so TEST-NET addresses are trusted by the very setting this
 * table is judging, and a "stranger" picked from them would hide the defect instead of showing it.
 */
$build = fn (string $peer): Request => Request::create('http://localhost/login', 'GET', [], [], [], [
    'REMOTE_ADDR' => $peer,
    'HTTP_X_FORWARDED_PROTO' => 'https',
    'HTTP_X_FORWARDED_FOR' => '8.8.4.4',
    'HTTP_HOST' => 'localhost',
]);

$command = $app->make(Preflight::class);
$readRow = new ReflectionMethod(Preflight::class, 'forwardedSchemeIsDecided');
$readRow->setAccessible(true);

/** Drive one spelling of the setting through the real middleware and say what the request ended up looking like. */
$effect = function (mixed $value, string $peer) use ($build): bool {
    config(['trustedproxy.proxies' => $value]);
    $request = $build($peer);
    (new TrustProxies)->handle($request, fn (Request $req) => $req);
    config(['trustedproxy.proxies' => null]);

    return $request->isSecure();
};

printf("%-22s | %-8s | %-8s | %-10s | %s\n", 'TRUSTED_PROXIES', 'from the', 'from a', 'row, over', 'what the row tells the installer');
printf("%-22s | %-8s | %-8s | %-10s | %s\n", str_repeat('-', 22), 'proxy', 'stranger', 'http/https', str_repeat('-', 46));

$rows = 0;

foreach ($cases as $label => $value) {
    $fromProxy = $effect($value, '10.0.0.5');
    $fromStranger = $effect($value, '8.8.8.8');

    config(['trustedproxy.proxies' => $value]);
    config(['app.url' => 'http://localhost:8080']);
    [, $overHttp, $detail] = $readRow->invoke($command);
    config(['app.url' => 'https://ems.example.edu.ph']);
    [, $overHttps, $detailHttps] = $readRow->invoke($command);

    printf(
        "%-22s | %-8s | %-8s | %-10s | %s\n",
        $label,
        $fromProxy ? 'SECURE' : 'http',
        $fromStranger ? 'SECURE' : 'http',
        $overHttp.'/'.$overHttps,
        mb_substr(preg_replace('/\s+/', ' ', $detail === $detailHttps ? $detail : $detailHttps), 0, 78)
    );

    $rows++;
}

// The one assertion this probe exists to make: the setting is not inert.
if (! $effect('10.0.0.5', '10.0.0.5')) {
    echo "REFUSED: config('trustedproxy.proxies') did NOT reach the request — the middleware never read it, so\n"
        ."        every OK verdict in the table above came from a setting that does nothing. Nothing here is wired.\n";

    exit(1);
}

if ($effect('10.0.0.5', '8.8.8.8')) {
    echo "REFUSED: a stranger address was believed, so the trust is not scoped — this is the `*` shape.\n";

    exit(1);
}

if ($rows !== count($cases)) {
    echo 'REFUSED: walked '.$rows.' of '.count($cases)." cases.\n";

    exit(1);
}

echo "wired: the named address is honoured (SECURE from the proxy) and a stranger's X-Forwarded-Proto is still\n"
    ."    discarded (http) — both verified through the middleware in this same run, not assumed.\n";

echo "\nread the middle two columns as the effect and the fourth as the promise; a row where they disagree is the\n"
    ."    finding. SECURE-from-a-stranger is the state G-13 refuses, and 'a hostname' / 'star inside a list' are the\n"
    ."    spellings that install silently doing nothing — which is why the row calls them WARN, not a count.\n";

// The figure the packet quotes ("for twelve spellings") is answered here, so a later edit that drops or adds a case
// moves this line and the document's sentence together instead of leaving the sentence to drift (lane 59's rule: a
// figure is only safe when the named tool prints it).
printf("%d spellings walked against the middleware and the row in the same run.\n", $rows);
