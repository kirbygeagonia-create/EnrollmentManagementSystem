<?php

/**
 * Pass 321b: §1.1 records the seventh sortable table.
 *
 * Cloned from the newest §1.1 row so the new row inherits that table's two-cell structure, then written by cell
 * offset with the guards pass 294 established: one run per cell, the flattened cell must equal the text handed to
 * it, container counts balance, document.xml parses, and the archive must read back byte-for-byte.
 *
 * No register row changes, so no §28/§29 count moves — the finding lives in G-40's own cell (pass 321) and this row
 * is the date-stamped record of the act. No angle brackets, and no citation-shaped token: a `File.php:NN` form here
 * would be read as a live pointer by the citation gate.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass321b.php [--apply]
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
if ($zip->open($docx) !== true) {
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

$anchor = '2026-10-30 Two censuses, two privacy holes, and a fix that made its own defect';
$tpl = $rowsStartingWith($xml, $anchor);
if (count($tpl) !== 1) {
    fwrite(STDERR, 'REFUSED: the row this pass clones matched '.count($tpl)." rows, expected 1.\n");

    exit(1);
}
[$rStart, $rEnd, $template] = $tpl[0];
if (count($cellsOf($template)) !== 2) {
    fwrite(STDERR, 'REFUSED: the template row has '.count($cellsOf($template))." cells, and this pass writes two.\n");

    exit(1);
}

$mine = '2026-10-30 The seventh sortable table, and a column withheld for its type';
if ($rowsStartingWith($xml, $mine) !== []) {
    fwrite(STDERR, "REFUSED: this pass's own row is already in the document.\n");

    exit(1);
}

$record = 'The clinic health queue became the seventh desk table that sorts on the server, through the same one '
    .'mechanism the Registrar queue started (§28.3 G-40), and it is the first screen where a column the desk can see '
    .'was deliberately not made sortable. Student Type and Standing both sit on the enrollment the row already reads, '
    .'so the usual reason to withhold a header — the query cannot reach it — does not apply. The reason is the column '
    .'type. An ENUM is ordered by MySQL at the index each value was declared with, while the test schema compiles the '
    .'same declaration into a text column with a check and orders the stored word, so a header over either column '
    .'would show the office one sequence and the suite another; the case that hid it from CI is already recorded in the '
    .'same register row. A header whose order depends on which machine answered is not an order the desk can be given, '
    .'and the vocabulary those two columns raise is the school’s question rather than a query’s. The screen therefore '
    .'offers four headers where six columns are visible, and the guard is a case that asserts the offered set, not the '
    .'comment beside the list. Two things this tranche also settled about the mechanism. The queue it orders is a '
    .'workflow population, not a status filter — the enrollments whose FIRST open box is the clinic, read through the '
    .'same call that paints the dashboard badge — so badge and list were one definition before the order arrived, and '
    .'the three summary tiles are still cloned before a surname join exists. And two of the eleven legs of '
    .'mutate-g40-clinic-sort-tests.php plant the JOIN rather than a column, because a surname ordered with no join is '
    .'not a wrong sequence but a list the desk would never have rendered; a harness that only plants wrong columns '
    .'would never notice a missing hop. What a browser could and could not show is stated rather than glossed: this '
    .'box’s demo data holds a single row waiting at the clinic box, so no click there can change a sequence — what was '
    .'read on the screen was the contract (three headers send the column and the direction, the second click reverses, '
    .'a filter submit carries the typed search and keeps the arrow), and the ordering itself is proved by a fixture '
    .'that writes four. One prose hazard was read and left alone: §1.1’s row of 2026-10-29 says thirty-four of the '
    .'thirty-six pages offer no order, which was true the day it was written. It is a dated record and stays as typed; '
    .'the living count lives in §28.3, which this pass moved to twenty-nine.';

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
foreach ([$mine, 'was deliberately not made sortable', 'a dated record and stays as typed'] as $want) {
    if (! str_contains($xml, $want)) {
        fwrite(STDERR, "REFUSED: the edited document does not contain [{$want}].\n");

        exit(1);
    }
}
$before = substr_count($xml, '<w:tr');
printf("%s: §1.1 gains the seventh-table row (%d bytes of record, %d table rows)\n", $apply ? 'APPLY' : 'DRY RUN', strlen($record), $before);
if (! $apply) {
    echo "DRY RUN — nothing written.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass321b-backup-'.date('Ymd-His').'.docx';
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

echo "\npass 321b applied: §1.1 records the seventh sortable table and the withheld ENUM column.\n";
