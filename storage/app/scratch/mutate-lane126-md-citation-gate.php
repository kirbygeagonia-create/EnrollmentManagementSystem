<?php

/**
 * Proves `repo-file-line-citations.php` reads the three things its dated-record rule depends on.
 *
 * The gate judges the repository's own prose pointers. On 2026-10-31 it gained a marker: a document that carries
 * `Pointer currency (2026-10-31)` has its drifted pointers REPORTED rather than JUDGED, because those two September
 * reviews quote the layout of the day they were read and re-typing the digits would state lines that were never true
 * on their own day (lane 60's rule for a dated row). That is a new exemption, and an exemption is a way for a gate to
 * stop looking — so the rule is proved the way every other scope flag here is proved: run with it, run without it,
 * compare the verdicts.
 *
 * No leg carries a remembered count. The control run's problem and dated figures are read out of its own footer and
 * the strict run is required to equal their sum, so the leg still bites on a tree where every pointer is true.
 *
 * usage: php storage/app/scratch/mutate-lane126-md-citation-gate.php
 *
 * Run this with the suite idle: leg 7 plants a dated document inside `Documentation/`, and two writers to one tree
 * fake verdicts (the no-concurrent-mutating-runs rule).
 */
$root = dirname(__DIR__, 3);
$gate = $root.'/storage/app/scratch/repo-file-line-citations.php';
$scratch = $root.'/storage/app/scratch';
$php = PHP_BINARY;

/** Created by this harness, removed on every exit path — captured BY REFERENCE, or the closure sees an empty list. */
$copies = [];
$plantedSheets = [];
register_shutdown_function(function () use (&$copies, &$plantedSheets, $scratch): void {
    foreach ($copies as $f) {
        if (is_file($f) && str_starts_with($f, $scratch)) {
            @unlink($f);
        }
    }
    foreach ($plantedSheets as $f) {
        if (is_file($f)) {
            @unlink($f);
        }
    }
    $leftover = glob($scratch.'/lane126-*.md') ?: [];
    if ($leftover !== []) {
        fwrite(STDERR, 'CLEANUP FAILED: '.implode(', ', array_map('basename', $leftover))." still in scratch\n");
    }
});

$write = function (string $path, string $body) use (&$copies): void {
    $copies[] = $path;
    $n = file_put_contents($path, $body);
    if ($n !== strlen($body)) {
        fwrite(STDERR, 'PLANT FAILED: wrote '.var_export($n, true).' bytes to '.basename($path)."\n");

        exit(1);
    }
};

$run = function (array $args) use ($php, $gate, $root): array {
    $cmd = escapeshellarg($php).' '.escapeshellarg($gate).' '
        .implode(' ', array_map('escapeshellarg', $args)).' 2>&1';
    $lines = [];
    $rc = 0;
    exec('cd '.escapeshellarg($root).' && '.$cmd, $lines, $rc);

    return [$rc, implode("\n", $lines)];
};

/** One figure out of the tool's own footer, so a leg asserts a relation rather than a remembered pair. */
$fig = function (string $out, string $label): int {
    return preg_match('/(\d+) '.preg_quote($label, '/').'/', $out, $m) ? (int) $m[1] : -1;
};

$flat = fn (string $s): string => preg_replace('/\s+/', ' ', substr($s, -220));

$bad = 0;
$say = function (string $name, bool $ok, string $detail) use (&$bad): void {
    printf("  %-24s %s%s\n", $name, $ok ? 'ok' : 'BAD', $ok ? '' : '  — '.$detail);
    if (! $ok) {
        $bad++;
    }
};

// The fixture cites a BLANK line of a real file, because a blank line is the one shape the gate refuses. The number
// is found at run time, never remembered: these legs once pointed at line 238 of the user-management controller, and
// the day a sort block was inserted above it the planted "defect" became a live line, three legs reported a clean
// tree, and an aged leg looked like a broken instrument.
$plantFile = 'app/Http/Controllers/Admin/UserManagementController.php';
$plantLines = explode("\n", (string) file_get_contents($root.'/'.$plantFile));
$blankAt = 0;
foreach ($plantLines as $i => $line) {
    if ($i > 5 && trim($line) === '') {
        $blankAt = $i + 1;

        break;
    }
}
if ($blankAt === 0) {
    fwrite(STDERR, "REFUSED: {$plantFile} holds no blank line for the fixture to cite, so the plant cannot be built.\n");

    exit(1);
}
$blankPointer = $plantFile.':'.$blankAt;
$fixture = "# Fixture\n\nThe deactivate act is `{$blankPointer}`.\n";

