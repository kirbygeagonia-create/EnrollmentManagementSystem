<?php

/**
 * Lane 64, step 1 — which tables really are empty in the demo database.
 *
 * §24.17 lists tables "empty in the demo database", and pass 179f's neighbour made me read it: it
 * still says `gradescale` has no rows while §24's own entry for that table states "Rows in the demo
 * database: 4". information_schema.TABLES.TABLE_ROWS is an InnoDB ESTIMATE — it reported clearanceperiods
 * as 0 while the clearance rows join to it — so this counts every base table with COUNT(*), prints the
 * true empty set, and prints what §24.17 currently claims. Read-only; writes nothing.
 */

require_once dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$tables = array_map(
    fn (stdClass $r): string => $r->TABLE_NAME,
    DB::select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")
);

$counts = [];
foreach ($tables as $t) {
    $counts[$t] = (int) DB::select("SELECT COUNT(*) c FROM `{$t}`")[0]->c;
}
$empty = array_keys(array_filter($counts, fn (int $c): bool => $c === 0));

printf("base tables: %d\n", count($tables));
printf("truly empty (COUNT(*) = 0): %d\n", count($empty));
foreach ($empty as $t) {
    printf("  %-28s 0\n", $t);
}

echo "\nrow counts for the tables §24.17 currently lists:\n";
foreach (['gradescale', 'transferacademicrecords', 'creditedsubjects', 'scholarshiptypes', 'studentscholarships',
    'schedulemeetings', 'model_has_permissions', 'settings'] as $t) {
    printf("  %-24s %d\n", $t, $counts[$t] ?? -1);
}

$flat = (string) file_get_contents(dirname(__DIR__, 3).'/storage/app/scratch/docx-flat.txt');
$listed = [];
foreach (explode("\n", $flat) as $i => $line) {
    if (preg_match('/^ROW (\d+): (\w+) \| [A-Z][^|]*\| [^|]*\| /', $line, $m) === 1
        && (int) $m[1] >= 2161 && (int) $m[1] <= 2170) {
        $listed[] = $m[2];
    }
}
echo "\n§24.17 lists (from the flat dump, lines 2161-2170): ".implode(', ', $listed)."\n";
$claimedEmpty = array_diff($listed, $tables);
if ($claimedEmpty !== []) {
    echo 'names in that range that are not tables of this schema: '.implode(', ', $claimedEmpty)."\n";
}
echo "\nthe section is wrong about: ";
$wrong = [];
foreach ($listed as $t) {
    if (($counts[$t] ?? null) > 0) {
        $wrong[] = $t.' ('.$counts[$t].' rows)';
    }
}
echo ($wrong === [] ? 'nothing — every table it lists is empty' : implode(', ', $wrong))."\n";
echo 'tables empty on the server that the section does not name: ';
$missing = array_values(array_diff($empty, $listed));
echo ($missing === [] ? 'none' : implode(', ', $missing))."\n";
