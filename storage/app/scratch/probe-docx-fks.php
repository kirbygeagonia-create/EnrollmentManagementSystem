<?php
/**
 * Diff §23.3's foreign-key inventory against information_schema.
 *
 * The chapter's own §24 totals row already admits the inventory was compiled at 86 while the
 * server reports 100. This names the gap edge by edge so the rows can be generated rather than
 * remembered. Prints the edges the document lacks, the ones it lists that no longer exist, and
 * the ones whose delete/update rule moved. Nothing is written.
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

config([
    'database.default' => 'mysql',
    'database.connections.mysql.database' => 'ems',
    'database.connections.mysql.username' => 'root',
    'database.connections.mysql.password' => '',
]);
DB::purge('mysql');

$z = new ZipArchive();
$z->open(dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx');
$xml = $z->getFromName('word/document.xml');
$z->close();

preg_match_all('/<w:tbl>.*?<\/w:tbl>/s', $xml, $tables, PREG_OFFSET_CAPTURE);

$target = null;
foreach ($tables[0] as $t) {
    $head = $t[0];
    preg_match('/<w:tr>.*?<\/w:tr>/s', $head, $firstRow);
    $cells = [];
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $firstRow[0], $cs);
    foreach ($cs[0] as $c) {
        $cells[] = trim(preg_replace('/\s+/', ' ', strip_tags($c)));
    }
    if ($cells === ['Child column', 'References', 'On delete', 'On update']) {
        $target = ['xml' => $head, 'at' => $t[1]];

        break;
    }
}

if ($target === null) {
    fwrite(STDERR, "the §23.3 inventory table was not found by its header row\n");
    exit(1);
}

$documented = [];
preg_match_all('/<w:tr>.*?<\/w:tr>/s', $target['xml'], $trs);
foreach ($trs[0] as $row) {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cs);
    $cells = [];
    foreach ($cs[0] as $c) {
        $cells[] = trim(preg_replace('/\s+/', ' ', strip_tags($c)));
    }
    if (count($cells) !== 4 || $cells[0] === 'Child column') {
        continue;
    }
    $documented[] = $cells;
}

printf("inventory table found at offset %d, %d documented edges (%d rows read)\n\n",
    $target['at'], count($documented), count($trs[0]));

// The server's edges, with the delete/update rule spelled the way the chapter spells it.
$live = [];
foreach (DB::select("
    select k.table_name t, k.column_name c, k.referenced_table_name rt, k.referenced_column_name rc,
           r.delete_rule d, r.update_rule u
    from information_schema.key_column_usage k
    join information_schema.referential_constraints r
      on r.constraint_name = k.constraint_name and r.constraint_schema = k.table_schema
    where k.table_schema = 'ems' and k.referenced_table_name is not null
    order by k.table_name, k.column_name") as $r) {
    $live[strtolower($r->t).'.'.strtolower($r->c)] = [
        'to' => $r->rt.'.'.$r->rc,
        'delete' => str_replace(' ', '', $r->d),
        'update' => str_replace(' ', '', $r->u),
    ];
}

printf("information_schema reports %d referential edges\n\n", count($live));

$docKeys = [];
foreach ($documented as $row) {
    // "students.religionId" — the chapter puts the child column in the first cell verbatim.
    $key = strtolower(trim($row[0]));
    $docKeys[$key] = $row;
}

echo "--- in the server, NOT in the document ---\n";
$missing = 0;
foreach ($live as $key => $v) {
    if (! isset($docKeys[$key])) {
        printf("  %-58s %-34s %s / %s\n", $key, $v['to'], $v['delete'], $v['update']);
        $missing++;
    }
}
echo $missing === 0 ? "  (none)\n" : '';

echo "\n--- in the document, NOT in the server ---\n";
$ghost = 0;
foreach ($docKeys as $key => $row) {
    if (! isset($live[$key])) {
        printf("  %-58s %-34s %s / %s\n", $key, $row[1], $row[2], $row[3]);
        $ghost++;
    }
}
echo $ghost === 0 ? "  (none)\n" : '';

echo "\n--- documented, present, but the rule reads differently ---\n";
$drift = 0;
foreach ($docKeys as $key => $row) {
    if (! isset($live[$key])) {
        continue;
    }
    $v = $live[$key];
    $docDelete = strtoupper(str_replace(' ', '', $row[2]));
    $docUpdate = strtoupper(str_replace(' ', '', $row[3]));
    $docTo = strtolower(trim(preg_replace('/\s+/', '', $row[1])));
    if ($docDelete !== strtoupper($v['delete']) || $docUpdate !== strtoupper($v['update']) || $docTo !== strtolower($v['to'])) {
        printf("  %-58s doc: %-32s %s/%s   server: %-32s %s/%s\n",
            $key, $row[1], $docDelete, $docUpdate, $v['to'], $v['delete'], $v['update']);
        $drift++;
    }
}
echo $drift === 0 ? "  (none)\n" : '';

printf("\ntotals: documented %d, server %d, missing %d, ghost %d, drift %d\n",
    count($docKeys), count($live), $missing, $ghost, $drift);
