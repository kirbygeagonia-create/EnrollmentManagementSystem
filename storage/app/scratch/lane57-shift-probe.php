<?php

/**
 * Lane 57 — which document pointers this batch moved, and whether a single shift answers for them.
 *
 * The bounds sweep only refuses a pointer that lands past the end of a file or on a blank line, so a pointer one row
 * short of its construct passes it while telling the reader the wrong thing. This compares HEAD's copy of a file with
 * the working copy and, for each cited range, accepts only a shift under which *every* cited line is byte-identical.
 * A range whose lines do not all move together is inside the edited region and must be re-read, not re-added.
 *
 *   php storage/app/scratch/lane57-shift-probe.php
 */

$root = dirname(__DIR__, 3);
$flat = (string) file_get_contents($root.'/storage/app/scratch/docx-flat.txt');

$targets = [
    'AccountingController.php' => 'app/Http/Controllers/Accounting/AccountingController.php',
    'AuditRecordStates.php' => 'app/Console/Commands/AuditRecordStates.php',
];

foreach ($targets as $basename => $path) {
    $head = array_map(fn (string $l): string => rtrim($l, "\r\n"),
        (array) @file($root.'/storage/app/scratch/lane57-head-'.$basename));
    if ($head === []) {
        echo "== {$basename}: NO HEAD export, refusing to guess\n";
        continue;
    }
    $now = array_map(fn (string $l): string => rtrim($l, "\r\n"), (array) file($root.'/'.$path));
    printf("== %s  HEAD %d lines -> working %d lines (%+d)\n", $basename, count($head), count($now), count($now) - count($head));

    preg_match_all('/'.$basename.':(\d+)(?:-(\d+))?/', $flat, $m, PREG_SET_ORDER);
    $seen = [];
    foreach ($m as $c) {
        $key = $c[1].'-'.($c[2] ?? '');
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $from = (int) $c[1];
        $to = isset($c[2]) && $c[2] !== '' ? (int) $c[2] : $from;

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

        if (count($ok) === 1 && $ok[0] !== 0) {
            printf("  :%-6s moves to :%s (%+d) — every cited line byte-identical under that shift\n",
                $from.'-'.($to === $from ? '' : $to), ($from + $ok[0]).'-'.($to + $ok[0]), $ok[0]);
        } elseif (count($ok) === 1 && $ok[0] === 0) {
            printf("  :%-6s unchanged\n", $from.'-'.($to === $from ? '' : $to));
        } else {
            printf("  :%-6s NOT RESOLVED by one shift (%d candidate(s)) — re-read this pointer against the construct\n",
                $from.'-'.($to === $from ? '' : $to), count($ok));
        }
    }
}
