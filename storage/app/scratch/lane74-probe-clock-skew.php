<?php

/**
 * Lane 74 — which clock stamped this date?
 *
 * Every dated claim in this repository ("measured 2026-10-26", the packet's own filename, `SchoolTime::today()`,
 * the `DEMO-OR-TODAY-01` slip) comes from ONE machine's clock, and no instrument here had ever compared that clock
 * to anything outside the box. `lane72-probe-school-day.php` asks whether the dataset carries the school's current
 * day — both sides of that question are computed from the same clock, so it can agree with itself and be wrong about
 * the world. This is the missing outside.
 *
 * Measured on this box: PHP's `gmdate` said `2026-10-26 07:23`, MySQL's `UTC_TIMESTAMP()` said `2026-10-26 07:23:39`
 * and the application's `now()` agreed, so the agreement is the Windows clock, not one program. The same minute,
 * GitHub's run record for the push that had just completed read `created_at = 2026-10-25T12:12:46Z` and the API's own
 * `date:` header read `Sun, 25 Oct 2026 12:26:48 GMT`. The box is about nineteen hours ahead, which makes the real
 * civil day in Manila the day BEFORE the one this project has been writing into its documents.
 *
 * It is a hand instrument, not a gate: a CI runner has its own clock, and this answers about the machine that ran
 * it. It prints a measurement and exits 0. It exits 1 only when it reached nothing — a probe that could not ask the
 * outside world must say so rather than report that the clocks agree, which is this repository's oldest failure
 * (an unread surface dressed up as a clean verdict).
 *
 * Its own first run refused for the wrong reason and that is recorded here because it is the trap: `curl` with no
 * `User-Agent` gets **403** from the GitHub API, and the 403 page carries no `date:` header at all — so "the host
 * sent no Date header" was a true sentence about a request that had failed, printed as if it were an answer about
 * time. The probe now sends a User-Agent and refuses on any status of 400 or above, naming it.
 *
 *   php storage/app/scratch/lane74-probe-clock-skew.php
 *   php storage/app/scratch/lane74-probe-clock-skew.php --host=www.google.com --path=/
 */
$root = dirname(__DIR__, 3);

$opt = static function (string $name, string $default): string {
    foreach ($GLOBALS['argv'] as $a) {
        if (str_starts_with($a, "--$name=")) {
            return substr($a, strlen("--$name="));
        }
    }

    return $default;
};
$bare = array_filter($GLOBALS['argv'], fn ($a) => is_string($a) && str_starts_with($a, '--') && ! str_contains($a, '='));
if ($bare !== []) {
    fwrite(STDERR, sprintf("REFUSED: %s is not a flag this tool reads. It measures one box's clock; a runner's clock "
        ."is a different answer about a different machine.\n", implode(' ', $bare)));

    exit(1);
}

$host = $opt('host', 'api.github.com');
$path = $opt('path', '/meta');
// CURLOPT_TIMEOUT casts a float to whole seconds, so `--timeout=0.05` became 0 = no timeout at all and the probe
// answered a measurement for a request that had outlasted its own limit. Ask in milliseconds.
$timeoutMs = max(50, (int) round((float) $opt('timeout', '10') * 1000));

// The school's zone is read the way every other probe here reads .env — line by line. parse_ini_file() returns
// nothing on this file and the probe would then silently fall back to its own default.
$schoolZone = 'Asia/Manila';
$zoneSource = 'config/school.php default';
$envFile = $root.'/.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (preg_match('/^\s*SCHOOL_TIMEZONE\s*=\s*(.+)$/u', $line, $m) === 1) {
            $schoolZone = trim($m[1], " \"'");
            $zoneSource = '.env SCHOOL_TIMEZONE';
            break;
        }
    }
}

$before = time();
$curl = curl_init("https://$host$path");
curl_setopt_array($curl, [
    CURLOPT_NOBODY => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_TIMEOUT_MS => $timeoutMs,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTPHEADER => ['User-Agent: ems-lane74-clock-skew-probe'],
]);
$response = curl_exec($curl);
$errno = curl_errno($curl);
$error = curl_error($curl);
$status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
curl_close($curl);
$after = time();

if ($errno !== 0 || ! is_string($response)) {
    fwrite(STDERR, sprintf('REFUSED: https://%s%s answered nothing (curl error %d: %s). The outside clock was never '
        ."asked, so this run can say nothing about the box's clock.\n", $host, $path, $errno, $error));

    exit(1);
}
if ($status >= 400) {
    fwrite(STDERR, sprintf('REFUSED: https://%s%s returned HTTP %d, so no clock was read from it. An error page is '
        ."not an answer about time.\n", $host, $path, $status));

    exit(1);
}

$external = null;
// Split on "\n", never preg_split('/\R/'): \R also matches the byte 0x85, which appears inside UTF-8 emoji, and a
// line counter that disagrees with the reader's screen is how this repository wrote a wrong citation.
foreach (explode("\n", $response) as $h) {
    if (preg_match('/^date:\s*(.+)$/i', trim($h), $m) === 1) {
        $d = date_create(trim($m[1]));
        if ($d !== false) {
            $external = $d->getTimestamp();
        }
        break;
    }
}
if ($external === null) {
    fwrite(STDERR, sprintf('REFUSED: https://%s returned HTTP %d with no readable date: header, so the external '
        ."clock is unread.\n", $host, $status));

    exit(1);
}

$manila = new DateTimeZone($schoolZone);
$dayAt = static function (int $epoch) use ($manila): string {
    return (new DateTimeImmutable("@$epoch"))->setTimezone($manila)->format('Y-m-d H:i:s');
};

$skew = $before - $external;
$sign = $skew >= 0 ? 'AHEAD of' : 'BEHIND';
printf("host asked:          https://%s%s (HTTP %d)\n", $host, $path, $status);
printf("box clock (UTC):     %s   epoch %d, re-read at %d — the round trip took %d s\n",
    gmdate('Y-m-d H:i:s', $before), $before, $after, $after - $before);
printf("host clock (UTC):    %s   epoch %d\n", gmdate('Y-m-d H:i:s', $external), $external);
printf("skew:                %+d s  =  the box clock is %d h %02d m %s the host\n",
    $skew, intdiv(abs($skew), 3600), intdiv(abs($skew) % 3600, 60), $sign);
printf("school zone %s (%s): this box calls the civil day %s; the host clock calls it %s\n",
    $schoolZone, $zoneSource, $dayAt($before), $dayAt($external));

if (abs($skew) < 120) {
    echo 'VERDICT: the box clock answers the same minute as the host, so a date stamped on this machine is a date '
        ."the world would write too.\n";

    exit(0);
}

echo "VERDICT: the two clocks disagree, so EVERY dated claim in this repository is box-relative, not world-relative.\n"
    ."    SchoolTime::today(), the packet filenames, the authority docx's §1.1 rows and the DEMO-OR-TODAY-01 slip all\n"
    ."    follow the box. Do not re-date them by hand: a batch of rows re-aged to a different calendar is worse than\n"
    ."    a batch that is consistently one day off, and commits already pushed carry these dates. Say which clock you\n"
    ."    mean when you quote a date. Correcting the OS clock is a deliberate act — MySQL, Word and any open session\n"
    ."    on this machine react to a jump of this size.\n";

exit(0);
