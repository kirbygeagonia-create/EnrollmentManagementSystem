<?php
// Reads a window of the flat dump with line numbers, truncating long rows.
$from = (int) ($argv[1] ?? 1631);
$to = (int) ($argv[2] ?? 1668);
$limit = (int) ($argv[3] ?? 170);
$lines = file('storage/app/scratch/docx-flat.txt');
for ($i = $from - 1; $i < $to && $i < count($lines); $i++) {
    $s = trim((string) $lines[$i]);
    if ($s === '') {
        continue;
    }
    echo ($i + 1).': '.(strlen($s) > $limit ? substr($s, 0, $limit).' …['.strlen($s).']' : $s)."\n";
}
