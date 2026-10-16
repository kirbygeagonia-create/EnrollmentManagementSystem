<?php

// Read-only audit scratch tool (gitignored path): print NOT NULL, no-default
// columns (the ones a test must supply) for the given tables.

$pdo = new PDO('sqlite:'.$argv[1]);
array_shift($argv);

foreach ($argv as $t) {
    $cols = [];
    foreach ($pdo->query('PRAGMA table_info("'.$t.'")') as $r) {
        if ((int) $r['notnull'] === 1 && $r['dflt_value'] === null && (int) $r['pk'] === 0) {
            $cols[] = $r['name'].' '.$r['type'];
        }
    }
    echo str_pad($t, 30).implode(', ', $cols)."\n";
}
