<?php
/**
 * Read-only: for each desk page that renders the shared table, print its column definitions and the
 * DataTable props, so a wiring pass knows which headers a person actually sees.
 */
$files = [
    'resources/js/Pages/Blocking/Index.jsx',
    'resources/js/Pages/Exam/Index.jsx',
    'resources/js/Pages/Exam/Results.jsx',
    'resources/js/Pages/Admin/WorkflowMonitor.jsx',
];
$root = dirname(__DIR__, 3);
foreach ($files as $rel) {
    $path = $root.DIRECTORY_SEPARATOR.$rel;
    echo "==== {$rel}\n";
    if (! is_file($path)) {
        echo "  (no such page)\n";
        continue;
    }
    $lines = file($path);
    $in = false;
    foreach ($lines as $i => $line) {
        $t = trim($line);
        if (preg_match('/const columns\s*=\s*(useMemo\(\(\)\s*=>\s*\[|\[)/', $t)) {
            $in = true;
        }
        if ($in && preg_match('/^\{?\s*key:/', $t)) {
            printf("%4d %s\n", $i + 1, $t);
        }
        if ($in && ($t === '];' || $t === '),' || $t === '],')) {
            $in = false;
        }
        if (preg_match('/<(DataTable|sort=|onSort|rows=\{)/', $t) || preg_match('/^sort=\{/', $t) || preg_match('/^onSort=\{/', $t) || preg_match('/^rows=\{/', $t)) {
            printf("%4d %s\n", $i + 1, $t);
        }
    }
}
