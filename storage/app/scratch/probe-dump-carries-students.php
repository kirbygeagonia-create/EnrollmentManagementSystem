<?php
/**
 * Which table actually carries DEMO-2026-001 and DEMO-2026-004 in the shipped demo dump?
 *
 * The claim being checked is one this repository makes to an operator ("restore the demo dataset, then run
 * the pipeline"), so it is worth getting right: the earlier one-line regex looked *backwards* from the match
 * for the nearest INSERT and reported `academicterms, blocks`, which is simply the wrong statement. This
 * walks the file statement by statement and asks, for each INSERT, whether the student number sits inside
 * that statement's own text.
 */
$path = 'Documentation/ems-demo-backup.sql';
$s = file_get_contents($path);

$want = ['DEMO-2026-001', 'DEMO-2026-004'];
$hits = array_fill_keys($want, []);

if (preg_match_all('/^INSERT INTO `([^`]+)` .*?;\s*$/ms', $s, $statements) === false) {
    fwrite(STDERR, "cannot walk the dump\n");
    exit(1);
}

foreach ($statements[0] as $i => $sql) {
    $table = $statements[1][$i];
    foreach ($want as $id) {
        if (str_contains($sql, $id)) {
            $hits[$id][] = $table.' ('.substr_count($sql, $id).' occurrence(s) in that statement)';
        }
    }
}

foreach ($want as $id) {
    printf("%s: %s\n", $id, $hits[$id] ? implode('; ', array_unique($hits[$id])) : 'NOT FOUND in any INSERT');
}

printf("\nstatements walked: %d\n", count($statements[0]));
