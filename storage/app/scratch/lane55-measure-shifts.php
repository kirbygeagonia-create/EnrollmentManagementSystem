<?php

/**
 * Lane 55 — measure where every docx pointer into the two files this batch edited now lands.
 *
 * The batch changed detectConflicts() (BlockingController) and appended two rules (AuditRecordStates), so every
 * `File.php:NN` citation into those two files that points at a line after the first hunk is now short. This prints,
 * for each cited HEAD range, the one shift over -400..400 that keeps EVERY line byte-identical — and says AMBIGUOUS
 * or NONE rather than guessing, because a shortlist of candidate offsets is how a first attempt reported "NONE" for
 * a pointer that had merely moved by a number nobody tried.
 *
 * Read-only. Usage: php storage/app/scratch/lane55-measure-shifts.php
 */

$root = dirname(__DIR__, 3);
$pairs = [
    'BlockingController' => [
        'head' => $root.'/storage/app/scratch/lane55-head-BlockingController.php',
        'now' => $root.'/app/Http/Controllers/Blocking/BlockingController.php',
        'ranges' => [[264, 271], [385, 387], [433, 433], [457, 538], [461, 461], [467, 478], [469, 476],
            [475, 475], [573, 573], [573, 601], [586, 586], [588, 588], [594, 594]],
    ],
    'AuditRecordStates' => [
        'head' => $root.'/storage/app/scratch/lane55-head-AuditRecordStates.php',
        'now' => $root.'/app/Console/Commands/AuditRecordStates.php',
        'ranges' => [[46, 167], [279, 301], [309, 314], [324, 333]],
    ],
];

$lines = fn (string $p): array => array_map(fn (string $l): string => rtrim($l, "\r\n"), (array) file($p));

$shiftOf = function (array $head, array $now, int $from, int $to): array {
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

    return $ok;
};

foreach ($pairs as $name => $p) {
    $head = $lines($p['head']);
    $now = $lines($p['now']);
    printf("%s: HEAD %d lines -> working %d lines\n", $name, count($head), count($now));
    foreach ($p['ranges'] as [$from, $to]) {
        $ok = $shiftOf($head, $now, $from, $to);
        $anchor = trim($head[$from - 1] ?? '');
        $verdict = count($ok) === 1 ? sprintf('shift %+d -> :%d-%d', $ok[0], $from + $ok[0], $to + $ok[0])
            : (count($ok) === 0 ? 'NONE — the line content moved, re-point by meaning'
                : 'AMBIGUOUS ('.implode(',', $ok).')');
        printf("  :%-9s %s\n     HEAD opens: %s\n", "{$from}-{$to}", $verdict, mb_substr($anchor, 0, 72));
        if (count($ok) === 0) {
            printf("     working tree has: %s\n", mb_substr(trim($now[$from - 1] ?? '<past end>'), 0, 72));
        }
    }
    echo "\n";
}
