<?php

/**
 * Audit lane 24, part 1 — the two routes that answer to nobody but APP_KEY.
 *
 * The routing census (`audit-2-routes.php`, section A) lists three named routes with no `auth`
 * middleware: Sanctum's CSRF cookie and Laravel's own `GET /storage/{path}` +
 * `PUT /storage/{path}`. The first is expected. The other two exist because
 * `config/filesystems.php` gives the `local` disk `'serve' => true`, and their only gate is
 * `hasValidRelativeSignature()` — an HMAC over the path and query keyed by `config('app.key')`.
 * There is no session, no CSRF token and no permission in front of them.
 *
 * So the question this answers by measurement, not by reading the framework:
 *
 *   1. What middleware do those two routes really carry? (If they were in the `web` group, an
 *      empty APP_KEY would break encryption before anything else; if they are not, the request
 *      reaches the signature check with nothing else to stop it.)
 *   2. Do the private upload folders actually live under the served disk root? (That decides
 *      whether a forged GET reads real signed forms and face photos.)
 *   3. Does a signature computed with the EMPTY key verify against an app whose key is empty?
 *      That is the forgery. `APP_KEY=` is what `.env.example` ships, so a server where the
 *      operator skipped DEPLOYMENT.md's `key:generate` line has a secret everybody knows.
 *   4. And the control that makes 3 mean something: the same forged signature must be REJECTED
 *      by an app that does have a key. Without this row, 3 could be a broken check that always
 *      says yes — or a check that always says no, which would make this whole probe meaningless.
 *
 * Read-only: no file is written, no database is touched, the real APP_KEY is never printed.
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$root = dirname(__DIR__, 3);

echo "== 1. THE UNAUTHENTICATED SURFACE, WITH ITS REAL MIDDLEWARE ==\n";
foreach (Route::getRoutes() as $r) {
    $mw = array_values(array_filter($r->gatherMiddleware(), 'is_string'));
    $needsAuth = in_array('auth', $mw, true) || in_array('web', $mw, true) || in_array('auth:sanctum', $mw, true);
    if ($needsAuth || in_array('guest', $mw, true)) {
        continue;
    }
    printf("  %-9s %-24s %-24s middleware: %s\n",
        implode('|', array_diff($r->methods(), ['HEAD'])),
        '/'.$r->uri(),
        (string) $r->getName(),
        $mw === [] ? '(NONE)' : implode(', ', $mw));
}

echo "\n== 2. WHAT A FORGED GET /storage WOULD BE READING ==\n";
foreach (['local', 'documents', 'public'] as $disk) {
    $cfg = (array) config("filesystems.disks.{$disk}");
    $abs = str_replace('\\', '/', (string) ($cfg['root'] ?? ''));
    $served = ($cfg['serve'] ?? false) ? 'SERVED' : 'not served';
    $count = 0;
    $bytes = 0;
    if (is_dir($abs)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile()) {
                $count++;
                $bytes += $f->getSize();
            }
        }
    }
    printf("  %-10s root=%-34s %-10s visibility=%-8s %d file(s), %s KB\n",
        $disk,
        str_replace($root.'/', '', $abs),
        $served,
        (string) ($cfg['visibility'] ?? 'private (default)'),
        $count,
        round($bytes / 1024));
}

echo "\n== 3/4. THE FORGERY AND ITS CONTROL ==\n";
$realKey = (string) config('app.key');
echo '  the installed key is a '.strlen($realKey).' character base64 string'
    .($realKey === '' ? ' — EMPTY' : ', not the value .env.example ships')."\n";

/**
 * A signed upload URL for the probe path, minted under whichever key the app currently carries.
 * `upload=1` has to be inside the signature: ReceiveFile demands both the flag and a signature
 * that covers the whole query, so adding it after signing would break the forgery.
 */
$forge = function (): string {
    $url = URL::temporarySignedRoute('storage.local.upload', now()->addMinutes(5), [
        'path' => 'preflight-probe-do-not-write.txt',
        'upload' => 1,
    ]);
    $parts = parse_url($url);

    return ($parts['path'] ?? '/').'?'.($parts['query'] ?? '');
};

$judge = function (string $label, string $key, string $relative): bool {
    config(['app.key' => $key]);
    $verifies = Request::create($relative, 'PUT')->hasValidRelativeSignature();
    printf("  %-50s app key %-10s → signature %s\n",
        $label,
        $key === '' ? 'EMPTY' : 'installed',
        $verifies ? 'ACCEPTED (the write would run)' : 'rejected');

    return $verifies;
};

// The attack: an outsider who knows the server kept `.env.example`'s blank key mints its own
// signature. That needs no secret, so it is generated with the same code path.
config(['app.key' => '']);
$blankKeyUrl = $forge();

$a = $judge('forged with the public empty secret → keyless server', '', $blankKeyUrl);
$b = $judge('the SAME forgery → server that ran key:generate', $realKey, $blankKeyUrl);

echo "\n== VERDICT ==\n";
if (! $a) {
    echo "  NO CONCLUSION: the forgery did not verify even against an empty key, so this probe\n"
        ."  proved nothing about the route. Read SignatureResolver before writing a rule.\n";
    exit(2);
}
if ($b) {
    echo "  NO CONCLUSION: an empty-key forgery also passed against the installed key — the check\n"
        ."  is not comparing what this probe thinks it compares.\n";
    exit(2);
}
echo "  PROVEN: /storage/{path} is guarded by APP_KEY and nothing else. An install that never\n"
    ."  ran `php artisan key:generate` — which is the state .env.example leaves it in — takes\n"
    ."  unauthenticated writes into the folder that holds every uploaded paper and face photo.\n";
exit(0);
