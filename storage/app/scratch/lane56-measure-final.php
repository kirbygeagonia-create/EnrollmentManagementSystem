<?php

/**
 * Lane 56 — measure the pointers this batch moved, in both files it edited, plus the generator's own.
 *
 * Reports the one byte-identical shift per cited HEAD range (searched across -400..400), and for a range whose
 * interior this batch rewrote it prints the span measured off the current file instead. Read-only; pass 162 binds
 * the document to these numbers and refuses anything that does not resolve to exactly one shift.
 *
 * Usage: php storage/app/scratch/lane56-measure-final.php
 */

$root = dirname(__DIR__, 3);

$jobs = [
    'app/Console/Commands/AuditRecordStates.php' => [
        'head' => 'storage/app/scratch/lane56-head-AuditRecordStates.php',
        'ranges' => [[46, 177], [289, 311], [319, 324], [334, 343]],
        'spans' => ['handle', 'clinicBoxWithoutRecord', 'idBoxWithoutValidatedRequest',
            'sheetWithoutLines', 'receiptWithoutPaidStep', 'collidingSchedules', 'receiptPaidWhileOwing'],
    ],
    'tools/seed_full_demo_pipeline.php' => [
        'head' => 'storage/app/scratch/lane56-head-seed_full_demo_pipeline.php',
        'ranges' => [[1429, 1447], [1374, 1382], [1396, 1399], [1828, 1843]],
        'spans' => [],
    ],
];

$lines = fn (string $p): array => array_map(fn (string $l): string => rtrim($l, "\r\n"), (array) file($p));

foreach ($jobs as $rel => $job) {
    $now = $lines($root.'/'.$rel);
    $head = $lines($root.'/'.$job['head']);
    echo "== {$rel} (HEAD ".count($head).' -> working '.count($now).' lines) =='."\n";
    foreach ($job['ranges'] as [$from, $to]) {
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
        printf("  HEAD :%-10s %s\n", "{$from}-{$to}", count($ok) === 1
            ? sprintf('-> :%d-%d (%+d)', $from + $ok[0], $to + $ok[0], $ok[0])
            : (count($ok) === 0 ? 'NONE — interior rewritten, take the measured span' : 'AMBIGUOUS'));
    }
    foreach ($job['spans'] as $name) {
        foreach ($now as $i => $line) {
            if (preg_match('/^\s*(?:public|private)?\s*(?:static\s+)?function\s+'.preg_quote($name, '/').'\(/', $line) === 1) {
                $depth = 0;
                $opened = false;
                for ($j = $i; $j < count($now); $j++) {
                    $depth += substr_count($now[$j], '{') - substr_count($now[$j], '}');
                    if ($depth > 0) {
                        $opened = true;
                    }
                    if ($opened && $depth === 0) {
                        printf("  span  %-30s :%d-%d\n", $name.'()', $i + 1, $j + 1);
                        break;
                    }
                }
                break;
            }
        }
    }
    echo "\n";
}
