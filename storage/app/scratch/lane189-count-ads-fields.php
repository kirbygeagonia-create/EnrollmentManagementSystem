<?php
// Counts the field rows of every two-column table in the ADS test plan, because the prompt asks for ten fields per
// case and a cell that carries a raw pipe silently splits its row — which reads as an eleventh field to a human and
// as two malformed rows to a parser. Prints each table's heading, its row count, and the distinct field names.
$path = $argv[1] ?? 'Documentation/TESTING-AND-VERIFICATION-ADS.md';
if (! is_file($path)) {
    fwrite(STDERR, "REFUSED: no file at {$path}\n");
    exit(1);
}
$lines = file($path);
$tables = [];
$current = null;
$heading = '(top of file)';
foreach ($lines as $i => $raw) {
    $line = rtrim((string) $raw);
    if (preg_match('/^#{2,4}\s+(.*)$/u', $line, $m) === 1) {
        $heading = trim($m[1]);
        continue;
    }
    if (preg_match('/^\|\s*([^|]+?)\s*\|\s*([^|]+?)\s*\|\s*$/u', $line, $m) === 1) {
        $key = $heading;
        if ($current !== $key) {
            $current = $key;
            $tables[$key] = ['rows' => 0, 'fields' => [], 'line' => $i + 1];
        }
        if (strtolower(trim($m[1])) === 'field') {
            continue;
        }
        if (preg_match('/^[-\s|:]+$/', $line) === 1) {
            continue;
        }
        $tables[$key]['rows']++;
        $tables[$key]['fields'][] = trim($m[1]);
        continue;
    }
    $current = null;
}
$odd = 0;
foreach ($tables as $name => $t) {
    if ($t['rows'] !== 10) {
        $odd++;
        printf("  %-46s %2d field rows (line %d): %s\n", substr($name, 0, 46), $t['rows'], $t['line'], implode(', ', array_slice($t['fields'], 0, 12)));
    }
}
printf("%d two-column table(s) read, %d with a field count other than 10.\n", count($tables), $odd);
