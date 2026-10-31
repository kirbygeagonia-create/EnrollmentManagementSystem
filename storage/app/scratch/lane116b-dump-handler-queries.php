<?php
/**
 * Read-only: print the query-building region of each action a G-40 wiring pass is about to touch —
 * the builder, the tile clone, the paginate, and the filters/props echo — with line numbers, so the
 * order is inserted after the clone and the echo gains its two keys on the right line.
 */
$root = dirname(__DIR__, 3);
$targets = [
    'app/Http/Controllers/BlockingController.php' => 'index',
    'app/Http/Controllers/Exam/ExamController.php' => 'index',
    'app/Http/Controllers/Exam/ExamController.php' => 'results',
];
foreach ($targets as $rel => $action) {
    $path = $root.DIRECTORY_SEPARATOR.$rel;
    echo "==== {$rel} :: {$action}\n";
    $lines = file($path);
    $start = null;
    $depth = 0;
    $seen = false;
    foreach ($lines as $i => $raw) {
        if (! $seen && preg_match('/public function '.preg_quote($action, '/').'\(.*\)/', $raw)) {
            $start = $i;
            $seen = true;
        }
        if (! $seen) {
            continue;
        }
        $depth += substr_count($raw, '{') - substr_count($raw, '}');
        $t = rtrim($raw);
        if (preg_match('/query|Query|::with|::query|when\(|orderBy|clone|paginate|filters|Inertia::render|=> \$|sortable|ListSort/', $t)) {
            printf("%4d %s\n", $i + 1, $t);
        }
        if ($seen && $depth <= 0 && $i > $start) {
            break;
        }
    }
}
