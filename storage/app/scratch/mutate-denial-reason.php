<?php

/**
 * Mutate the refusal-reason path and require that the right test dies, one mutant per process.
 *
 *   A  the 403 page never prints a reason            → "a refusal that named the reason prints it"
 *   B  the page prints Laravel's placeholder too     → "…prints no framework placeholder"
 *   C  the page prints the reason unescaped          → "a reason is escaped not injected"
 *   D  bootstrap stops answering a JSON refusal      → "a json caller…gets the reason as a message"
 *   E  bootstrap answers JSON for every 403          → "…reasonless denial is left exactly as it was"
 *
 * Each mutant is restored byte-for-byte and the view cache is cleared on both sides, because a cached
 * compiled blade would let mutant C pass or fail on the previous run's HTML rather than this one's.
 *
 * Usage: php mutate-denial-reason.php A|B|C|D|E     (exit 0 = mutant killed by the expected test)
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$blade = $root.'/resources/views/errors/403.blade.php';
$boot = $root.'/bootstrap/app.php';
$test = 'tests/Feature/Errors/AccessDeniedPageTest.php';

$cases = [
    'A' => [
        'file' => $blade, 'what' => 'the page never prints a reason',
        'expect' => 'a refusal that named the reason prints it',
        'find' => '    $reason = \App\Support\DenialReason::of($exception ?? null);',
        'fix' => '    $reason = null;',
    ],
    'B' => [
        'file' => $blade, 'what' => 'the placeholder filter dropped',
        'expect' => 'prints no framework placeholder',
        'find' => '    $reason = \App\Support\DenialReason::of($exception ?? null);',
        'fix' => "    \$reason = trim((string) (\$exception?->getMessage() ?? '')) ?: null;",
    ],
    'C' => [
        'file' => $blade, 'what' => 'the reason printed unescaped',
        'expect' => 'a reason is escaped not injected',
        'find' => '        <p class="reason">{{ $reason }}</p>',
        'fix' => '        <p class="reason">{!! $reason !!}</p>',
    ],
    'D' => [
        'file' => $boot, 'what' => 'bootstrap stopped answering a JSON refusal',
        'expect' => 'gets the reason as a message',
        'find' => "            return \$reason === null ? null : response()->json(['message' => \$reason], 403);",
        'fix' => "            return null;",
    ],
    'E' => [
        'file' => $boot, 'what' => 'every 403 answered JSON, reason or not',
        'expect' => 'is left exactly as it was',
        'find' => "            \$reason = \$e->getStatusCode() === 403 && \$request->expectsJson()\n                ? DenialReason::of(\$e)\n                : null;",
        'fix' => "            \$reason = \$e->getStatusCode() === 403 && \$request->expectsJson()\n                ? (trim(\$e->getMessage()) === '' ? null : \$e->getMessage())\n                : null;",
    ],
];

$id = $argv[1] ?? '';
if (! isset($cases[$id])) {
    fwrite(STDERR, "usage: php ".basename(__FILE__)." A|B|C|D|E\n");
    exit(2);
}

$c = $cases[$id];
$baseline = file_get_contents($c['file']);
$hits = substr_count($baseline, $c['find']);
if ($hits !== 1) {
    printf("mutant %s  %-40s NOT USABLE: anchor occurs %d time(s)\n", $id, $c['what'], $hits);
    exit(1);
}

$clear = fn () => exec(sprintf('cd "%s" && "%s" artisan view:clear 2>&1', $root, $php));
$clear();

$out = [];
$code = 0;
try {
    file_put_contents($c['file'], str_replace($c['find'], $c['fix'], $baseline));
    clearstatcache(true, $c['file']);
    $clear();

    exec(sprintf('cd "%s" && "%s" artisan test %s 2>&1', $root, $php, $test), $out, $code);
} finally {
    file_put_contents($c['file'], $baseline);
    clearstatcache(true, $c['file']);
    $clear();
}

$round = file_get_contents($c['file']);
$body = implode("\n", $out);
$died = str_contains($body, '⨯') === false ? false : (bool) preg_match('/⨯[^\n]*'.preg_quote($c['expect'], '/').'/', $body);

if ($round !== $baseline) {
    print("mutant $id  ".str_pad($c['what'], 40)." RESTORE BROKE: file differs from baseline\n");
    exit(1);
}

if ($code === 0) {
    printf("mutant %s  %-40s SURVIVED (suite passed) — the tests do not prove this\n", $id, $c['what']);
    exit(1);
}

if (! $died) {
    printf("mutant %s  %-40s FAILED THE WRONG TEST: expected %s\n%s\n", $id, $c['what'], $c['expect'], $body);
    exit(1);
}

$passed = preg_match_all('/✓/', $body);
printf("mutant %s  %-40s killed by %s  (%d other tests still passed, file restored)\n", $id, $c['what'], $c['expect'], $passed);
exit(0);
