<?php

/**
 * Proves sweep-docx-citations.php's bare-continuation half can fail.
 *
 * The named half was proved long ago by planting a citation past the end of a file. The half added on
 * 2026-10-24 — `File.php:380 and :404`, where the second citation names no file — had never been proved,
 * and an unproved checker is worse than none, because its silence reads as a pass. Each case plants one
 * shape in a COPY of the flat dump and requires the sweep to name it; the control copy must pass, which is
 * what proves --dump= is read rather than accepted.
 *
 *   php storage/app/scratch/mutate-lane69-bare-cites.php
 */
$root = dirname(__DIR__, 3);
$real = $root.'/storage/app/scratch/docx-flat.txt';
$copy = $root.'/storage/app/scratch/lane69-bare-mutant-dump.txt';
$sweep = $root.'/storage/app/scratch/sweep-docx-citations.php';
$php = PHP_BINARY;

$base = (string) file_get_contents($real);
if (strlen($base) < 1000) {
    echo "REFUSED: the real dump is missing or short — re-run dump-docx-flat.php first.\n";
    exit(1);
}

$run = function (string $path) use ($root, $sweep, $php): array {
    $lines = [];
    $exit = 0;
    exec('cd '.escapeshellarg($root).' && '.escapeshellarg($php).' '.escapeshellarg($sweep)
        .' --dump='.escapeshellarg($path).' 2>&1', $lines, $exit);

    return [implode("\n", $lines), $exit];
};

$fails = 0;
$say = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'BAD ', $what);
    if (! $ok) {
        $fails++;
    }
};

try {
    // Baseline, so a case that changes a count is compared against this run rather than a remembered number.
    [$out0, $exit0] = $run($real);
    $blind0 = preg_match('/(\d+) blind spot\(s\)/', $out0, $b) === 1 ? (int) $b[1] : -1;
    $say($exit0 === 0 && $blind0 > 0, "the unmutated document is clean and reports {$blind0} blind spot(s)");

    $cases = [
        'blank-line' => [
            'and :408 , BlockingController',
            'and :404 , BlockingController',
            'EMPTY LINE',
            'the drift this pass just fixed must be refused again, not re-cleared',
        ],
        'past-end' => [
            'downloadSlip() serves the PDF ( :408 )',
            'downloadSlip() serves the PDF ( :99999 )',
            'PAST END',
            'a bare number beyond the end of the file named before it must be refused',
        ],
    ];

    foreach ($cases as $name => [$from, $to, $needle, $why]) {
        $n = substr_count($base, $from);
        if ($n !== 1) {
            printf("  BAD  %-14s needle matched %d times — the mutant cannot apply\n", $name, $n);
            $fails++;

            continue;
        }
        $mutated = str_replace($from, $to, $base);
        if ($mutated === $base) {
            printf("  BAD  %-14s mutation changed nothing\n", $name);
            $fails++;

            continue;
        }
        file_put_contents($copy, $mutated);
        [$out, $exit] = $run($copy);
        $say($exit === 1 && str_contains($out, $needle),
            $name.' refused with '.$needle.' — '.$why);
        if (! str_contains($out, $needle)) {
            echo '      the run said: '.str_replace("\n", "\n      ", substr($out, 0, 300))."\n";
        }
    }

    // The 160-character window: pushed past it, the token must become a printed blind spot rather than a
    // judged citation — inventing a file for it would let the checker refuse or clear a pointer it cannot bind.
    $filler = str_repeat('x', 200);
    $from = 'downloadBlockSchedule() serves the PDF ( :616 )';
    // The lead character still has to be one the sweep accepts (a comma here); the point of the case is the
    // DISTANCE, so the mutation must not also change the token's shape out from under it.
    $to = 'downloadBlockSchedule() serves the PDF ('.$filler.', :616 )';
    if (substr_count($base, $from) === 1) {
        file_put_contents($copy, str_replace($from, $to, $base));
        [$out, $exit] = $run($copy);
        $grew = preg_match('/(\d+) blind spot\(s\)/', $out, $b) === 1 ? (int) $b[1] > $blind0 : false;
        $say($grew && str_contains($out, ':616'), 'past 160 characters the token is printed as unattributed, not judged');
        $say($exit === 0, 'and an unjudged token is a blind spot, not a refusal — the sweep says so instead of guessing');
    } else {
        printf("  BAD  window needle matched %d times — the case cannot apply\n", substr_count($base, $from));
        $fails++;
    }

    // Control: a byte-identical copy passes, which is the proof the flag is honoured.
    file_put_contents($copy, $base);
    [$out, $exit] = $run($copy);
    $say($exit === 0 && str_contains($out, '0 line problem(s)'), 'a clean copy of the dump passes with --dump= honoured');

    // Refusal: a missing dump must not answer with the tidy verdict of a measured document.
    [$out, $exit] = $run($copy.'.nope');
    $say($exit === 1 && str_contains($out, 'REFUSED'), 'a dump that is not there refuses instead of reporting 0 problems');
} finally {
    if (is_file($copy)) {
        unlink($copy);
        echo "\nmutant copy removed; the real dump was never written to.\n";
    }
}

printf("%d case(s) did not behave.\n", $fails);
exit($fails > 0 ? 1 : 0);
