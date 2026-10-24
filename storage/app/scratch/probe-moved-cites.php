<?php

/**
 * List every citation into the two files pass 184's build moved, with the line each one currently lands on,
 * so a pointer that resolves onto the wrong construct is caught by reading rather than by a range check.
 */
$root = dirname(__DIR__, 3);
$dump = (string) file_get_contents($root.'/storage/app/scratch/docx-flat.txt');

$targets = [
    'clearance-slip.blade.php' => explode("\n", (string) file_get_contents($root.'/resources/views/prints/clearance-slip.blade.php')),
    'PrintSlip.jsx' => explode("\n", (string) file_get_contents($root.'/resources/js/Pages/Clearance/PrintSlip.jsx')),
];

preg_match_all('/(clearance-slip\.blade\.php|PrintSlip\.jsx):(\d+)(?:-(\d+))?/', $dump, $all, PREG_SET_ORDER);
$seen = [];
foreach ($all as $m) {
    $seen[$m[1].':'.$m[2].'-'.($m[3] ?? $m[2])] = [$m[1], (int) $m[2], isset($m[3]) ? (int) $m[3] : (int) $m[2]];
}
ksort($seen);

foreach ($seen as $key => [$file, $from, $to]) {
    $lines = $targets[$file];
    $count = count($lines);
    $blank = [];
    foreach (range($from, min($to, $count)) as $n) {
        if (trim($lines[$n - 1] ?? '') === '') {
            $blank[] = $n;
        }
    }
    printf("\n%-34s file has %d lines%s\n", $key, $count, $blank === [] ? '' : ' | BLANK AT '.implode(',', $blank));
    foreach (range($from, min($to, $count)) as $n) {
        printf("   %4d| %s\n", $n, trim(substr(trim($lines[$n - 1] ?? ''), 0, 96)));
    }
}
