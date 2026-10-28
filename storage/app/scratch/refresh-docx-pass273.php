<?php

/**
 * Pass 273: §1.1 records the queue drain, in its own table.
 *
 * Rows 25, 26, 27 and 28 each carry one completed unit of this issue's work — draft intake, the crediting
 * decision, examination adoption, the two prompt gaps. The citation drain is a fifth unit and the document has no
 * trace of it: §25.1's table, §23's print-action row and §28's E-13 row now state lines that were not in them an
 * hour ago, and a reader who wants to know *when* the print routes stopped naming the Blocking chapter has no row
 * to read. This pass clones the existing 2026-10-27 row and writes that record.
 *
 * The clone is built by row prefix and by cell offset (the pass-251 rules): a §1.1 cell can carry a trailing empty
 * paragraph, a cloned cell must be emptied of EVERY run, and a cell value written by `str_replace()` would land in
 * every identical copy. The new row is then required to flatten to exactly the two strings this pass supplies.
 *
 * The record spells its numbers as prose. A `File.php:NN` token written into a revision row becomes a citation the
 * sweeps judge (the pass-250 defect: a row that quotes a citation shape invents one), so nothing here carries the
 * colon form.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass273.php [--apply]
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

$rowText = static function (string $row) use ($flatten): string {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);

    return implode(' ', array_map($flatten, $cm[0]));
};

$date = '2026-10-27';
$record = '§25.1’s print-route table re-taken by reading, and the citation queue closed. Seventy-two UNBOUND '
    .'pointers were opened against the files they name, and thirty-two citation numbers moved. Fifteen sit in one '
    .'table: its Staff-route column named nine web.php lines that today are the Blocking and Accounting chapter '
    .'banners and five unrelated CRUD routes, and its page and PDF cells named the wrong RegistrarController action '
    .'six times over. The same three-number run was repeated in §25.1’s reachability paragraph and §28’s P-3 row, '
    .'and the two were deliberately given different lines — the paragraph claims every Inertia::render whose '
    .'component is named Print, the register row claims that each desk print writes its own log row, and those are '
    .'different lines of the same file. Pass 271 also took §23’s print-action row, §28’s E-13 row (whose second '
    .'claim, that subject status has its own proposed to confirmed to dropped line, had never carried a pointer at '
    .'all) and §6’s Registrar/Show cell. Every new number was asserted against the line it names before the write, '
    .'and the count of moves comes from diffing the citation multiset of two copies of this document rather than '
    .'from arithmetic, because the two totals first written down from memory were both wrong. The document’s human '
    .'citation queue now reports one hundred and eight of its one hundred and eight rows carrying a recorded read '
    .'with none stale, proved by all eleven legs of its harness.';

/** @return array<int, array{0:int,1:int,2:string}> every <w:tr> whose text starts with $prefix */
$rowsStartingWith = static function (string $xml, string $prefix) use ($rowText): array {
    $hits = [];
    $at = 0;
    while (($start = strpos($xml, '<w:tr', $at)) !== false) {
        $end = strpos($xml, '</w:tr>', $start);
        if ($end === false) {
            break;
        }
        $end += 7;
        $row = substr($xml, $start, $end - $start);
        if (strncmp($rowText($row), $prefix, strlen($prefix)) === 0) {
            $hits[] = [$start, $end, $row];
        }
        $at = $end;
    }

    return $hits;
};

// The template: the 2026-10-27 row this pass follows. Anchored on its first TWO cells so a date alone, which opens
// four rows in this table, is not mistaken for a unique target.
$anchor = $date.' Two prompt gaps built';
$templates = $rowsStartingWith($xml, $anchor);
if (count($templates) !== 1) {
    fwrite(STDERR, 'REFUSED: the anchor row ['.$anchor.'] matched '.count($templates)
        ." row(s), expected exactly 1.\n");

    exit(1);
}
[$tStart, $tEnd, $template] = $templates[0];

preg_match_all('/<w:tc>.*?<\/w:tc>/s', $template, $tc);
if (count($tc[0]) !== 2) {
    fwrite(STDERR, 'REFUSED: the template row holds '.count($tc[0])." cells; this pass writes a two-cell §1.1 row.\n");

    exit(1);
}
foreach ($tc[0] as $j => $cell) {
    preg_match_all('/<w:p>.*?<\/w:p>/s', $cell, $pm);
    if (count($pm[0]) > 1) {
        foreach (array_slice($pm[0], 1) as $extra) {
            if ($flatten($extra) !== '') {
                fwrite(STDERR, "REFUSED: cell {$j} of the template holds a SECOND paragraph with text — read it"
                    ." before cloning.\n");

                exit(1);
            }
        }
    }
}

// Idempotence, judged on the new row's own text rather than on a needle: a record row is unique by what it says.
if ($rowsStartingWith($xml, $date.' §25.1’s print-route table re-taken by reading') !== []) {
    fwrite(STDERR, "REFUSED: this pass's own row is already in the document.\n");

    exit(1);
}

$values = [$date, $record];
$clone = $template;
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $template, $cm2, PREG_OFFSET_CAPTURE);
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
        fwrite(STDERR, "REFUSED: the replacement for cell {$j} holds "
            .substr_count($new, '<w:t xml:space="preserve">')." text runs; the cloned cell must hold exactly 1.\n");

        exit(1);
    }
    $clone = substr_replace($clone, $new, $at, $len);
}

if (substr_count($clone, '<w:tc>') !== 2) {
    fwrite(STDERR, "REFUSED: the clone lost or gained a cell (".substr_count($clone, '<w:tc>')." against 2).\n");

    exit(1);
}

// Containment is not enough: the flattened clone must EQUAL the two values joined by the cell separator, which is
// the guard that catches a template cell whose later runs survived the write (pass 173's §1.1 clone).
$want = $date.' '.$record;
$got = $rowText($clone);
if ($got !== $want) {
    $at = similar_text($want, $got, $pct);
    fwrite(STDERR, "REFUSED: the cloned row flattens to ".strlen($got)." bytes (matching {$pct}% of what was"
        ." written, longest common span {$at} bytes), so a template run survived the cell write.\n");

    exit(1);
}

$updated = substr_replace($xml, $clone, $tEnd, 0);

if (substr_count($updated, $clone) !== 1) {
    fwrite(STDERR, "REFUSED: the new row does not occur exactly once after the insert.\n");

    exit(1);
}
if (strlen($updated) <= strlen($xml)) {
    fwrite(STDERR, 'REFUSED: inserting a row did not lengthen the document.'."\n");

    exit(1);
}

foreach ([['<w:p', '</w:p>'], ['<w:t', '</w:t>'], ['<w:tc', '</w:tc>'], ['<w:tr', '</w:tr>'], ['<w:tbl', '</w:tbl>']] as [$open, $close]) {
    $a = preg_match_all('/'.preg_quote($open, '/').'(?=[\s>])/', $updated);
    $b = substr_count($updated, $close);
    if ($a !== $b) {
        fwrite(STDERR, "REFUSED: the edited document is unbalanced in {$open} ({$a} against {$b}).\n");

        exit(1);
    }
}

if (simplexml_load_string($updated) === false) {
    fwrite(STDERR, "REFUSED WRITE: document.xml no longer parses.\n");

    exit(1);
}

printf("%s§1.1 gains its fifth row for this issue (%d bytes of record)\n", $apply ? '' : 'DRY RUN — ', strlen($record));

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass273-backup-'.date('Ymd-His').'.docx';

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

echo "\npass 273 applied: §1.1 records the print-route re-take and the closed citation queue.\n";
