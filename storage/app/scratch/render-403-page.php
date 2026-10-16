<?php

/**
 * Boot the application, refuse a request the way ExamController does, and print the bytes a desk receives.
 *
 * AccessDeniedPageTest asserts the reason is present. It cannot show what a browser is handed: whether the
 * doctype still leads, whether the reason lands inside the amber box rather than loose in the card, and
 * whether the blade's @php block emits a blank line before it. Those are the parts of "the page renders"
 * that no assertion in that file reads, so they are read here.
 *
 * Read-only. Writes nothing.
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';

$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$route = Illuminate\Support\Facades\Route::middleware('web')->get('__render/403-with-reason', function () {
    abort_unless(false, 403, 'Program “BTS” belongs to another college; a department records its own.');
});

$request = Illuminate\Http\Request::create('/__render/403-with-reason', 'GET');
$response = $app->make(Illuminate\Contracts\Http\Kernel::class)->handle($request);

$body = $response->getContent();

printf("status     : %d\n", $response->getStatusCode());
printf("bytes      : %d\n", strlen($body));
printf("first 40   : %s\n", str_replace("\n", '\n', substr($body, 0, 40)));
printf("doctype leads: %s\n", str_starts_with(ltrim($body), '<!DOCTYPE html>') ? 'yes' : 'NO — the browser quirks-mode');

$reason = strpos($body, 'belongs to another college');
printf("\nas received, the card:\n%s\n", htmlspecialchars(
    substr($body, (int) strpos($body, '<div class="card">'), (int) strpos($body, '</div>', $reason) + 20 - strpos($body, '<div class="card">')),
    ENT_NOQUOTES, 'UTF-8'));

preg_match('/class="reason"[^>]*>(.*?)</s', $body, $m);
printf("inside .reason: %s\n", $m[1] ?? '(NOTHING — the reason did not reach the box)');
