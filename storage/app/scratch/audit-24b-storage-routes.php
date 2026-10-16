<?php

/**
 * Audit lane 24, part 2 — drive the real /storage/{path} routes through the HTTP kernel.
 *
 * Part 1 (`audit-24a-appkey.php`) refused its own conclusion: the forgery did not verify, but so
 * did nothing else, which is the signature of a probe with the wrong request shape rather than a
 * server that is safe. This file removes the guesswork by asking the route itself, through the
 * same kernel a request from a desk takes, and by running the row that must succeed first:
 *
 *   R1  signed with the installed key      → the app HAS a key       expected 204 (write runs)
 *   R2  signed with the installed key      → the app has NO key      expected 403/404
 *   R3  signed with the empty public key   → the app has NO key      expected ?  ← the question
 *   R4  signed with the empty public key   → the app HAS a key       expected 403/404
 *   R5  no signature at all                → the app HAS a key       expected 403/404
 *
 * R1 is the control that makes every other row mean something: if the route refuses a genuinely
 * signed upload, then R3's rejection would prove nothing about safety. R4/R5 are the negative
 * controls, and R3 is the finding or its absence.
 *
 * This WRITES files, deliberately and narrowly: each accepted row puts a few bytes into
 * storage/app/private under a name no real upload can have, and the folder is restored to its
 * exact starting contents at the end (count verified, and each probe file unlinked by name —
 * nothing that was there before the probe is touched). It never touches a database.
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

$root = dirname(__DIR__, 3);

function boot(): array
{
    $app = require dirname(__DIR__, 3).'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    return [$app, $app->make(Kernel::class)];
}

/** Every file currently under the served disk root, so the probe can prove it left none behind. */
$listing = function (): array {
    $dir = dirname(__DIR__, 3).'/storage/app/private';
    $out = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
        if ($f->isFile()) {
            $out[str_replace('\\', '/', $f->getPathname())] = $f->getSize();
        }
    }
    asort($out);

    return $out;
};

$before = $listing();
echo 'storage/app/private before the probe: '.count($before)." file(s)\n\n";

// Read the installed key once, before any row mutates the config, so a row can sign with it
// without printing it.
[$bootApp] = boot();
$realKey = (string) config('app.key');
if ($realKey === '') {
    echo "  NO CONCLUSION: this install has no APP_KEY at all, so 'the keyed server' row cannot be\n"
        ."  built here. Fix .env first — the probe would then be measuring the wrong machine.\n";
    exit(2);
}

/**
 * One row: mint a signed upload URL under $signKey (or unsigned, when null), then hand the app
 * $serverKey as its own key and PUT to that URL. Returns the status the route answered.
 *
 * `$absolute = false` is the whole reason part 2 exists. `UrlGenerator::signedRoute()` hashes
 * whatever `route()` returns, and `route()` returns the ABSOLUTE url unless told otherwise —
 * while `ReceiveFile` verifies with `hasValidRelativeSignature()`, which hashes
 * `/{path}?{query}`. Sign the ordinary way and the route refuses a perfectly good signature
 * (that was part 1's HTTP 403 on every row). The attacker's forgery has to be built the same
 * relative way, so both sides of this probe use it.
 */
$run = function (string $label, ?string $signKey, string $serverKey, string $file) use (&$app, $realKey): int {
    [$app, $kernel] = boot();

    config(['app.key' => $signKey ?? $realKey]);
    $url = $signKey === null
        ? '/storage/'.$file.'?expires='.now()->addMinutes(5)->timestamp.'&upload=1'
        : URL::temporarySignedRoute('storage.local.upload', now()->addMinutes(5), [
            'path' => $file,
            'upload' => 1,
        ], false);

    config(['app.key' => $serverKey]);
    $status = $kernel->handle(
        Request::create('http://localhost'.$url, 'PUT')
    )->getStatusCode();

    printf("  %-42s signed with %-22s app key %-12s → HTTP %d\n",
        $label,
        $signKey === null ? '(NOT SIGNED)' : ($signKey === '' ? 'EMPTY secret' : 'installed key'),
        $serverKey === '' ? 'EMPTY' : 'installed',
        $status);

    return $status;
};

$r1 = $run('R1 genuine signature, keyed server', $realKey, $realKey, 'lane24-r1-delete-me.txt');
$r2 = $run('R2 genuine signature, keyless server', $realKey, '', 'lane24-r2-delete-me.txt');
$r3 = $run('R3 forgery from the public empty key', '', '', 'lane24-r3-delete-me.txt');
$r4 = $run('R4 forgery from the public empty key', '', $realKey, 'lane24-r4-delete-me.txt');
$r5 = $run('R5 no signature at all', null, $realKey, 'lane24-r5-delete-me.txt');

echo "\n== CLEAN-UP ==\n";
$after = $listing();
$added = array_diff_key($after, $before);
foreach (array_keys($added) as $path) {
    if (! str_contains(basename($path), 'lane24-')) {
        echo '  REFUSING TO DELETE a file the probe did not name: '.$path."\n";
        exit(3);
    }
    unlink($path);
    echo '  removed '.basename($path)."\n";
}
$final = $listing();
echo '  folder restored: '.($final === $before ? 'YES, identical to the starting listing' : 'NO — DIFFERENT')."\n";

echo "\n== VERDICT ==\n";
if ($r1 >= 400) {
    echo "  NO CONCLUSION: the route refused even a genuine signature (HTTP $r1), so none of the\n"
        ."  other rows tested what they claim. Read the middleware stack before believing this.\n";
    exit(2);
}
if ($r3 < 400) {
    echo "  PROVEN: an unauthenticated PUT that carries only a signature made with the EMPTY\n"
        ."  secret — public knowledge, because that is what .env.example ships — writes into\n"
        ."  storage/app/private on a server whose APP_KEY was never generated (HTTP $r3).\n";
} else {
    echo "  NOT PROVEN: the empty-key forgery was refused (HTTP $r3), so this route does not\n"
        ."  depend on APP_KEY the way the argument assumed. Say what does guard it instead.\n";
}
printf("  controls: R2 (real sig on keyless server)=%d  R4 (forgery on keyed server)=%d  R5 (unsigned)=%d\n",
    $r2, $r4, $r5);
