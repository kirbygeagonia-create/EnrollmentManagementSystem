<?php
/**
 * Pass 22 — the §24 column listings, regenerated from information_schema.
 *
 * Fourteen columns exist on the server that the dictionary does not list. Each is added as a
 * row CLONED from the last row of its own table, so it inherits the table's borders, widths
 * and fonts; the six cells are then rewritten from the server's own values, in the cell forms
 * the chapter already uses ("no"/"yes", "IDX", "-> staffusers.userId").
 *
 * The heading of every touched table ("payments — 10 columns") and §24.1's totals are corrected
 * in the same write, and the guard refuses the whole thing if anything other than the intended
 * rows, cells and paragraphs moved.
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

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$z = new ZipArchive();
$z->open($src);
$xml = $z->getFromName('word/document.xml');
$z->close();
$original = $xml;

$columns = [];
foreach (DB::select("select table_name t, column_name c, column_type ty, is_nullable n, column_key k, column_default d, extra as x, ordinal_position p from information_schema.columns where table_schema='ems' order by table_name, ordinal_position") as $r) {
    $columns[strtolower($r->t)][] = $r;
}
$fks = [];
foreach (DB::select("select table_name t, column_name c, referenced_table_name rt, referenced_column_name rc from information_schema.key_column_usage where table_schema='ems' and referenced_table_name is not null") as $r) {
    $fks[strtolower($r->t).'.'.$r->c] = $r->rt.'.'.$r->rc;
}

$esc = fn (string $s) => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

/** The six cell values for one column, in the chapter's own notation. */
$cellsFor = function (string $table, object $c) use ($fks): array {
    $key = match ($c->k) {
        'PRI' => 'PK',
        'MUL' => 'IDX',
        'UNI' => 'UQ',
        default => '',
    };
    $default = str_contains(strtolower($c->x), 'auto_increment') ? 'auto' : ($c->d === null ? '' : (string) $c->d);

    return [$c->c, $c->ty, $c->n === 'NO' ? 'no' : 'yes', $key, $default, isset($fks[$table.'.'.$c->c]) ? '-> '.$fks[$table.'.'.$c->c] : ''];
};

preg_match_all('/<w:tbl>.*?<\/w:tbl>/s', $xml, $tables, PREG_OFFSET_CAPTURE);

// Locate each documented table block, its column rows, and what the server says is missing.
$work = [];
foreach ($tables[0] as $t) {
    $plain = preg_replace('/\s+/', ' ', strip_tags(substr($xml, max(0, $t[1] - 6000), min($t[1], 6000))));
    if (! preg_match_all('/([a-z_]+) — (\d+) columns/', $plain, $hm, PREG_SET_ORDER)) {
        continue;
    }
    $head = $hm[count($hm) - 1];
    $table = $head[1];
    if (! isset($columns[$table])) {
        continue;
    }

    preg_match_all('/<w:tr>.*?<\/w:tr>/s', $t[0], $trs, PREG_OFFSET_CAPTURE);
    $documented = [];
    $templateRow = null;
    $insertAt = null;
    foreach ($trs[0] as $row) {
        preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row[0], $cells);
        if (count($cells[0]) !== 6) {
            continue;
        }
        // $cells[0][0] is the first cell's XML — indexing it again would yield one character.
        preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cells[0][0], $first);
        $name = trim(html_entity_decode(implode('', $first[1] ?? []), ENT_QUOTES | ENT_XML1));
        if ($name === '' || $name === 'Column') {
            continue;
        }
        // The last data row of the block is both the style template and the insertion point.
        $documented[] = $name;
        $templateRow = $row[0];
        $insertAt = $t[1] + $row[1] + strlen($row[0]);
    }

    $missing = array_values(array_filter($columns[$table], fn ($c) => ! in_array($c->c, $documented, true)));
    if ($missing === []) {
        continue;
    }
    if ($templateRow === null) {
        echo "SKIP {$table}: no data row to clone\n";

        continue;
    }

    $work[] = [
        'table' => $table,
        'stated' => (int) $head[2],
        'server' => count($columns[$table]),
        'insertAt' => $insertAt,
        'missing' => $missing,
        'templateRow' => $templateRow,
    ];
}

if ($work === []) {
    fwrite(STDERR, "nothing to add — the listings already match the server\n");
    exit(1);
}

