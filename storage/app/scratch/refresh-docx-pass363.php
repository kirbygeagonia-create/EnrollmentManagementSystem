<?php

/**
 * Docx pass 363 — §28.3 gains G-53, the desk search that widened the filter set beside it.
 *
 * The defect shipped fixed and pinned in the same batch as the fifteenth sortable table, and AGENTS.md records that
 * a register row is what makes a found defect finish existing: a fix with no row is a claim only in the day's record,
 * and the day's record is a sentence nobody goes looking in when they ask what the document knows about desk filters.
 *
 * The recipe is the one pass 251b established and pass 308 repeated: the engine refuses any pass that moves a
 * `<w:tr>`, so the row is cloned on the in-memory bytes from a template whose first cell is exactly `G-52`, every run
 * of every cell is emptied and re-written by OFFSET (a cloned multi-run cell otherwise carries the template's tail),
 * the ids are read back and asserted in order, and a failed post-guard restores this pass's own backup rather than
 * printing advice.
 *
 * The two places that COUNT the register — §28.1's `G-1 … G-52` index cell and §29.4's `69` for the G/D/U/X/N family —
 * are a separate pass, because `docxApply()` re-reads the archive from disk and would discard what this pass wrote.
 * Pass 363b does them.
 *
 * Every figure in the new row was measured the day it was typed: eleven of twenty-one accounts answering a request
 * that named one office holding three, and thirteen chained desk searches of which twelve are inside a `whereHas`.
 * No cell states a case count or an assertion total — `lane58-suite-claims.php` binds those to the runner's junit.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass363.php [--apply]
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
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open {$docx} (ZipArchive answers an error CODE for a bad file, which is truthy).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

if (str_contains($xml, 'A desk search widened the filter set written beside it')) {
    fwrite(STDERR, "REFUSED: pass 363's own G-53 row is already in the document.\n");

    exit(1);
}

$flatten = static function (string $fragment): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
};

$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

/** @return list<array{0:int,1:int}> every `<w:tr>` whose first cell is EXACTLY `$id` */
$rowsForId = static function (string $xml, string $id) use ($flatten): array {
    $hits = [];
    $at = 0;
    while (($start = strpos($xml, '<w:tr', $at)) !== false) {
        $end = strpos($xml, '</w:tr>', $start);
        if ($end === false) {
            break;
        }
        $end += 7;
        preg_match_all('/<w:tc>.*?<\/w:tc>/s', substr($xml, $start, $end - $start), $cm);
        if (($flatten($cm[0][0] ?? '') ?? '') === $id) {
            $hits[] = [$start, $end];
        }
        $at = $end;
    }

    return $hits;
};

/** The G/D/U/X/N ids in document order — order is a claim, so it is read, never assumed. */
$familyIds = static function (string $xml) use ($flatten): array {
    $at = 0;
    $seq = [];
    while (($start = strpos($xml, '<w:tr', $at)) !== false) {
        $end = strpos($xml, '</w:tr>', $start);
        if ($end === false) {
            break;
        }
        $end += 7;
        preg_match_all('/<w:tc>.*?<\/w:tc>/s', substr($xml, $start, $end - $start), $cm);
        $first = $flatten($cm[0][0] ?? '');
        if (preg_match('/^(G|D|U|X|N)-\d+$/', $first) === 1) {
            $seq[] = $first;
        }
        $at = $end;
    }

    return $seq;
};

