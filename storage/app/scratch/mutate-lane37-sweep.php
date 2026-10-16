<?php

/**
 * Mutation-proof for lane37-section-reference-sweep.php.
 *
 * A sweep that reports 0 problems over 838 references has either checked them or cannot fail, and the two look
 * identical from here. So each verdict the sweep can give is provoked once, in a scratch copy of the document —
 * the real file is never opened for writing — and the unmutated copy has to stay clean in the same run.
 *
 *   A  wrong-chapter   a citation retargeted at another register  ("§25 P-14" → "§28 P-14") — the defect this
 *                      sweep exists to catch, because P-14's row lives in §25.4 and §28 has no such row.
 *   B  missing-section a pointer to a section that is not there   ("§12.1" → "§12.91").
 *   C  missing-id      the register row renamed, the citations left alone (only the row's own cell changes:
 *                      `>P-14<` occurs exactly once, while the string appears in prose many times).
 *   D  titled-escape   §19.3 is *titled* "…off by one (N-2)", which is the one acceptance rule that could
 *                      swallow a real defect; retitle it and "§19.3 (N-2)" must become WRONG-LOCATION.
 *
 * usage: php storage/app/scratch/mutate-lane37-sweep.php
 */

$root = dirname(__DIR__, 3);
$php = PHP_BINARY;
$real = $root.'/Documentation/EMS Complete Documentation.docx';
$sweep = str_replace('\\', '/', $root.'/storage/app/scratch/lane37-section-reference-sweep.php');
$scratchDir = $root.'/storage/app/scratch/mut-lane37';

if (! is_dir($scratchDir)) {
    mkdir($scratchDir, 0777, true);
}

$xmlOf = function (string $file): string {
    $z = new ZipArchive();
    $z->open($file);
    $x = $z->getFromName('word/document.xml');
    $z->close();

    return $x;
};

$write = function (string $file, string $xml) use ($real): void {
    copy($real, $file);
    $z = new ZipArchive();
    if ($z->open($file, ZipArchive::CREATE) !== true) {
        fwrite(STDERR, "cannot open {$file} for updating\n");
        exit(1);
    }
    $z->addFromString('word/document.xml', $xml);
    $z->close();
    $check = new ZipArchive();
    $check->open($file);
    $parts = count($check);
    if ($check->getFromName('word/document.xml') !== $xml || $parts !== 14) {
        fwrite(STDERR, "the mutant copy lost parts ({$parts}) — the harness itself is broken\n");
        exit(1);
    }
    $check->close();
};

$runSweep = function (string $file) use ($php, $sweep): array {
    $out = shell_exec(escapeshellarg(str_replace('/', '\\', $php)).' '.escapeshellarg($sweep).' '.escapeshellarg($file).' 2>&1');
    preg_match('/NO-SECTION (\d+), NO-ID (\d+), WRONG-LOCATION (\d+)/', (string) $out, $m);
    if (! isset($m[1])) {
        return [-1, -1, -1, (string) $out];
    }

    return [(int) $m[1], (int) $m[2], (int) $m[3], (string) $out];
};

$fails = 0;
$verdict = function (string $name, bool $ok, string $detail) use (&$fails): void {
    printf("  %-9s %-20s %s\n", $ok ? 'PASS' : 'SURVIVED', $name, $detail);
    if (! $ok) {
        $fails++;
    }
};

$base = $xmlOf($real);
echo "=== lane 37 sweep mutation-proof (the real document is only ever read) ===\n";

// control — the shipped document, copied exactly, must stay clean
$control = $scratchDir.'/control.docx';
$write($control, $base);
[$noSec, $noId, $wrong, $out] = $runSweep($control);
// Read the verdicts, not the word "checked": the sweep prints that word twice (its header and its summary),
// so an assertion on how often it appears is a test of the wording, not of the document.
$verdict('control-clean', $noSec === 0 && $noId === 0 && $wrong === 0,
    'NO-SECTION '.$noSec.', NO-ID '.$noId.', WRONG-LOCATION '.$wrong);

$cases = [
    'A wrong-chapter' => ['§25 P-14', '§28 P-14', 1, 'wrong', 'the cited chapter no longer holds the row'],
    'B missing-section' => ['§12.1', '§12.91', 1, 'noSec', 'the pointer names a section that is not there'],
    'C missing-id' => ['>P-14<', '>P-14x<', 1, 'noId', 'the register row was renamed under its citations'],
    // The heading alone, at its full length, so the citation keeps naming the id it cites — replacing the
    // shorter "(N-2)" changed the prose too and produced NO-ID, which tests a different rule entirely.
    'D titled-escape' => ['Workflow step labels are off by one (N-2)', 'Workflow step labels are off by one (N-9)', 1, 'wrong',
        'the heading no longer carries the finding the citation leans on'],
];

foreach ($cases as $name => [$from, $to, $expectAtLeast, $which, $why]) {
    $n = substr_count($base, $from);
    if ($n < $expectAtLeast) {
        $verdict($name, false, "needle [{$from}] occurs {$n} time(s) in document.xml — the mutant cannot be built");
        $fails++;

        continue;
    }
    $file = $scratchDir.'/'.strtolower(str_replace(' ', '-', $name)).'.docx';
    $write($file, str_replace($from, $to, $base));
    [$ns, $ni, $wl] = $runSweep($file);
    $counts = ['noSec' => $ns, 'noId' => $ni, 'wrong' => $wl];
    $fired = $counts[$which] > 0;
    // The mutant must be caught by the verdict it targets and not merely by *some* noise.
    $verdict($name, $fired, "{$n} needle(s) replaced; NO-SECTION {$ns}, NO-ID {$ni}, WRONG-LOCATION {$wl} — ".$why);
}

printf("\n%s\n", $fails === 0
    ? 'every rule can fail, and the unmutated document is clean under the same run'
    : "{$fails} mutant(s) SURVIVED — the sweep's silence proves nothing until each is explained");
exit($fails === 0 ? 0 : 1);
