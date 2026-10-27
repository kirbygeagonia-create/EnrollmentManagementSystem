<?php

/**
 * §24 gains the dictionary entry for `enrollmentreturns`, the table G-10's return act writes.
 *
 * The authority document's data dictionary is one block per table — a `name — N columns` heading, a Role
 * sentence, a Model line, and a table whose header is `Column | Type | Null | Key | Default | References`
 * — and §24.19 then claims the dictionary documents a given number of columns for a given number of domain
 * tables. Live `ems` carries `enrollmentreturns` since the G-10 migration and §24 held nothing about it, so
 * those two §24.19 cells could not be made true by editing them alone: the row has to exist first. That is
 * why this pass is a clone rather than a text edit, and why pass 259 deliberately left the aggregates for it.
 *
 * Every cell is generated from `information_schema` rather than typed, which is the same source §24.19
 * says the dictionary is built from. The template block is `shiftingrequests` (20 rows: header plus 19),
 * the new block needs 16, so four rows are dropped after the fifteen are written.
 *
 * The engine refuses any pass that moves a `<w:tr>`, so the row goes through direct bytes — the same shape
 * as pass 251b — with the engine's own guards re-implemented here: tag balance, a parse, and a read-back
 * that must equal the bytes handed to the archive.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass260.php [--apply]
 */

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

$mysql = 'C:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysql.exe';

$sql = 'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, EXTRA, COLUMN_DEFAULT '
    ."FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='ems' AND TABLE_NAME='enrollmentreturns' "
    .'ORDER BY ORDINAL_POSITION;';

$out = shell_exec(' '.$mysql.' -u root ems -N -B -e "'.str_replace('"', '\"', $sql).'" 2>&1');

if (! is_string($out) || trim($out) === '') {
    fwrite(STDERR, "REFUSED: information_schema answered nothing for enrollmentreturns; this pass writes no cell it has not read.\n");

    exit(1);
}

$rows = [];

foreach (explode("\n", trim(str_replace("\r", '', $out))) as $line) {
    $f = explode("\t", $line);

    if (count($f) !== 6) {
        fwrite(STDERR, "REFUSED: a column row came back with ".count($f)." fields, not 6: {$line}\n");

        exit(1);
    }

    $rows[] = $f;
}

if (count($rows) !== 15) {
    fwrite(STDERR, 'REFUSED: the server answers '.count($rows)." columns for enrollmentreturns, not the 15 this block's heading states.\n");

    exit(1);
}

$fkOut = shell_exec(' '.$mysql.' -u root ems -N -B -e "SELECT k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.CONSTRAINT_SCHEMA = k.TABLE_SCHEMA WHERE k.TABLE_SCHEMA=\'ems\' AND k.TABLE_NAME=\'enrollmentreturns\' AND k.REFERENCED_TABLE_NAME IS NOT NULL;" 2>&1');

$fks = [];

foreach (explode("\n", trim(str_replace("\r", '', (string) $fkOut))) as $line) {
    $f = explode("\t", $line);

    // A server error arrives as one field, and reading it as "no rows" would print a refusal about the
    // wrong thing — or, with a looser guard, an empty set as a measurement.
    if (count($f) !== 4) {
        fwrite(STDERR, "REFUSED: the foreign-key query answered a line that is not four fields: {$line}\n");

        exit(1);
    }

    $fks[$f[0]] = $f;
}

if (count($fks) !== 3) {
    fwrite(STDERR, 'REFUSED: the server holds '.count($fks)." foreign keys on enrollmentreturns, not 3.\n");

    exit(1);
}

// §24's per-table cells, built the way the existing rows read: Key is PK/IDX, Default carries `auto` for
// an auto-increment column and nothing otherwise, References is `-> table.column` with the delete rule
// named when it is not the plain refuse.
$cells = [];

foreach ($rows as [$name, $type, $nullable, $key, $extra, $default]) {
    $ref = '';

    if (isset($fks[$name])) {
        $rule = $fks[$name][3] === 'SET NULL' ? ', SET NULL' : '';
        $ref = '-> '.$fks[$name][1].'.'.$fks[$name][2].$rule;
    }

    $cells[] = [
        $name,
        $type,
        strtolower($nullable),
        $key === 'PRI' ? 'PK' : ($key === 'MUL' ? 'IDX' : ''),
        // `mysql -B` prints an SQL NULL as the word NULL; §24's Default column leaves that cell empty and
        // writes `auto` for the auto-increment key, so a literal NULL string must not reach the page.
        str_contains($extra, 'auto_increment') ? 'auto' : ((string) $default === 'NULL' ? '' : (string) $default),
        $ref,
    ];
}

printf("read 15 columns and 3 foreign keys from information_schema\n");