/** Build one row from a template row, writing $values into its cells by offset. */
$buildRow = static function (string $row, array $values, string $anchor) use ($flatten, $escape): string {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);
    if (count($cm[0]) !== count($values)) {
        fwrite(STDERR, "REFUSED: the template row '{$anchor}' has ".count($cm[0])
            .' cells, this row writes '.count($values).".\n");

        exit(1);
    }
    foreach ($cm[0] as $cell) {
        preg_match_all('/<w:p>.*?<\/w:p>/s', $cell, $pm);
        foreach (array_slice($pm[0], 1) as $extra) {
            if ($flatten($extra) !== '') {
                fwrite(STDERR, "REFUSED: a cell of the template '{$anchor}' holds a SECOND paragraph with text — read it before cloning.\n");

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

    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $clone, $check);
    foreach ($check[0] as $j => $cell) {
        if ($flatten($cell) !== trim(preg_replace('/\s+/', ' ', $values[$j]))) {
            fwrite(STDERR, "REFUSED: cell {$j} of the new row does not read back as what was supplied.\n");

            exit(1);
        }
    }

    return $clone;
};

$row = [
    'G-53',
    'A desk search widened the filter set written beside it: the staff directory chained its own `orWhere` '
    .'alternatives on the same builder that carried the office and status filters, so a request naming one office '
    .'answered staff from every office that held a match.',
    'Closed 2026-10-31, found while wiring that screen’s order and measured on live `ems` before the parentheses '
    .'went in: office 1 for the term `Sta` answered eleven of the twenty-one accounts while that office holds three. '
    .'The search’s alternatives now sit inside a group of their own, so the filters set beside them survive. The '
    .'family was counted rather than assumed: thirteen desk searches chain `orWhere`, and twelve of them scope '
    .'their alternatives inside a `whereHas` subquery, where the OR belongs to the exists-clause and the filter '
    .'beside it is unaffected — this was the only one written on the outer builder. '
    .'`storage/app/scratch/lane126-probe-search-precedence.php` compiles both shapes, chooses its discriminating '
    .'pair from the rows the table actually holds, and prints the two SQL strings beside each other, so the claim is '
    .'a row difference and not a reading of the grammar. No school ruling is needed: the desk had set both controls '
    .'and asked for the narrower list both times.',
    'Nobody',
    'Prompt §14 — met wiring the fifteenth sortable table 2026-10-31',
];

$template = $rowsForId($xml, 'G-52');
if (count($template) !== 1) {
    fwrite(STDERR, 'REFUSED: the G-52 template row matched '.count($template).' rows, expected exactly 1'
        ." — the anchor is a row whose FIRST cell is exactly `G-52`.\n");

    exit(1);
}
[$tStart, $tEnd] = $template[0];
$templateRow = substr($xml, $tStart, $tEnd - $tStart);
$cellCount = preg_match_all('/<w:tc>.*?<\/w:tc>/s', $templateRow, $tmp);
printf("template: the G-52 row at byte %d..%d, %d cell(s)\n", $tStart, $tEnd, $cellCount);

$before = $familyIds($xml);
$gBefore = array_values(array_filter($before, static fn (string $i): bool => str_starts_with($i, 'G-')));
$gNums = array_map(static fn (string $s): int => (int) substr($s, 2), $gBefore);
printf("family rows before this pass: %d (expected 69); G ids %d..%d, %d of them\n",
    count($before), min($gNums), max($gNums), count($gBefore));
if (count($before) !== 69 || count($gBefore) !== 52) {
    fwrite(STDERR, "REFUSED: the register holds ".count($before)." family rows and ".count($gBefore)
        ." G rows, not the 69 and 52 this pass was cut against — re-count before cloning anything.\n");

    exit(1);
}
if (count($rowsForId($xml, 'G-53')) !== 0) {
    fwrite(STDERR, "REFUSED: a row whose first cell is 'G-53' is already in the document.\n");

    exit(1);
}

$clone = $buildRow($templateRow, $row, 'G-53');
$xml = substr_replace($xml, $clone, $tEnd, 0);
printf("ok   G-53 staged after G-52: %d cell(s), %d bytes\n", count($row), strlen($clone));

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass363-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup.\n");

    exit(1);
}

$zip = new ZipArchive;
if ($zip->open($docx, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "REFUSED: cannot reopen for writing.\n");

    exit(1);
}
$zip->addFromString('word/document.xml', $xml);
$zip->close();

$again = new ZipArchive;
if ($again->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot re-open the written archive.\n");

    exit(1);
}
$read = (string) $again->getFromName('word/document.xml');
$again->close();
if ($read !== $xml) {
    restore_and_bail($backup, $docx, 'the archive did not read back the bytes it was handed');
}

$dom = new DOMDocument;
libxml_use_internal_errors(true);
if (! $dom->loadXML($xml)) {
    $errs = libxml_get_errors();
    restore_and_bail($backup, $docx, 'document.xml no longer parses: '.trim($errs[0]->message));
}

$after = $familyIds($xml);
$gAfter = array_values(array_filter($after, static fn (string $i): bool => str_starts_with($i, 'G-')));
printf("family rows now: %d (expected 70); G ids now %d, ending %s\n", count($after), count($gAfter), implode(',', array_slice($gAfter, -3)));

if (count($after) !== 70 || count($gAfter) !== 53) {
    restore_and_bail($backup, $docx, 'the family count is not 70 rows / 53 G rows');
}

// Sequence is asserted, not admired: G-53 must sit between G-52 and D-1, each id once, in order.
$expect = [];
for ($i = 1; $i <= 53; $i++) {
    $expect[] = 'G-'.$i;
}
if ($gAfter !== $expect) {
    restore_and_bail($backup, $docx, 'the G ids are not G-1 … G-53 once each in order');
}
$pos = array_search('G-53', $after, true);
if ($after[$pos - 1] !== 'G-52' || $after[$pos + 1] !== 'D-1') {
    restore_and_bail($backup, $docx, 'G-53 did not land between G-52 and D-1');
}

echo 'backup: '.basename($backup)."\nwritten, re-read, parsed, counted, ordered.\n";

/**
 * A post-write guard that only prints leaves the document broken while the operator reads one line.
 */
function restore_and_bail(string $backup, string $docx, string $why): void
{
    if (! copy($backup, $docx)) {
        fwrite(STDERR, "REFUSED: {$why} — AND the restore failed. Restore ".basename($backup)." by hand.\n");

        exit(1);
    }
    fwrite(STDERR, "REFUSED: {$why} — restored the pass's own backup; nothing was left half-written.\n");

    exit(1);
}
