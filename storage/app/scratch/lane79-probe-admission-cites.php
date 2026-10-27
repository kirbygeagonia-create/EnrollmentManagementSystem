<?php

/**
 * Every citation the authority docx holds into `AdmissionController.php`, with the line each one lands on
 * TODAY and the sentence that offered it.
 *
 * Pass 258 re-pointed these against the file as it stood that day; the resume edge built afterwards moved
 * the file again. `lane76-shift-cites.php` cannot settle it, because its reference is HEAD and the docx is
 * keyed to the working tree — seven of the twenty-two lines in `git diff --stat` predate this edit — so a
 * uniform shift would have written twelve live-looking-but-wrong pointers. This probe prints the reading
 * instead: for each cited range the caller can see which construct it now lands on and decide by meaning.
 *
 * Usage: php storage/app/scratch/lane79-probe-admission-cites.php
 */

$root = dirname(__DIR__, 3);
$dump = (string) file_get_contents($root.'/storage/app/scratch/docx-flat.txt');
$file = $root.'/app/Http/Controllers/Admission/AdmissionController.php';
$lines = explode("\n", (string) file_get_contents($file));
$count = count($lines);

preg_match_all('/AdmissionController\.php:(\d+)(?:-(\d+))?/', $dump, $all, PREG_SET_ORDER);

$cited = [];

foreach ($all as $m) {
    $from = (int) $m[1];
    $to = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : $from;
    $cited[$from.'-'.$to] = [$from, $to];
}

ksort($cited);

// Bare continuations (`the seat guard at :530`) belong to the file named most recently before them, so they
// are collected the same way the sweep collects them: on the same dump line, within 160 characters.
preg_match_all('/(?<![\w.\/])(?::(\d{2,4}))(?![\d-])/', $dump, $bare, PREG_SET_ORDER);

printf("%d line citations over %d distinct range(s); the file holds %d lines\n\n",
    count($all), count($cited), $count
);

foreach ($cited as $key => [$from, $to]) {
    $blank = [];

    foreach (range($from, min($to, $count)) as $n) {
        if (trim($lines[$n - 1] ?? '') === '') {
            $blank[] = $n;
        }
    }

    printf("%-12s %s\n", $key, $blank === [] ? '' : 'BLANK AT '.implode(',', $blank));

    foreach ([max(1, $from), min($to, $count)] as $n) {
        printf("   %4d| %s\n", $n, trim(substr(trim($lines[$n - 1] ?? ''), 0, 104)));
    }

    echo "\n";
}

$needle = '/.{0,120}AdmissionController\.php:\d+(?:-\d+)?.{0,120}/';

if (preg_match_all($needle, $dump, $sentences) > 0) {
    echo "the sentences that carry them, in dump order:\n\n";

    foreach (array_unique($sentences[0]) as $n => $s) {
        printf("  %d| %s\n\n", $n + 1, trim(preg_replace('/\s+/', ' ', $s)));
    }
}

echo "bare continuations near an AdmissionController citation:\n";

foreach ($bare as $m) {
    $at = (int) $m[1];

    if ($at < 100 || $at > $count) {
        continue;
    }

    printf("   :%-5d lands on  %s\n", $at, trim(substr(trim($lines[$at - 1] ?? ''), 0, 92)));
}