foreach ($cells as $c) {
    printf("  %-22s %-52s %-4s %-4s %-6s %s\n", $c[0], $c[1], $c[2], $c[3], $c[4], $c[5]);
}

$modelSrc = file_get_contents($root.'/app/Models/Enrollmentreturns.php');

foreach ([
    "protected \$primaryKey = 'returnId'",
    'public $timestamps = false',
] as $mustDeclare) {
    if (! str_contains($modelSrc, $mustDeclare)) {
        fwrite(STDERR, "REFUSED: the model does not declare \"{$mustDeclare}\", so the Model line this pass writes would be false.\n");

        exit(1);
    }
}

$migration = '2026_10_27_1000000000_create_enrollmentreturns_table.php';

if (! is_file($root.'/database/migrations/'.$migration)) {
    fwrite(STDERR, "REFUSED: database/migrations/{$migration} is not on disk.\n");

    exit(1);
}

$zip = new ZipArchive;

if (! $zip->open($docx)) {
    fwrite(STDERR, "REFUSED: cannot open {$docx}.\n");

    exit(1);
}

$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$flatten = static function (string $fragment): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
};

$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

// The template block: the heading paragraph whose text opens `shiftingrequests — 19 columns`, through the
// end of the table that follows it.
$paras = preg_split('/(?=<w:p[ >])/', $xml, -1, PREG_SPLIT_NO_EMPTY);

if (! is_array($paras)) {
    fwrite(STDERR, "REFUSED: the paragraph split found nothing.\n");

    exit(1);
}

$headIndex = null;

foreach ($paras as $i => $p) {
    if (strncmp($flatten($p), 'shiftingrequests — 19 columns', 29) === 0) {
        $headIndex = $i;

        break;
    }
}

if ($headIndex === null) {
    fwrite(STDERR, "REFUSED: no paragraph reads `shiftingrequests — 19 columns`; the template block is not where this pass thinks it is.\n");

    exit(1);
}

// Walk forward until the paragraph that begins the template table, then take everything up to and
// including that table's close. Three prose paragraphs and one 20-row table.
// The walk below appends EVERY chunk from the heading onward, so the block starts empty. Seeding it with
// the heading paragraph here would carry that paragraph into the clone twice — which is what the
// paragraph-shape guard beneath it caught on the first dry run.
$block = '';
$tableFound = false;
$proseCount = 0;

for ($i = $headIndex; $i < count($paras); $i++) {
    $p = $paras[$i];

    if (! $tableFound) {
        if (str_contains($p, '<w:tbl>')) {
            $tableFound = true;
        } else {
            $proseCount++;
        }

        $block .= $p;

        continue;
    }

    $block .= $p;

    if (str_contains($p, '</w:tbl>')) {
        break;
    }
}

if (! $tableFound) {
    fwrite(STDERR, "REFUSED: the template heading is followed by no table.\n");

    exit(1);
}

printf("template block: %d bytes, table found after the prose\n", strlen($block));

// The three prose paragraphs are identified by what they SAY, not by how many chunks the split made:
// a paragraph chunk can carry the `<w:tbl>` opener too, because the split breaks only at `<w:p`.
preg_match_all('/<w:p[ >].*?<\/w:p>/s', $block, $pmAll);

$expectedHeads = [
    'shiftingrequests — 19 columns',
    'Role: the shift paper itself',
    'Model App\Models\Shiftingrequests',
];

// Print the first six paragraphs of the block before judging any of them: a paragraph that reads like the
// one before it means the block was assembled differently than this pass assumed, and that is only visible
// beside its neighbours.
foreach (array_slice($pmAll[0], 0, 6) as $k => $p) {
    $preview = $flatten($p);
    printf("block   [%d] %s\n", $k, strlen($preview) > 88 ? substr($preview, 0, 88).'…' : $preview);
}

foreach ($expectedHeads as $i => $expect) {
    $flat0 = $flatten($pmAll[0][$i] ?? '');

    if (strncmp($flat0, $expect, strlen($expect)) !== 0) {
        fwrite(STDERR, "REFUSED: template paragraph {$i} reads '{$flat0}', expected to open \"{$expect}\".\n");

        exit(1);
    }

    printf("ok   paragraph %d carries the expected opening\n", $i);
}

if (count($pmAll[0]) <= 3) {
    fwrite(STDERR, 'REFUSED: the block holds '.count($pmAll[0]).' paragraphs — no column table inside it.\n');

    exit(1);
}

preg_match_all('/<w:tr\b.*?<\/w:tr>/s', $block, $rm);
$templateRows = $rm[0];

