<?php

// Read-only audit scratch tool (gitignored path): for every reference-data parent
// table, list the child tables that point at it and what ON DELETE does — the map
// that decides whether a "Delete" button in Reference Data is safe, blocked by a
// RESTRICT FK, or silently cascades away transactional rows.

$pdo = new PDO('sqlite:'.$argv[1]);

$parents = ['courses', 'majors', 'curriculums', 'curriculumsubjects', 'subjects', 'academicyears',
    'academicterms', 'feetypes', 'scholarshiptypes', 'rooms', 'blocks', 'offices', 'academicunits',
    'gradescale', 'admissionrequirements', 'clearancerequirements', 'students'];

$sql = 'select name from sqlite_master where type="table" and name not like "sqlite_%"';
$tables = array_column($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC), 'name');

foreach ($parents as $parent) {
    echo '['.$parent."]\n";
    $found = false;
    foreach ($tables as $t) {
        if ($t === $parent) {
            continue;
        }
        foreach ($pdo->query('PRAGMA foreign_key_list("'.$t.'")') as $fk) {
            if ($fk['table'] !== $parent) {
                continue;
            }
            $found = true;
            printf("  %-28s %s -> %s.%s   on_delete=%s\n", $t, $fk['from'], $parent, $fk['to'], $fk['on_delete']);
        }
    }
    if (! $found) {
        echo "  (no children)\n";
    }
    echo "\n";
}
