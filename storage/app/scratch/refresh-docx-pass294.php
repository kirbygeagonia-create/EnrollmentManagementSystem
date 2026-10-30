<?php

/**
 * Pass 294: §1.1 records the lane-69 inventory finding — one open item was going unreported.
 *
 * Cloned from the newest §1.1 row so the new row inherits that table's two-cell structure, then written by cell
 * offset with the same read-back guards pass 292 used: one run per cell, the flattened cell must equal the text
 * handed to it, container counts balance, document.xml parses, and the archive must read back byte-for-byte.
 *
 * No register row changes, so no §28/§29 count moves. The wording avoids angle brackets because a cell's text is
 * read as prose by the offices that use this document.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass294.php [--apply]
 */

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', array_slice($argv, 1), true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unknown argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

$zip = new ZipArchive;
if (! $zip->open($docx)) {
    fwrite(STDERR, "REFUSED: cannot open the docx.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$flatten = static function (string $fragment): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
};
$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);
$cellsOf = static function (string $row): array {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);

    return $cm[0];
};
$rowsStartingWith = static function (string $xml, string $prefix) use ($flatten, $cellsOf): array {
    $hits = [];
    $at = 0;
    while (($start = strpos($xml, '<w:tr', $at)) !== false) {
        $end = strpos($xml, '</w:tr>', $start);
        if ($end === false) {
            break;
        }
        $end += 7;
        $row = substr($xml, $start, $end - $start);
        $text = implode(' ', array_map($flatten, $cellsOf($row)));
        if (strncmp($text, $prefix, strlen($prefix)) === 0) {
            $hits[] = [$start, $end, $row];
        }
        $at = $end;
    }

    return $hits;
};
$writeCell = static function (string $row, int $index, string $text) use ($flatten, $escape, $cellsOf): string {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm, PREG_OFFSET_CAPTURE);
    if (! isset($cm[0][$index])) {
        fwrite(STDERR, "REFUSED: the row has no cell {$index}.\n");

        exit(1);
    }
    $at = (int) $cm[0][$index][1];
    $target = strlen($cm[0][$index][0]);
    $cell = $cm[0][$index][0];
    preg_match_all('/<w:p>.*?<\/w:p>/s', $cell, $pm);
    foreach (array_slice($pm[0], 1) as $extra) {
        if ($flatten($extra) !== '') {
            fwrite(STDERR, "REFUSED: cell {$index} holds a second paragraph with text.\n");

            exit(1);
        }
        $cell = str_replace($extra, '', $cell);
    }
    preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
    preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
    $body = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
    $new = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
        .$escape($text).'</w:t></w:r></w:p>', $body);
    if (substr_count($new, '<w:t xml:space="preserve">') !== 1) {
        fwrite(STDERR, "REFUSED: cell {$index} would hold more than one text run.\n");

        exit(1);
    }
    $out = substr_replace($row, $new, $at, $target);
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $out, $check);
    if ($flatten($check[0][$index]) !== $text) {
        fwrite(STDERR, "REFUSED: cell {$index} reads [".mb_substr($flatten($check[0][$index]), 0, 80)."].\n");

        exit(1);
    }

    return $out;
};

$anchor = '2026-10-29 The school’s day on the write side';
$tpl = $rowsStartingWith($xml, $anchor);
if (count($tpl) !== 1) {
    fwrite(STDERR, 'REFUSED: the lane-87 row matched '.count($tpl)." rows, expected 1.\n");

    exit(1);
}
[$rStart, $rEnd, $template] = $tpl[0];
if (count($cellsOf($template)) !== 2) {
    fwrite(STDERR, 'REFUSED: the template row has '.count($cellsOf($template))." cells, and this pass writes two.\n");

    exit(1);
}

$mine = '2026-10-29 What is left, measured twice';
if ($rowsStartingWith($xml, $mine) !== []) {
    fwrite(STDERR, "REFUSED: this pass's own row is already in the document.\n");

    exit(1);
}

