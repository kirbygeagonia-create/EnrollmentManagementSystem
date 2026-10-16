<?php
/**
 * Record the time-zone finding in DEPLOYMENT.md §2 and make the smoke test check it.
 *
 * Two facts, both measured rather than reasoned about: the app carries app.timezone UTC while the school
 * keeps Manila time, and PHP's stamp and MySQL's stamp of one instant differ by eleven hours on the
 * development box because the database session is in another zone. The second matters only because two
 * DATETIME columns carry a MySQL default and a DATETIME stores no zone. Every string here is built with a
 * plain "\n" and converted to CRLF exactly once at the end — an earlier version of this script joined lines
 * with PHP_EOL (already "\r\n" on Windows) and then replaced "\n" with "\r\n", producing "\r\r\n" breaks that
 * its own CRLF counters could not see.
 */
$path = 'DEPLOYMENT.md';
$crlf = "\r\n";
$bytes = file_get_contents($path);

if (! str_contains($bytes, $crlf)) {
    fwrite(STDERR, "ABORT: DEPLOYMENT.md is not CRLF as expected\n");
    exit(1);
}

$anchor2 = "Force HTTPS in the entry point (reverse proxy `X-Forwarded-Proto`, or\n"
    ."`URL::forceScheme('https')` in `AppServiceProvider::boot` when\n"
    .'`app()->isProduction()`).';

$addition = "\n\n"
    ."**Time zone: two processes write into these tables.** `config/app.php` carries `timezone => 'UTC'` while\n"
    ."the school keeps Manila time (UTC+8), so a record made before 08:00 local is stored with the previous day\n"
    ."and every printed timestamp runs eight hours behind the wall clock in the room. Whether to set\n"
    .'`APP_TIMEZONE=Asia/Manila` is a decision to take deliberately, not one to inherit. The second half is not\n'
    ."a decision: measured on the development box on 2026-10-12, PHP stamped `2026-10-13 05:32` while MySQL's\n"
    ."`NOW()` answered `2026-10-12 18:32` for the same instant. The two epochs agree to the second — the clocks\n"
    ."are fine — but the database session runs in another zone, and `auditlogs.createdAt` and\n"
    ."enrollmentstatushistory.changedAt` carry a MySQL `CURRENT_TIMESTAMP` default into plain DATETIME columns\n"
    ."that store no zone. Whichever process stamps the row decides the hours a desk reads.\n"
    ."`php artisan ems:preflight` now fails the run on that disagreement and names both strings; fix it by\n"
    ."setting the database session time zone to the app's, or the app's zone to the room's — leaving both as\n"
    .'they are is the one option that keeps the split.';

$smoke_anchor = '1. `/up` returns 200 (health route).';
$smoke_new = "1. `php artisan ems:preflight` exits 0 — including `Clocks agree`, the check that a server does\n"
    ."not stamp the same moment two different ways.\n"
    .'2. `/up` returns 200 (health route).';

$fails = 0;

foreach ([['§2 time-zone paragraph', $anchor2, $anchor2.$addition, 1],
    ['§8 smoke test runs preflight first', $smoke_anchor, $smoke_new, 1]] as [$label, $from, $to, $expect]) {
    $fromX = str_replace("\n", $crlf, $from);
    $toX = str_replace("\n", $crlf, $to);
    $hits = substr_count($bytes, $fromX);
    if ($hits !== $expect) {
        fwrite(STDERR, "REFUSE: {$label} — anchor matched {$hits} times, expected {$expect}\n");
        $fails++;

        continue;
    }
    $bytes = str_replace($fromX, $toX, $bytes);
    echo "edit ok  {$label}\n";
}

$check = str_replace($crlf, "\n", $bytes);
foreach ([
    'the paragraph is in once' => substr_count($check, 'Time zone: two processes write into these tables') === 1,
    'the measured strings are quoted' => str_contains($check, '2026-10-13 05:32')
        && str_contains($check, '2026-10-12 18:32'),
    'the smoke test renumbered, not duplicated' => substr_count($check, 'returns 200 (health route)') === 1,
    'the preflight check is named in the smoke test' => str_contains($check, 'including `Clocks agree`'),
    'no doubled CR' => substr_count($bytes, "\r\r\n") === 0,
    'no bare LF introduced' => substr_count($bytes, "\n") === substr_count($bytes, $crlf),
] as $name => $ok) {
    if (! $ok) {
        fwrite(STDERR, "REFUSE guard: {$name}\n");
        $fails++;
    } else {
        printf("guard ok %s\n", $name);
    }
}

echo $fails === 0 ? "\nall edits and guards ok\n" : "\n{$fails} problem(s)\n";
if ($fails > 0 || ! in_array('--apply', $argv, true)) {
    exit($fails === 0 ? 0 : 1);
}

file_put_contents($path, $bytes);
echo "WROTE {$path}\n";
