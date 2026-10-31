<?php

/**
 * Pass 324b: §1.1 records the eighth sortable table, the ID desk validation queue.
 *
 * Cloned from the seventh-table row that pass 321b wrote so the new row inherits that table's two-cell structure, then written by cell
 * offset with the guards pass 294 established: one run per cell, the flattened cell must equal the text handed to
 * it, container counts balance, document.xml parses, and the archive must read back byte-for-byte.
 *
 * No register row changes, so no §28/§29 count moves — the finding lives in G-40's own cell (pass 321) and this row
 * is the date-stamped record of the act. No angle brackets, and no citation-shaped token: a `File.php:NN` form here
 * would be read as a live pointer by the citation gate.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass324b.php [--apply]
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

$anchor = '2026-10-30 The eighth sortable table, and the tab that chooses which set the order arranges';
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

$mine = '2026-10-30 A ruled reversal: an ENUM column may be sorted, and the test asks the server which order';
if ($rowsStartingWith($xml, $mine) !== []) {
    fwrite(STDERR, "REFUSED: this pass's own row is already in the document.\n");

    exit(1);
}

$record = 'The owner ruled on the sorting of ENUM columns, and the ruling reversed a rule this register had'
    .'carried since the clinic queue joined the day before. An ENUM column MAY be given a header: the'
    .'order the school’s server applies is the declaration order the school itself wrote into the'
    .'migration, which for an enrollment status is the pipeline itself — pending, evaluated,'
    .'assessed, paid — and for a student type is the order the registrar reads. What the ruling'
    .'moves is the test. The suite compiles the same enum() declaration into a varchar with a check'
    .'and sorts the stored word, so the expected sequence is now read from the connection by one'
    .'shared helper, Tests\\Support\\EnumColumnOrder, which refuses a column that names no enum on the'
    .'mysql driver rather than guessing; four desk screens ask it, and a second copy of the parse'
    .'would be the two-answers-for-one-word defect this document has already removed for year levels,'
    .'status tones and citation attribution. The clinic and ID queues therefore GAIN the Student Type'
    .'and Standing headers they were built without, and each gained a case that asserts the sequence'
    .'this connection answers. Two columns stay withheld, for a reason the ruling does not touch:'
    .'Clinic Status is painted from whether a record exists on the row and ID Status from the status'
    .'of its FIRST request, so neither is a column an order can be asked of — ordering ID Status'
    .'would mean deciding what two requests on one enrollment amount to, which the school has never'
    .'been asked. The same sweep closed an older gap: the Registrar’s Status header had been offered'
    .'since the first wiring and answered by no case at all, and now has one, over a fixture that'
    .'interleaves Paid and Assessed so neither id order satisfies it. Both mutant harnesses had to be'
    .'re-cut the same day rather than left running, because each carried a leg that PLANTED an offered'
    .'ENUM key as the defect — under the ruling that leg asks a test to fail on correct code — so'
    .'it now plants an invented key instead, and eleven and thirteen legs kill again. The two §1.1'
    .'rows written earlier today state the old rule in their own words and are left as written: they'
    .'are records of the day they were read, and this row is the day the ruling changed what the'
    .'screen does.';

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
foreach ([$mine, 'two-answers-for-one-word defect', 'plants an invented key instead'] as $want) {
    if (! str_contains($xml, $want)) {
        fwrite(STDERR, "REFUSED: the edited document does not contain [{$want}].\n");

        exit(1);
    }
}
$before = substr_count($xml, '<w:tr');
printf("%s: §1.1 gains the ENUM-ruling row (%d bytes of record, %d table rows)\n", $apply ? 'APPLY' : 'DRY RUN', strlen($record), $before);
if (! $apply) {
    echo "DRY RUN — nothing written.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass324b-backup-'.date('Ymd-His').'.docx';
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

echo "\npass 324b applied: §1.1 records the ruling and the reversal it caused.\n";