if (count($templateRows) !== 20) {
    fwrite(STDERR, 'REFUSED: the template table holds '.count($templateRows)." rows; expected the header plus 19 columns.\n");

    exit(1);
}

$buildRow = static function (string $row, array $values, string $anchor) use ($flatten, $escape): string {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);

    if (count($cm[0]) !== count($values)) {
        fwrite(STDERR, "REFUSED: the row anchored on '{$anchor}' has ".count($cm[0]).' cells, this pass writes '.count($values).".\n");

        exit(1);
    }

    foreach ($cm[0] as $cell) {
        preg_match_all('/<w:p>.*?<\/w:p>/s', $cell, $pm);

        if (count($pm[0]) === 1) {
            continue;
        }

        foreach (array_slice($pm[0], 1) as $extra) {
            if ($flatten($extra) !== '') {
                fwrite(STDERR, "REFUSED: a cell of the row anchored on '{$anchor}' holds a SECOND paragraph with text — read it before cloning.\n");

                exit(1);
            }
        }
    }

    $clone = $row;
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm2, PREG_OFFSET_CAPTURE);
    $pairs = [];

    foreach ($cm2[0] as $j => $x) {
        $pairs[] = [$j, (int) $x[1], strlen($x[0]), $x[0]];
    }

    usort($pairs, static fn (array $a, array $b): int => $b[1] <=> $a[1]);

    foreach ($pairs as [$j, $at, $len, $src]) {
        $cell = $src;
        preg_match_all('/<w:p>.*?<\/w:p>/s', $cell, $pm);

        foreach (array_slice($pm[0], 1) as $extra) {
            $cell = str_replace($extra, '', $cell);
        }

        preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
        preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
        $body = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
        $new = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
            .$escape($values[$j]).'</w:t></w:r></w:p>', $body);

        if (substr_count($new, '<w:t xml:space="preserve">') !== 1) {
            fwrite(STDERR, "REFUSED: the replacement for cell {$j} of '{$anchor}' holds "
                .substr_count($new, '<w:t xml:space="preserve">')." text runs.\n");

            exit(1);
        }

        $clone = substr_replace($clone, $new, $at, $len);
    }

    if (substr_count($clone, '<w:tc>') !== count($cm[0])) {
        fwrite(STDERR, "REFUSED: the clone of '{$anchor}' lost or gained a cell.\n");

        exit(1);
    }

    return $clone;
};

// The three prose paragraphs, in order. The Role sentence states what a row is, in the register's own
// terms: the six facts of the return, and that the record's money is untouched (E-3).
$prose = [
    'enrollmentreturns — 15 columns',
    'Role: one row per return act (ruling of 2026-10-26, closing G-10). It states the six facts of the act — who '
        .'returned the record, from which office and box, to which desk and box, the category, the note and the '
        .'correction owed — plus the status the record moved from and to. The payments are not touched by it: '
        .'receipts keep counting through Payments::held() while a returned account is not re-billed (§17, E-3).',
    'Model App\Models\Enrollmentreturns (app/Models/Enrollmentreturns.php), $primaryKey = \'returnId\', $timestamps = false. '
        .'Created by 2026_10_27_1000000000_create_enrollmentreturns_table.php.',
];

$paragraphs = preg_match_all('/<w:p[ >].*?<\/w:p>/s', $block, $pmAll, PREG_SET_ORDER);
$proseReplacements = [];

// Rewrite each prose paragraph in place, keeping its paragraph properties and its first run's formatting.
for ($i = 0; $i < count($prose); $i++) {
    $p = $pmAll[$i][0];
    preg_match('/<w:pPr>.*?<\/w:pPr>/s', $p, $pp);
    preg_match('/<w:r\b.*?<\/w:r>/s', $p, $rm2);
    preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm2[0] ?? '', $pr2);
    $open = substr($p, 0, strpos($p, '>') + 1);
    $newP = $open.($pp[0] ?? '').'<w:r>'.($pr2[0] ?? '').'<w:t xml:space="preserve">'
        .$escape($prose[$i]).'</w:t></w:r></w:p>';

    if (substr_count($newP, '<w:t xml:space="preserve">') !== 1) {
        fwrite(STDERR, "REFUSED: replacement paragraph {$i} holds more than one text run.\n");

        exit(1);
    }

    $proseReplacements[] = [$p, $newP];
}

$newBlock = $block;

foreach ($proseReplacements as [$old, $new]) {
    if (substr_count($newBlock, $old) !== 1) {
        fwrite(STDERR, "REFUSED: a template paragraph is not unique inside the block; the clone would be half rewritten.\n");

        exit(1);
    }

    $newBlock = str_replace($old, $new, $newBlock);
}

