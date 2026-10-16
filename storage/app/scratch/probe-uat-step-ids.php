<?php
/**
 * Are the acceptance script's step numbers unique? Several rows were inserted into existing tables
 * by hand, and a duplicate id means a tester ticks one box and reports on another.
 */

$file = dirname(__DIR__, 3).'/Documentation/Per-Office-UAT-Walkthrough.md';
$lines = file($file, FILE_IGNORE_NEW_LINES);

$seen = [];
$section = '—';
foreach ($lines as $n => $line) {
    if (preg_match('/^## (\d+)\./', $line, $m)) {
        $section = $m[1];
    }
    if (preg_match('/^\|\s*(\d+R?\??\.?\d*)\s*\|/', $line, $m)) {
        $id = $m[1];
        $seen[$id][] = $n + 1;
    }
}

$dupes = array_filter($seen, fn ($rows) => count($rows) > 1);
printf("%d distinct step ids read\n", count($seen));
if ($dupes === []) {
    echo "no duplicate ids\n";
    exit(0);
}
echo "DUPLICATE ids:\n";
foreach ($dupes as $id => $rows) {
    echo "  $id on lines ".implode(', ', $rows)."\n";
}
exit(1);
