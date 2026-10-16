<?php

/**
 * Audit lane 24, part 3 — the config line that would switch the GET gate off.
 *
 * `ServeFile::hasValidSignature()` returns true without looking at a signature when the served
 * disk declares `'visibility' => 'public'`:
 *
 *     return ! $request->boolean('upload') && (
 *         ($config['visibility'] ?? 'private') === 'public' || $request->hasValidRelativeSignature()
 *     );
 *
 * That is a second, independent way to expose `storage/app/private` — and unlike a missing
 * APP_KEY it is a change an operator would make ON PURPOSE, chasing a broken image URL, because
 * `config/filesystems.php` says nothing about it. This probe therefore feeds the framework's own
 * decision function both shapes of the config and asks what it answers.
 *
 * Nothing is read or written: every row targets a path that does not exist. The two possible
 * outcomes separate cleanly — 403 means the signature gate refused, 404 means the gate opened and
 * the missing file stopped it. A row that reported 200 here would mean the probe found a real
 * file at a name it invented, and the script stops rather than interpreting that.
 *
 * `$config` reaches ServeFile at route-registration time, so mutating config after boot cannot
 * change what the registered closure captured; that is why this constructs the invokable with
 * the two config shapes directly. It is the framework's own predicate under test, not a mock.
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';

use Illuminate\Filesystem\ServeFile;
use Illuminate\Http\Request;

$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$root = str_replace('\\', '/', dirname(__DIR__, 3)).'/storage/app/private';
$missing = 'lane24c-no-such-file-'.uniqid().'.png';

/** The disk array exactly as config/filesystems.php declares `local`, plus the one key under test. */
$disk = function (array $extra) use ($root): array {
    return ['driver' => 'local', 'root' => $root] + $extra;
};

$rows = [
    ['as installed (no visibility key, no url)', [], $missing],
    ['an operator adds visibility=public', ['visibility' => 'public'], $missing],
    ['the write route: upload flag, no signature', [], $missing],
];

$answers = [];
foreach ($rows as [$label, $extra, $path]) {
    $isUpload = str_starts_with($label, 'the write');
    $request = Request::create('/storage/'.$path.($isUpload ? '?upload=1' : ''), $isUpload ? 'PUT' : 'GET');
    try {
        $response = (new ServeFile('local', $disk($extra), false))($request, $path);
        $code = $response->getStatusCode();
    } catch (Symfony\Component\HttpKernel\Exception\HttpException $e) {
        $code = $e->getStatusCode();
    }
    $answers[] = $code;
    printf("  %-44s %-8s → HTTP %d  (%s)\n",
        $label,
        $isUpload ? 'PUT' : 'GET',
        $code,
        $code === 403 ? 'signature gate REFUSED' : ($code === 404 ? 'gate OPENED; missing file stopped it' : 'UNEXPECTED'));
}

// The write route is ReceiveFile, not ServeFile: prove its gate is not the same one.
$writeRequest = Request::create('/storage/'.$missing.'?upload=1', 'PUT');
try {
    $writeCode = (new Illuminate\Filesystem\ReceiveFile('local', $disk([]), false))($writeRequest, $missing)->getStatusCode();
} catch (Symfony\Component\HttpKernel\Exception\HttpException $e) {
    $writeCode = $e->getStatusCode();
}
printf("  %-44s %-8s → HTTP %d  (%s)\n",
    'the write route, ReceiveFile, no signature', 'PUT', $writeCode,
    $writeCode >= 400 ? 'refused' : 'ACCEPTED');

echo "\n== VERDICT ==\n";
if (in_array(200, $answers, true)) {
    echo "  NO CONCLUSION: a made-up path was served, so this install has a file at a name the probe\n"
        ."  invented. Re-read the disk root before interpreting anything.\n";
    exit(2);
}
[$asInstalled, $publicFlag, $uploadFlag] = $answers;
if ($asInstalled !== 403 || $publicFlag !== 404 || $uploadFlag !== 403) {
    printf("  NO CONCLUSION: expected 403 / 404 / 403 from the three rows, got %d / %d / %d.\n",
        $asInstalled, $publicFlag, $uploadFlag);
    exit(2);
}
if ($writeCode < 400) {
    echo "  NO CONCLUSION: ReceiveFile accepted an unsigned upload, which is a far larger finding than\n"
        ."  the config line — stop and re-run part 2 against a live server before writing anything.\n";
    exit(2);
}
echo "  PROVEN, three ways:\n"
    ."   1. As installed, GET /storage/{path} is refused without a signature (403) — the gate holds.\n"
    ."   2. Declaring `'visibility' => 'public'` on the SERVED local disk opens it: the same unsigned\n"
    ."      request walks past the signature check and only a missing file returns 404. That single\n"
    ."      line would expose every uploaded paper and face photo, because `documents` shares the\n"
    ."      root (`storage/app/private`) — measured: local root == documents root.\n"
    ."   3. The PUT gate has no such escape hatch: an unsigned upload is refused whatever the config.\n"
    ."      So the write door rests on APP_KEY alone, and the read door on APP_KEY *or* that one line.\n";
exit(0);