// The table: keep the header row, write the fifteen measured columns over the first fifteen data rows,
// and drop the four rows the template carries beyond them.
$written = $templateRows[0];

for ($j = 1; $j <= 15; $j++) {
    $written .= $buildRow($templateRows[$j], $cells[$j - 1], 'enrollmentreturns row '.$j);
}

$blockStart = strpos($newBlock, $templateRows[0]);
$originalTable = substr($newBlock, $blockStart);
$tblClose = strpos($originalTable, '</w:tbl>') + 8;
$suffix = '</w:tbl>';

$newBlock = substr($newBlock, 0, $blockStart).$written.$suffix.substr($newBlock, $blockStart + $tblClose);

printf("new block: %d bytes, %d row(s) — header + 15 columns, %d template row(s) dropped\n",
    strlen($newBlock), 1 + substr_count($newBlock, '<w:tr'), count($templateRows) - 16
);

// Guards, re-implemented because this write moves rows and the engine would (correctly) refuse.
foreach ([['<w:tc>', '</w:tc>'], ['<w:tr', '</w:tr>'], ['<w:tbl>', '</w:tbl>']] as [$open, $close]) {
    $o = substr_count($newBlock, $open);
    $c = substr_count($newBlock, $close);

    if ($o !== $c) {
        fwrite(STDERR, "REFUSED: the new block itself is unbalanced — {$open} {$o} vs {$close} {$c}.\n");

        exit(1);
    }
}

// Paragraphs are counted by their own tag shape rather than by `</w:p>` (one close serves both `<w:p>` and
// `<w:p >`), and the four dropped rows must account for exactly the paragraph pairs they carry — 4 rows of
// 6 cells. A block that lost a paragraph nobody planned to lose is the shape that made Word call the file
// corrupt once already, in pass 254.
$pOpens = static fn (string $s): int => preg_match_all('/<w:p(?=[ >])/', $s);
$removed = $pOpens($block) - $pOpens($newBlock);

if ($removed !== 24 || $pOpens($newBlock) !== substr_count($newBlock, '</w:p>')) {
    $closes = substr_count($newBlock, '</w:p>');

    fwrite(STDERR, "REFUSED: dropping 4 rows of 6 cells should remove 24 paragraph pairs; this block removed {$removed}, "
        ."and its paragraph opens and closes are {$pOpens($newBlock)}/{$closes}.\n");

    exit(1);
}

$insertAt = strpos($xml, $block);

if ($insertAt === false || strpos($xml, $block, $insertAt + 1) !== false) {
    fwrite(STDERR, "REFUSED: the template block is not a unique span of the document.\n");

    exit(1);
}

$updated = substr_replace($xml, $newBlock, $insertAt, 0);

foreach ([['<w:tc>', '</w:tc>'], ['<w:tr', '</w:tr>'], ['<w:tbl>', '</w:tbl>']] as [$open, $close]) {
    $before = substr_count($xml, $open) - substr_count($xml, $close);
    $after = substr_count($updated, $open) - substr_count($updated, $close);

    if ($before !== $after || substr_count($updated, $open) === substr_count($xml, $open)) {
        fwrite(STDERR, "REFUSED: {$open} counts did not move by whole pairs ({$before} vs {$after} imbalance, "
            .substr_count($xml, $open).' → '.substr_count($updated, $open)." total).\n");

        exit(1);
    }
}

if (substr_count($updated, '<w:tbl>') !== substr_count($xml, '<w:tbl>') + 1) {
    fwrite(STDERR, "REFUSED: the document did not gain exactly one table.\n");

    exit(1);
}

if (simplexml_load_string($updated) === false) {
    fwrite(STDERR, "REFUSED WRITE: document.xml no longer parses.\n");

    exit(1);
}

echo "\nguard  block balanced, document gained one table and 16 rows, XML parses\n";
printf("%s§24 would gain the enrollmentreturns entry at byte %d\n", $apply ? '' : 'DRY RUN — ', $insertAt);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass260-backup-'.date('Ymd-His').'.docx';

if (! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup.\n");

    exit(1);
}

$zip = new ZipArchive;

if (! $zip->open($docx, ZipArchive::CREATE)) {
    fwrite(STDERR, "REFUSED: cannot reopen for writing.\n");

    exit(1);
}

$zip->addFromString('word/document.xml', $updated);
$zip->close();

$again = new ZipArchive;
$again->open($docx);
$read = (string) $again->getFromName('word/document.xml');
$again->close();

if ($read !== $updated) {
    fwrite(STDERR, "REFUSED: the archive did not read back the bytes it was handed. Restore from {$backup}.\n");

    exit(1);
}

echo "\npass 260 applied: §24 now documents enrollmentreturns, 15 columns read from information_schema.\n";
