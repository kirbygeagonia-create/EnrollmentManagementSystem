<?php

/**
 * Lane 70: the §25 identity matrix — every bare number in it, read against the file its COLUMN owns.
 *
 * The matrix is the document's widest table: one row per required context fact, one column per printed
 * document, and each cell says what the desk screen does and what the PDF does — `desk ✓ ( :87-90 ) /
 * PDF ✗`. The numbers are bare because the file is never named in the cell: it is a COLUMN property. So the
 * bounds sweep's rule (attribute a bare `:NN` to the file named within 160 characters on the line) cannot see
 * any of them, and 24 of this repository's 70 unattributed pointers are these cells.
 *
 * This tool states the mapping itself, prints the lines each number lands on, and refuses when the mapping or
 * the table is missing. It exits 0 on a verdict of MOVED, because "the range is inside the file and non-blank"
 * is not the claim a matrix cell makes — the claim is that those lines print that field, which is the reading
 * the printed lines support.
 *
 *   php storage/app/scratch/lane70-matrix-cites.php [--dump=<flat dump>]
 */
$root = dirname(__DIR__, 3);
$dumpPath = $root.'/storage/app/scratch/docx-flat.txt';
foreach ($argv ?? [] as $a) {
    if (str_starts_with($a, '--dump=')) {
        $dumpPath = substr($a, 7);
    }
}
if (! is_file($dumpPath)) {
    echo "REFUSED: no dump at {$dumpPath}. Run dump-docx-flat.php first.\n";
    exit(1);
}

/** Column name => the files that column's bare numbers belong to. Built from the repository, not recalled. */
$columns = [
    'Enrollment Form' => ['desk' => null, 'pdf' => 'enrollment-form.blade.php'],
    'Certificate' => ['desk' => 'PrintCertificate.jsx', 'pdf' => 'enrollment-certificate.blade.php'],
    'Class Card' => ['desk' => 'PrintClassCards.jsx', 'pdf' => 'class-card.blade.php'],
    'Subject Load' => ['desk' => 'PrintSubjectLoad.jsx', 'pdf' => 'subject-load.blade.php'],
    'Clearance Slip' => ['desk' => 'PrintSlip.jsx', 'pdf' => 'clearance-slip.blade.php'],
    'Block & Schedule' => ['desk' => 'PrintSchedule.jsx', 'pdf' => 'block-schedule.blade.php'],
];
$missing = [];
$resolved = [];
foreach (['resources/views/prints', 'resources/js/Pages/Registrar', 'resources/js/Pages/Clearance', 'resources/js/Pages/Blocking'] as $dir) {
    foreach (glob($root.'/'.$dir.'/*') ?: [] as $f) {
        $resolved[basename($f)] = $dir.'/'.basename($f);
    }
}
foreach ($columns as $col => $pair) {
    foreach ($pair as $side => $file) {
        if ($file !== null && ! isset($resolved[$file])) {
            $missing[] = $col.'/'.$side.' → '.$file;
        }
    }
}
if ($missing !== []) {
    echo "REFUSED: the column map names files that are not in the tree: ".implode(', ', $missing)."\n";
    exit(1);
}

$lines = explode("\n", (string) file_get_contents($dumpPath));
$headerAt = null;
foreach ($lines as $i => $l) {
    if (str_contains($l, 'Enrollment Form | Certificate | Class Card')) {
        $headerAt = $i;

        break;
    }
}
if ($headerAt === null) {
    echo "REFUSED: the identity matrix's header row is not in the dump — the table moved or the dump is stale.\n";
    exit(1);
}

$cache = [];
$read = function (string $rel) use ($root, &$cache): array {
    if (! isset($cache[$rel])) {
        $cache[$rel] = (array) file($root.'/'.$rel);
    }

    return $cache[$rel];
};

echo 'matrix header at dump line '.($headerAt + 1)."\n";
$cells = 0;
$stats = ['OK' => 0, 'MOVED' => 0, 'OUT' => 0, 'NOOWNER' => 0];
for ($i = $headerAt + 1; $i < count($lines); $i++) {
    $l = $lines[$i];
    if (! preg_match('/^ROW \d+: /', $l)) {
        if ($cells > 0) {
            break;
        }

        continue;
    }
    $rowCells = preg_split('/ \| /', preg_replace('/^ROW \d+: /', '', $l));
    // The matrix is one label cell plus one cell per column; the table that follows it (the print register)
    // has five cells, so the width is where this table ends.
    if (count($rowCells) !== count($columns) + 1) {
        break;
    }
    $colName = array_keys($columns);
    printf("\n--- %s ---\n", mb_substr(trim($rowCells[0]), 0, 60));
    foreach (array_slice($rowCells, 1) as $c => $cell) {
        $col = $colName[$c] ?? null;
        if ($col === null) {
            continue;
        }
        $side = 'pdf';
        // No PREG_OFFSET_CAPTURE here: under it every group is the array ['',-1] when the alternation did not
        // match it, so `! empty($t['word'])` is true for every token and the loop silently classifies all of
        // them as the side word — a checker that reads nothing and reports a clean table.
        // The alternation is top-level on purpose. Written as `(?<word>A|B|C|:NN)` every match lands inside
        // `word`, the side branch fires for a bare number, and the run reports a clean table it never read.
        if (preg_match_all('/(?<word>\bdesk\b|\bPDF\b)|(?<file>[A-Za-z0-9_.\-]+\.(?:php|jsx|js|md))|:(?<from>\d+)(?:-(?<to>\d+))?/u', $cell, $m, PREG_SET_ORDER) === 0) {
            continue;
        }
        foreach ($m as $t) {
            $word = (string) ($t['word'] ?? '');
            $fileTok = (string) ($t['file'] ?? '');
            $fromTok = (string) ($t['from'] ?? '');
            if ($word !== '') {
                $side = strcasecmp($word, 'desk') === 0 ? 'desk' : 'pdf';

                continue;
            }
            if ($fileTok !== '') {
                continue; // a named citation carries its own file; the bounds sweep already judges it
            }
            if ($fromTok === '') {
                continue;
            }
            $cells++;
            $file = $columns[$col][$side];
            $from = (int) $fromTok;
            $to = ($t['to'] ?? '') === '' ? $from : (int) $t['to'];
            if ($file === null) {
                $stats['NOOWNER']++;
                printf("  NOOWNER     %-16s %-4s :%s  — this column has no %s file, so the number can only be the PDF's (%s)\n",
                    $col, $side, sprintf('%d-%d', $from, $to), $side, $columns[$col]['pdf']);

                continue;
            }
            $rel = $resolved[$file];
            $fl = $read($rel);
            $firstLine = trim($fl[$from - 1] ?? '');
            if ($from > count($fl) || $to > count($fl)) {
                $stats['OUT']++;
                printf("  OUT-OF-FILE %-16s %-4s :%s  — %s has %d lines\n", $col, $side,
                    sprintf('%d-%d', $from, $to), $file, count($fl));

                continue;
            }
            $verdict = $firstLine === '' ? 'MOVED' : 'OK';
            $stats[$verdict]++;
            printf("  %-11s %-16s %-4s :%s  %s\n                 %s\n", $verdict, $col, $side,
                sprintf('%d-%d', $from, $to), $file,
                mb_substr(preg_replace('/\s+/', ' ', $firstLine), 0, 100));
        }
    }
}

printf("\nbare matrix pointer(s) read: %d — %s\n", $cells, implode(', ', array_map(static fn ($k, $v): string => $k.' '.$v, array_keys($stats), $stats)));
echo "This is a reading queue: OK means the line is non-blank and inside the file, not that it prints the field the cell claims.\n";
