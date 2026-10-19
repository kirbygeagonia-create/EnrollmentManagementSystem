<?php

// Lane 51: for every file the batch changed, find the shift under which each cited range still points at the
// same bytes. A citation is moved only when that holds; anything else is reported for a human to read.
// HEAD copies are exported by bash into lane50-head/ with '__' standing for '/'.
//
//   git diff --name-only > storage/app/scratch/lane51-changed-files.txt
//   php storage/app/scratch/lane51-probe-cite-shift2.php

$root = dirname(__DIR__, 3);
$dump = $root.'/storage/app/scratch/docx-flat.txt';
$listFile = $root.'/storage/app/scratch/lane51-changed-files.txt';

if (! is_file($dump) || ! is_file($listFile)) {
    fwrite(STDERR, "REFUSE: missing the flat dump or the changed-file list\n");
    exit(1);
}

// Not PHP_EOL: on this box that is "\r\n", and the carriage returns are stripped above, so splitting on it
// would hand back one giant "filename".
$files = array_values(array_filter(explode(chr(10), str_replace("\r", '', trim(file_get_contents($listFile))))));

// Only files the document can cite by name.
$files = array_values(array_filter($files, fn ($f) => preg_match('/\.(php|jsx)$/', $f) && ! str_starts_with($f, 'storage/app/scratch/')));

if ($files === []) {
    fwrite(STDERR, "NO CONCLUSION: nothing to measure\n");
    exit(1);
}

$text = file_get_contents($dump);
$report = [];
$stuck = 0;

foreach ($files as $rel) {
    $headPath = $root.'/storage/app/scratch/lane50-head/'.str_replace('/', '__', $rel);

    if (! is_file($headPath)) {
        fwrite(STDERR, "REFUSE: no exported HEAD copy for $rel\n");
        exit(1);
    }

    $head = explode(chr(10), str_replace("\r\n", PHP_EOL, file_get_contents($headPath)));
    $work = explode(chr(10), str_replace("\r\n", PHP_EOL, file_get_contents($root.'/'.$rel)));
    // The bare file name collides across pages (Show.jsx is seven files), so the parent directory is part
    // of the needle — same rule lane50-probe-cite-shift.php uses.
    $base = basename(dirname($rel)).'/'.basename($rel);

    preg_match_all('/(?:.*[\/])?'.preg_quote($base, '/').'\s*:\s*(\d+)(?:\s*-\s*(\d+))?/', $text, $cites, PREG_SET_ORDER);

    $seen = [];

    foreach ($cites as $c) {
        $from = (int) $c[1];
        $to = isset($c[2]) && $c[2] !== '' ? (int) $c[2] : $from;
        $key = $rel.':'.$from.'-'.$to;

        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $found = null;

        // Both directions: this batch also deleted lines (a dead local helper), and a citation into that file
        // has moved backwards.
        for ($k = -14; $k <= 14; $k++) {
            $all = true;

            for ($n = $from; $n <= $to; $n++) {
                if (($head[$n - 1] ?? "\0") !== ($work[$n - 1 + $k] ?? "\1")) {
                    $all = false;
                    break;
                }
            }

            if ($all) {
                $found = $k;
                break;
            }
        }

        if ($found === null) {
            $stuck++;
        }

        $report[$key] = $found;
        printf("%-46s %-14s %s\n", basename($rel), $from.'-'.$to, $found === null
            ? 'NO UNIFORM SHIFT — read'
            : ($found === 0 ? 'unchanged' : '-> '.($from + $found).'-'.($to + $found).' (+'.$found.')'));
    }
}

printf("\n%d citation(s) measured, %d with no uniform shift.\n", count($report), $stuck);

file_put_contents($root.'/storage/app/scratch/lane51-shifts.json', json_encode($report, JSON_PRETTY_PRINT));
echo "wrote lane51-shifts.json\n";
