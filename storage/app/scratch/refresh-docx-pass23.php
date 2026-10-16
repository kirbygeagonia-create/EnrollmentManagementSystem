<?php
/**
 * Pass 23 — §24's missing table: shiftingrequests, built the way the chapter builds them.
 *
 * Every element is CLONED from the last table of §24.6 (enrolledsubjects): its heading,
 * Role and Model paragraphs, its <w:tbl> (header row kept verbatim, one data row cloned
 * nineteen times and rewritten from information_schema), and its Referenced-by paragraph.
 * The block is inserted before §24.7, so the new table sits in the Enrollment module where
 * the paper lives.
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

$esc = fn (string $s) => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

$columns = DB::select("select column_name c, column_type ty, is_nullable n, column_key k, column_default d, extra as x from information_schema.columns where table_schema='ems' and table_name='shiftingrequests' order by ordinal_position");
$fks = [];
foreach (DB::select("select column_name c, referenced_table_name rt, referenced_column_name rc from information_schema.key_column_usage where table_schema='ems' and table_name='shiftingrequests' and referenced_table_name is not null") as $r) {
    $fks[$r->c] = $r->rt.'.'.$r->rc;
}

$cellsFor = function (object $c) use ($fks, $esc): array {
    $key = match ($c->k) { 'PRI' => 'PK', 'MUL' => 'IDX', 'UNI' => 'UQ', default => '' };
    $default = str_contains(strtolower((string) $c->x), 'auto_increment') ? 'auto' : ($c->d === null ? '' : (string) $c->d);

    return [$c->c, $c->ty, $c->n === 'NO' ? 'no' : 'yes', $key, $default, isset($fks[$c->c]) ? '-> '.$fks[$c->c] : ''];
};

/** Rewrite a paragraph's text into its own first run's properties. An empty string leaves a
 *  paragraph with no run at all — the cell must read as blank, not keep whatever the cloned
 *  row had in it (a primary-key row that "references enrolledsubjects" is a fabricated fact). */
$retext = function (string $body, string $text) use ($esc): string {
    $stripped = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $body);
    if ($text === '') {
        return $stripped;
    }
    preg_match('/<w:r\b.*?<\/w:r>/s', $body, $rm);
    preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);

    return str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'.$esc($text).'</w:t></w:r></w:p>', $stripped);
};

// --- locate the templates and the insertion point, all inside §24.6
//
// The chapter boundary must be found on the HEADING PARAGRAPH, not on the first string
// match: '24.7 Module 5' also appears in the table of contents far earlier, and cutting the
// document there hands over a slice that ends in the middle of a table.
$pReg = '/<w:p[ >].*?<\/w:p>/s';
preg_match_all($pReg, $xml, $paras, PREG_OFFSET_CAPTURE);

$before = [];
$next = null;
foreach ($paras[0] as $p) {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $p[0], $t);
    $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $t[1]), ENT_QUOTES | ENT_XML1)));
    if (str_starts_with($text, '24.7 Module 5')) {
        $next = $p[1];

        break;
    }
    if ($text !== '') {
        $before[] = ['at' => $p[1], 'len' => strlen($p[0]), 'text' => $text];
    }
}
if ($next === null) {
    fwrite(STDERR, "§24.7 heading paragraph not found\n");
    exit(1);
}

$last = fn (callable $f) => collect($before)->filter($f)->last();
$heading = $last(fn ($b) => (bool) preg_match('/^enrolledsubjects — \d+ columns$/', $b['text']));
$role = $last(fn ($b) => str_starts_with($b['text'], 'Role ('));
$model = $last(fn ($b) => str_starts_with($b['text'], 'Model App\Models\Enrolledsubjects'));
$refer = $last(fn ($b) => str_starts_with($b['text'], 'Referenced by'));

if (! $heading || ! $role || ! $model || ! $refer) {
    fwrite(STDERR, "templates not found: heading=".($heading ? 'y' : 'n')." role=".($role ? 'y' : 'n')." model=".($model ? 'y' : 'n')." ref=".($refer ? 'y' : 'n')."\n");
    exit(1);
}

preg_match_all('/<w:tbl>.*?<\/w:tbl>/s', substr($xml, 0, $refer['at']), $tbs);
$templateTable = $tbs[0][count($tbs[0]) - 1];
if (! str_contains($templateTable, 'enrolledSubjectId')) {
    fwrite(STDERR, "the cloned table is not enrolledsubjects' — refusing\n");
    exit(1);
}

$block = $retext(substr($xml, $heading['at'], $heading['len']), 'shiftingrequests — '.count($columns).' columns')
    ."\n".$retext(substr($xml, $role['at'], $role['len']), 'Role: the shift paper itself — a student already enrolled at SEAIT moving to another program (ruling 11, closing G-7). It holds the student’s own will statement, the program being left and the one being entered, the enrollment the grant retires and the one it opens, and three signature pairs: filing, departmental endorsement, and the Guidance decision. Role in workflow: the only lawful way a program changes mid-career (§13.7), and the history row of the change — no separate history table was added.')
    ."\n".$retext(substr($xml, $model['at'], $model['len']), "Model App\\Models\\Shiftingrequests (app/Models/Shiftingrequests.php), \$primaryKey = 'shiftingRequestId', \$timestamps = true. Created by 2026_10_03_1000000008_create_shiftingrequests_table.php. Primary key shiftingRequestId. Rows in the demo database: 0 — the docket is empty until a desk files the first paper.");