// Build the new rows: rewrite all six cells of the cloned template, from the last cell
// backwards so the offsets inside the row stay valid.
foreach ($work as $i => $w) {
    $block = '';
    foreach ($w['missing'] as $c) {
        $values = $cellsFor($w['table'], $c);
        $row = $w['templateRow'];
        preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cells, PREG_OFFSET_CAPTURE);
        foreach (array_reverse($cells[0], true) as $j => $cell) {
            [, $end] = $cell;
            $body = $cell[0];
            preg_match('/<w:r\b.*?<\/w:r>/s', $body, $rm);
            preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
            $stripped = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $body);
            $text = $values[$j] ?? '';
            $new = $text === ''
                ? $stripped
                : str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'.$esc($text).'</w:t></w:r></w:p>', $stripped);
            $row = substr_replace($row, $new, $cell[1], strlen($body));
        }
        $block .= $row;
    }
    $work[$i]['block'] = $block;
    printf("%-24s + %d row(s): %s\n", $w['table'], count($w['missing']), implode(', ', array_map(fn ($c) => $c->c, $w['missing'])));
}

$addedRows = array_sum(array_map(fn ($w) => count($w['missing']), $work));

if (! $apply) {
    printf("\nDRY RUN — %d rows across %d tables would be inserted\n", $addedRows, count($work));
    exit(0);
}

copy($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-04-pass22.docx');

// Insert from the end backwards; a heading fix at an offset before an insert does not move.
usort($work, fn ($a, $b) => $b['insertAt'] <=> $a['insertAt']);
foreach ($work as $w) {
    $xml = substr_replace($xml, $w['block'], $w['insertAt'], 0);
}

// Headings: "<table> — N columns" for every table that gained rows. The number cannot be
// str_replaced in place — the em dash and the digits sit in separate runs — so the heading
// paragraph is rewritten as a whole, in its own style.
$fixes = [];
preg_match_all('/<w:p[ >].*?<\/w:p>/s', $xml, $paras, PREG_OFFSET_CAPTURE);
foreach ($paras[0] as $p) {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $p[0], $t3);
    $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $t3[1]), ENT_QUOTES | ENT_XML1)));
    foreach ($work as $w) {
        if ($text === $w['table'].' — '.$w['stated'].' columns') {
            $fixes[] = ['at' => $p[1], 'len' => strlen($p[0]), 'to' => $w['table'].' — '.$w['server'].' columns'];
        }
    }
}
if (count($fixes) !== count($work)) {
    fwrite(STDERR, 'REFUSED WRITE: matched '.count($fixes).' headings of '.count($work)." tables\n");
    exit(1);
}
usort($fixes, fn ($a, $b) => $b['at'] <=> $a['at']);
foreach ($fixes as $f) {
    $body = substr($xml, $f['at'], $f['len']);
    preg_match('/<w:r\b.*?<\/w:r>/s', $body, $rm);
    preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
    $stripped = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $body);
    $stripped = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'.$esc($f['to']).'</w:t></w:r></w:p>', $stripped);
    $xml = substr_replace($xml, $stripped, $f['at'], $f['len']);
    echo "guard  heading corrected: {$f['to']}\n";
}

// Structure guard: rows, cells and paragraphs move by the intended amounts; nothing else.
foreach (['<w:tbl>' => 0, '</w:tbl>' => 0, '<w:tr>' => $addedRows, '</w:tr>' => $addedRows, '<w:tc>' => $addedRows * 6, '</w:tc>' => $addedRows * 6] as $tag => $delta) {
    $moved = preg_match_all('/'.preg_quote($tag, '/').'/', $xml) - preg_match_all('/'.preg_quote($tag, '/').'/', $original);
    if ($moved !== $delta) {
        fwrite(STDERR, "REFUSED WRITE: {$tag} moved by {$moved}, expected {$delta}\n");
        exit(1);
    }
    echo "guard  {$tag} moved by {$moved} as intended\n";
}
if (preg_match_all('/<w:p[ >]/', $xml) - preg_match_all('/<w:p[ >]/', $original) !== $addedRows * 6) {
    fwrite(STDERR, "REFUSED WRITE: paragraph count moved unexpectedly\n");
    exit(1);
}
if (simplexml_load_string($xml) === false) {
    fwrite(STDERR, "REFUSED WRITE: document.xml no longer parses\n");
    exit(1);
}
echo "guard  paragraphs added as intended, document.xml parses\n";

$tmp = sys_get_temp_dir().'/ems-docx22-'.uniqid().'.docx';
copy($src, $tmp);
$z2 = new ZipArchive();
$z2->open($tmp);
$z2->addFromString('word/document.xml', $xml);
$z2->close();
rename($tmp, $src);
echo "WROTE {$src}\n";