// ── 1. green control over the whole scanned set ─────────────────────────────────────────────────────────────
[$rc, $normal] = $run([]);
$controlProblems = $fig($normal, 'problem(s)');
$controlDated = $fig($normal, 'dated-record pointer(s)');
$say('control-green', $rc === 0 && $controlProblems === 0, "rc={$rc} problems={$controlProblems} ".$flat($normal));

// ── 2. a planted stale pointer accuses when no marker is present ───────────────────────────────────────────
$plain = $scratch.'/lane126-plain.md';
$write($plain, $fixture);
[$rc, $out] = $run(['storage/app/scratch/lane126-plain.md']);
$say('no-marker-accuses', $rc === 1 && str_contains($out, 'EMPTY LINE') && $fig($out, 'problem(s)') === 1
    && $fig($out, 'dated-record pointer(s)') === 0, "rc={$rc} ".$flat($out));

// ── 3. the same bytes plus the marker are REPORTED, not judged ──────────────────────────────────────────────
$marked = $scratch.'/lane126-marked.md';
$write($marked, "# Fixture\n\n**Pointer currency (2026-10-31):** these digits quote that day's layout.\n\n"
    ."The deactivate act is `{$blankPointer}`.\n");
[$rc, $out] = $run(['storage/app/scratch/lane126-marked.md']);
$say('marker-exempts', $rc === 0 && $fig($out, 'problem(s)') === 0 && $fig($out, 'dated-record pointer(s)') === 1
    && str_contains($out, 'DATED'), "rc={$rc} ".$flat($out));

// ── 4. --strict-dated beats the marker ─────────────────────────────────────────────────────────────────────
[$rc, $out] = $run(['--strict-dated', 'storage/app/scratch/lane126-marked.md']);
$say('strict-beats-marker', $rc === 1 && $fig($out, 'problem(s)') === 1 && str_contains($out, 'EMPTY LINE')
    && ! str_contains($out, 'DATED'), "rc={$rc} ".$flat($out));

// ── 5. on the real tree the flag moves the verdict by exactly the dated count ──────────────────────────────
[$rc, $out] = $run(['--strict-dated']);
$strictProblems = $fig($out, 'problem(s)');
$say('flag-changes-the-set', $controlProblems + $controlDated === $strictProblems
    && $fig($out, 'dated-record pointer(s)') === 0
    && ($controlDated === 0 ? $rc === 0 : $rc === 1),
    "control {$controlProblems}+{$controlDated} vs strict {$strictProblems}, rc={$rc}");

// ── 6. a flag the tool does not read is refused by name ────────────────────────────────────────────────────
[$rc, $out] = $run(['--no-such-flag-at-all']);
$say('unknown-flag-refused', $rc === 1 && str_contains($out, 'REFUSED') && str_contains($out, '--no-such-flag-at-all'),
    "rc={$rc} ".$flat($out));

// ── 7. a dated document the list does not name is a loud refusal, and removing it returns the verdict ──────
$plantedSheet = $root.'/Documentation/School-Decision-Sheet-2999-01-01.md';
$plantedSheets[] = $plantedSheet;
if (file_put_contents($plantedSheet, "# Fixture sheet\n\nSee `{$blankPointer}`.\n") === false) {
    fwrite(STDERR, "PLANT FAILED: cannot write a sheet into Documentation/\n");
    $bad++;
    printf("\n7 leg(s), %d bad\n", $bad);

    exit(1);
}
[$rc, $out] = $run([]);
$refused = $rc === 1 && str_contains($out, 'REFUSED') && str_contains($out, 'School-Decision-Sheet-2999-01-01.md');
@unlink($plantedSheet);
[$rcAfter, $outAfter] = $run([]);
$say('unlisted-doc-loud', $refused && $rcAfter === 0, "planted rc={$rc} removed rc={$rcAfter} ".$flat($out));

printf("\n7 leg(s), %d bad\n", $bad);

exit($bad === 0 ? 0 : 1);
