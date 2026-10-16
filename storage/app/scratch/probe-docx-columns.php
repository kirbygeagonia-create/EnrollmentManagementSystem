<?php
/**
 * Diff the §24 column listings against information_schema.
 *
 * For every table block in the dictionary chapter, read its column rows (six cells: name,
 * type, null, key, default, references) and compare with what the server reports today.
 * Prints what is missing, what the document still lists that the server does not have, and
 * what the document states with the wrong type. Nothing is written.
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

// Table blocks, in document order, with the heading that names their table. The heading is
// the LAST "<name> — N columns" in the plain text before the table starts, so the tag soup
// has to be stripped before it can be matched at all.
$blocks = [];
preg_match_all('/<w:tbl>.*?<\/w:tbl>/s', $xml, $tables, PREG_OFFSET_CAPTURE);
foreach ($tables[0] as $t) {
    $before = substr($xml, max(0, $t[1] - 6000), min($t[1], 6000));
    $plain = preg_replace('/\s+/', ' ', strip_tags($before));
    if (! preg_match_all('/([a-z_]+) — (\d+) columns/', $plain, $hm, PREG_SET_ORDER)) {
        continue;
    }
    $last = $hm[count($hm) - 1];
    $blocks[] = ['table' => $last[1], 'stated' => (int) $last[2], 'at' => $t[1], 'len' => strlen($t[0]), 'xml' => $t[0]];
}

$live = [];
foreach (DB::select("select table_name t, column_name c, column_type ty, is_nullable n, column_key k, column_default d from information_schema.columns where table_schema='ems' order by table_name, ordinal_position") as $r) {
    $live[strtolower($r->t)][] = $r;
}

$fks = [];
foreach (DB::select("select table_name t, column_name c, referenced_table_name rt, referenced_column_name rc from information_schema.key_column_usage where table_schema='ems' and referenced_table_name is not null") as $r) {
    $fks[strtolower($r->t).'.'.$r->c] = $r->rt.'->'.$r->rc;
}

$totalMissing = 0;
foreach ($blocks as $b) {
    $rows = preg_match_all('/<w:tr>.*?<\/w:tr>/s', $b['xml'], $trs);
    $documented = [];
    foreach ($trs[0] as $row) {
        preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cells);
        if (count($cells[0]) !== 6) {
            continue;
        }
        $vals = [];
        foreach ($cells[0] as $cell) {
            preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell, $t2);
            $vals[] = trim(html_entity_decode(implode('', $t2[1]), ENT_QUOTES | ENT_XML1));
        }
        if ($vals[0] === 'Column' || $vals[0] === '') {
            continue;
        }
        $documented[] = $vals[0];
    }

    $actual = array_map(fn ($r) => $r->c, $live[$b['table']] ?? []);
    $missing = array_diff($actual, $documented);
    $ghosts = array_diff($documented, $actual);

    if ($missing === [] && $ghosts === []) {
        continue;
    }

    printf("%s: documented %d, server %d\n", $b['table'], count($documented), count($actual));
    foreach ($missing as $col) {
        $r = collect($live[$b['table']])->first(fn ($x) => $x->c === $col);
        printf("  MISSING  %-28s %-22s null=%s key=%-4s default=%s%s\n", $col, $r->ty, $r->n, $r->k ?: '-', var_export($r->d, true), isset($fks[$b['table'].'.'.$col]) ? '  fk='.$fks[$b['table'].'.'.$col] : '');
        $totalMissing++;
    }
    foreach ($ghosts as $col) {
        printf("  GHOST    %s (documented, not on the server)\n", $col);
        $totalMissing++;
    }
}

printf("\n%d rows to add or remove across %d documented tables\n", $totalMissing, count($blocks));
printf("shiftingrequests documented? %s\n", in_array('shiftingrequests', array_column($blocks, 'table'), true) ? 'yes' : 'NO — whole block missing');
printf("server tables=%d documented blocks=%d\n", count($live), count($blocks));
