<?php

/**
 * DEPLOYMENT.md §6 — the seal does not travel over HTTP, so the printing box needs no APP_URL.
 *
 * The note told an operator that "the templates reference the letterhead logo through `asset()`, so an
 * absolute APP_URL must be reachable from the printing process for the seal to appear". Measured
 * 2026-10-13, `asset(` occurs 0 times in the six files under resources/views/prints/: each one embeds
 * `\App\Support\PrintAssets::logoDataUri()`, which reads `public_path('images/logos/seait-logo.png')`
 * from disk — its own docblock says the data URI exists precisely because an asset() URL sends the
 * headless browser back into the app and deadlocks a single-threaded server. The authority docx
 * already states the truth ("the letterhead image … is embedded as a data URI"); this file was the
 * one left behind, and an operator following it would have hunted for a reachability problem that
 * does not exist.
 *
 * Asserts one match before writing, then re-reads to confirm the new sentence landed and the old
 * claim is gone, with the file's CRLF intact.
 */

$file = dirname(__DIR__, 3).'/DEPLOYMENT.md';
$raw = file_get_contents($file);
$eol = str_contains($raw, "\r\n") ? "\r\n" : "\n";

$find = implode($eol, [
    'Note that the templates reference the letterhead logo through `asset()`, so an',
    'absolute `APP_URL` must be reachable from the printing process for the seal to appear.',
]);

$replace = implode($eol, [
    'The letterhead does not travel over HTTP. Every print template embeds it as a data URI',
    '(`App\Support\PrintAssets::logoDataUri()` reads `public/images/logos/seait-logo.png` from disk,',
    'measured 2026-10-13: `asset(` appears 0 times in the six files under `resources/views/prints/`),',
    'so the seal needs no `APP_URL` reachable from the printing process — that inlining is what stops',
    'a single-threaded server deadlocking on the render, and it is why a PDF is correct with the',
    'network down. `APP_URL` still matters to the desks: it is the base of every link and asset the',
    'browser loads.',
]);

$hits = substr_count($raw, $find);
if ($hits !== 1) {
    fwrite(STDERR, sprintf("REFUSED: the anchor matched %d times, expected exactly 1.\n", $hits));
    exit(1);
}

file_put_contents($file, str_replace($find, $replace, $raw));

$back = file_get_contents($file);
$new = substr_count($back, 'The letterhead does not travel over HTTP.');
$old = substr_count($back, 'reference the letterhead logo through');
$doubled = substr_count($back, "\r\r\n");
$bareLf = preg_match("/(?<!\r)\n/", $back);

printf("new sentence present %d, old claim remaining %d, doubled-CR %d, bare-LF %s\n",
    $new, $old, $doubled, $bareLf ? 'YES' : 'no');

exit($new === 1 && $old === 0 && $doubled === 0 && ! $bareLf ? 0 : 1);
