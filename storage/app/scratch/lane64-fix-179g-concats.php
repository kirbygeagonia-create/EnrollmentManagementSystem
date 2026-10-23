<?php

/**
 * One-off tidy: pass 179g was written with both a trailing `.` on a continuation line and a leading
 * `.` on the next, which PHP reads as two concatenation operators. Collapse `'x'.\n  .'y'` to
 * `'x'\n  .'y'`. Reports every line it changes so the fix is visible rather than silent.
 */
$path = $argv[1] ?? dirname(__DIR__, 3).'/storage/app/scratch/refresh-docx-pass179g.php';
$src = (string) file_get_contents($path);
$out = preg_replace("/\.(\r?\n\s*)\.'/", '$1.\'', $src, -1, $count);
if ($count === 0) {
    echo "nothing to change\n";
    exit(0);
}
file_put_contents($path, $out);
echo "fixed {$count} doubled-concatenation site(s)\n";