$record = 'The instrument that answers "what is left" was under-reporting by one item, and the table row it blamed '
    .'was innocent. §28.3 G-41 has been open since the sweep raised it and is item 6 on the 2026-10-29 sheet, yet '
    .'`lane69-open-inventory.php` left it out of its OPEN list: the tool cancelled a whole row whenever a closing '
    .'word appeared anywhere inside it, and that row’s verdict cell begins `Open 2026-10-29.` while its own sentence '
    .'later uses the word “answered”. `lane61-open-claims.php`, which reads the verb the cell LEADS with, had named it '
    .'all along — two tools answering the same question disagreed, and only one of them had been read back. The fix is '
    .'that a verdict is the leading verb of the verdict cell, never a word elsewhere in the row; a cell that leads '
    .'`Closed` and later says “Open” stays closed, and §28.5’s rows, which lead with the question text, are still '
    .'reported. The same row had been printed as UNRESOLVED, “four cells against a six-cell header”, and this file had '
    .'recorded that as a live document defect. It is not one. Read out of `document.xml` the row holds six cells, '
    .'exactly like its six siblings; what the tool was counting was its own reading of the flat dump, which loses '
    .'information in two ways: `dump-docx-flat.php` joins cells with a spaced pipe while the inventory split on a bare '
    .'pipe, so every `enum(provisional|official)` cut itself in two, and the dumper also drops EMPTY cells before '
    .'joining, so no separator can restore a column position. Cell counts are now taken from the archive, the row '
    .'located by its own text with whitespace and pipes stripped from both sides, and 377 rows needed that tiebreak — '
    .'among them every §24 row whose Key or Reference column is empty. Measured 2026-10-29 and deliberately dated: 6 '
    .'OPEN rows (G-35, G-36, G-37, G-38, G-40, G-41), 4 labels, 0 unresolved, and the two readers now name the same '
    .'ids. The harness was re-cut in the same batch, because its control had been a remembered tally — “0 OPEN, 2 '
    .'LABELS” — which was true the day it was written and reported a correct tool as broken the moment §28.3 held open '
    .'rows. It now asserts a relation instead: the ids the inventory reports must equal the ids lane 61 names, and '
    .'every planted leg is judged as a delta against the control measured moments earlier (13 legs, 0 failures). Two '
    .'defects in the new code came out of running it, both already named in §28: the pipe-stripping was applied to only '
    .'one side, so an enum row could never be found and the repair looked inert rather than fired, and the counter that '
    .'gates the tiebreak was initialised to an empty array, which compares greater than zero in PHP 8 — caught by the '
    .'leg that requires `--no-archive` to print its own refusal. Nothing in the prompt’s answer changes: G-41 was '
    .'already open and already put to the school. What changed is that the instrument a reader trusts for the question '
    .'"what is left" now agrees with the register it reads.';

$row = $writeCell($template, 0, $mine);
$row = $writeCell($row, 1, $record);
if ($row === $template) {
    fwrite(STDERR, "REFUSED: the cloned row is byte-identical to its template.\n");

    exit(1);
}
$xml = substr_replace($xml, $row, $rEnd, 0);

foreach ([['<w:p', '</w:p>'], ['<w:t', '</w:t>'], ['<w:tc', '</w:tc>'], ['<w:tr', '</w:tr>'], ['<w:tbl', '</w:tbl>'], ['<w:r', '</w:r>']] as [$o, $c]) {
    $a = preg_match_all('/'.preg_quote($o, '/').'(?=[\s>])/', $xml);
    $b = substr_count($xml, $c);
    if ($a !== $b) {
        fwrite(STDERR, "REFUSED: unbalanced {$o} ({$a} against {$b}).\n");

        exit(1);
    }
}
if (simplexml_load_string($xml) === false) {
    fwrite(STDERR, "REFUSED WRITE: document.xml no longer parses.\n");

    exit(1);
}
foreach ([$mine, 'a verdict is the leading verb of the verdict cell', 'now agrees with the register it reads'] as $want) {
    if (! str_contains($xml, $want)) {
        // The third phrase is the closing clause; check the fragment the writer can actually reach.
        $needle = str_contains($record, $want) ? $want : mb_substr($want, 0, 24);
        if (! str_contains($xml, $needle)) {
            fwrite(STDERR, "REFUSED: the edited document does not contain [{$needle}].\n");

            exit(1);
        }
    }
}

printf("%s: §1.1 records the lane-69 finding (%d bytes of record)\n", $apply ? 'APPLY' : 'DRY RUN', strlen($record));
if (! $apply) {
    echo "DRY RUN — nothing written.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass294-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: no backup at {$backup}.\n");

    exit(1);
}
$w = new ZipArchive;
if ($w->open($docx, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "REFUSED: cannot reopen for writing. Restore from {$backup}.\n");

    exit(1);
}
$w->addFromString('word/document.xml', $xml);
$w->close();

$again = new ZipArchive;
$again->open($docx);
$read = (string) $again->getFromName('word/document.xml');
$again->close();
if ($read !== $xml) {
    fwrite(STDERR, "REFUSED: the archive did not read back its bytes. Restore from {$backup}.\n");

    exit(1);
}

echo "\npass 294 applied: §1.1 carries the inventory finding.\n";
