<?php

/**
 * Lane 56 — measure where the generator's own citations land after this batch moved its lines.
 *
 * Same engine as lane 55's `lane55-measure-shifts.php`: for each cited HEAD range, the one offset over
 * -400..400 that keeps EVERY line byte-identical, and NONE or AMBIGUOUS printed as such instead of guessed.
 * Nothing is written by this file; it only reports, and `tools/` is invisible to PHPStan and Pint, so a pointer
 * into it is verified by running this and reading the answer.
 *
 * Usage: php storage/app/scratch/lane56-measure-pipeline.php
 */

$root = dirname(__DIR__, 3);
$head = array_map(fn (string $l): string => rtrim($l, "\r\n"),
    (array) file($root.'/storage/app/scratch/lane56-head-seed_full_demo_pipeline.php'));
$now = array_map(fn (string $l): string => rtrim($l, "\r\n"),
    (array) file($root.'/tools/seed_full_demo_pipeline.php'));

$ranges = [[1374, 1382], [1396, 1399], [1429, 1447], [1706, 1706], [1765, 1765], [1771, 1771], [1828, 1843]];

printf("tools/seed_full_demo_pipeline.php: HEAD %d lines -> working %d lines\n", count($head), count($now));
foreach ($ranges as [$from, $to]) {
    $ok = [];
    for ($s = -400; $s <= 400; $s++) {
        $good = 0;
        for ($i = $from; $i <= $to; $i++) {
            $j = $i - 1 + $s;
            if (isset($now[$j]) && $now[$j] === ($head[$i - 1] ?? '__missing__')) {
                $good++;
            }
        }
        if ($good === $to - $from + 1) {
            $ok[] = $s;
        }
    }
    $verdict = count($ok) === 1
        ? sprintf('shift %+d -> :%d%s', $ok[0], $from + $ok[0], $from === $to ? '' : '-'.($to + $ok[0]))
        : (count($ok) === 0 ? 'NONE — the content moved, re-point by meaning'
            : 'AMBIGUOUS ('.implode(',', array_slice($ok, 0, 8)).'…)');
    printf("  :%-10s %s\n     HEAD opens: %s\n", "{$from}-{$to}", $verdict, mb_substr(trim($head[$from - 1] ?? ''), 0, 74));
}