// The table: header row verbatim, then one cloned row per column.
preg_match_all('/<w:tr>.*?<\/w:tr>/s', $templateTable, $trs);
$dataRow = null;
// $trs[0] is an array of row STRINGS here (no offset capture), so the element itself is
// the XML — indexing it again yields a single character.
foreach (array_reverse($trs[0]) as $row) {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cs);
    if (count($cs[0]) === 6) {
        $dataRow = $row;

        break;
    }
}
if ($dataRow === null) {
    fwrite(STDERR, "no six-cell row to clone\n");
    exit(1);
}

$table = $templateTable;
$tail = '';
foreach ($columns as $c) {
    $values = $cellsFor($c);
    $row = $dataRow;
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cells, PREG_OFFSET_CAPTURE);
    foreach (array_reverse($cells[0], true) as $j => $cell) {
        $body = $cell[0];
        $text = $values[$j] ?? '';
        // Empty is a value here: it clears the cloned row's text rather than keeping it.
        $row = substr_replace($row, $retext($body, $text), $cell[1], strlen($body));
    }
    $tail .= $row;
}
// Drop every data row of the template except the header, then append the generated ones.
$headerEnd = strpos($table, '</w:tr>') + 7;
$table = substr($table, 0, $headerEnd).$tail.substr($table, strpos($table, '</w:tbl>'));

$block .= "\n".$table."\n".$retext(substr($xml, $refer['at'], $refer['len']), 'Referenced by: nothing — a leaf table. It points outward at students, courses (twice: the program left and the program entered), enrollments (twice: the record retired and the one the grant opens), academicterms, and staffusers (three times: filed, endorsed, decided), which is the whole audit trail of the change in one row.');

$rowsAdded = count($columns);
printf("built a block: %d column rows, %d chars\n", $rowsAdded, strlen($block));

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-04-pass23.docx');

// Insert immediately before §24.7's heading paragraph — after enrolledsubjects' own
// "Referenced by" line, which must stay attached to its table.
$xml = substr_replace($xml, $block."\n", $next, 0);

$checks = [
    '<w:tbl>' => [1, 'one table added'],
    '</w:tbl>' => [1, 'and closed'],
    '<w:tr>' => [$rowsAdded + 1, 'header plus the column rows'],
    '</w:tr>' => [$rowsAdded + 1, 'and closed'],
    '<w:tc>' => [($rowsAdded + 1) * 6, 'six cells per row'],
    '</w:tc>' => [($rowsAdded + 1) * 6, 'and closed'],
];
foreach ($checks as $tag => [$want, $why]) {
    $moved = preg_match_all('/'.preg_quote($tag, '/').'/', $xml) - preg_match_all('/'.preg_quote($tag, '/').'/', $original);
    if ($moved !== $want) {
        fwrite(STDERR, "REFUSED WRITE: {$tag} moved by {$moved}, expected {$want} ({$why})\n");
        exit(1);
    }
    echo "guard  {$tag} +{$moved} ({$why})\n";
}

// Paragraphs are not one-per-cell in a cloned table: a cell can carry a trailing empty
// <w:p>, so the exact count is the template's business. What must hold is that opens and
// closes moved together, by at least the cells added.
$po = preg_match_all('/<w:p[ >]/', $xml) - preg_match_all('/<w:p[ >]/', $original);
$pc = preg_match_all('/<\/w:p>/', $xml) - preg_match_all('/<\/w:p>/', $original);
if ($po !== $pc || $po < ($rowsAdded + 1) * 6) {
    fwrite(STDERR, "REFUSED WRITE: <w:p> moved {$po} open / {$pc} close, expected balanced and at least ".(($rowsAdded + 1) * 6)."\n");
    exit(1);
}
echo "guard  <w:p> +{$po} open and +{$pc} close, balanced\n";
if (substr_count($xml, 'shiftingrequests — '.count($columns).' columns') !== 1) {
    fwrite(STDERR, "REFUSED WRITE: the heading is not unique\n");
    exit(1);
}
if (simplexml_load_string($xml) === false) {
    fwrite(STDERR, "REFUSED WRITE: document.xml no longer parses\n");
    exit(1);
}
echo "guard  heading present, document.xml parses\n";

$tmp = sys_get_temp_dir().'/ems-docx23-'.uniqid().'.docx';
copy($src, $tmp);
$z2 = new ZipArchive();
$z2->open($tmp);
$z2->addFromString('word/document.xml', $xml);
$z2->close();
rename($tmp, $src);
echo "WROTE {$src}\n";
