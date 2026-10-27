<?php

/**
 * §28.3 gains G-32, and the range statement that counts the family moves with it.
 *
 * The register's own rule is that a row you add moves every place that counts the register, so this pass
 * prints what it finds before it writes: the template row's cells, and §29.4's range row in full. If the
 * family table carries a numeric count as well as the range, the reading is here rather than a guess made
 * from memory of what such tables usually hold.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass268.php [--apply]
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

    return implode(' | ', array_map($flatten, $cm[0]));
};

preg_match_all('/<w:tr\b.*?<\/w:tr>/s', $xml, $rows, PREG_OFFSET_CAPTURE);

$template = null;
$range = null;
$rangeRows = [];

foreach ($rows[0] as [$row, $offset]) {
    $text = $rowText($row);

    if (strncmp($text, 'G-31 |', 6) === 0) {
        if ($template !== null) {
            fwrite(STDERR, "REFUSED: two register rows open with G-31.\n");

            exit(1);
        }

        $template = ['row' => $row, 'offset' => (int) $offset, 'text' => $text];
    }

    // A row that STATES the range has it as its first cell. The G-30 row quotes an older range —
    // "§28.1's identifier index still said the G family runs G-1 … G-8" — as the finding it records, and a
    // quoted figure is a record of the day it was true, not a live claim to move.
    if (strncmp($text, 'G-1 … G-', 10) === 0) {
        $rangeRows[] = $text;
    }

    if (strncmp($text, 'G-32 |', 6) === 0) {
        fwrite(STDERR, "REFUSED: §28.3 already carries a G-32 row.\n");

        exit(1);
    }
}

if ($template === null || $rangeRows === []) {
    fwrite(STDERR, 'REFUSED: template G-31 row found: '.($template === null ? 'no' : 'yes').'; range rows found: '.count($rangeRows).".\n");

    exit(1);
}

// More than one place states the family range — §28.1's index and §29.4's family table both do — and an
// earlier pass proved what happens when one of them is left behind. Every statement is printed, and every
// one must agree with the others before any of them moves.
printf("%d row(s) state the G range:\n", count($rangeRows));

foreach ($rangeRows as $n => $t) {
    printf("  %d| %s\n", $n + 1, strlen($t) > 190 ? substr($t, 0, 190).'…' : $t);
}

$tokens = [];

foreach ($rangeRows as $t) {
    if (preg_match_all('/G-1 … G-(\d+)/u', $t, $mm)) {
        foreach ($mm[1] as $d) {
            $tokens[$d] = ($tokens[$d] ?? 0) + 1;
        }
    }
}

// The keys are ints by the time they come out of `preg_match_all`, so the comparison is by key EXISTENCE —
// a strict `!== ['31']` against string literals refuses a reading that is perfectly agreed.
if (count($tokens) !== 1 || ! isset($tokens[31])) {
    fwrite(STDERR, 'REFUSED: the range statements disagree — they end at '.implode(' and ', array_keys($tokens))."; this pass moves a single known figure.\n");

    exit(1);
}

printf("the range is stated %d time(s), each ending at 31\n", $tokens[31]);

$range = $rangeRows[0];

$cells = [];
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $template['row'], $cm);
$cells = array_map($flatten, $cm[0]);

printf("template §28.3 row has %d cells:\n", count($cells));

foreach ($cells as $j => $c) {
    printf("  [%d] %s\n", $j, strlen($c) > 96 ? substr($c, 0, 96).'…' : $c);
}

printf("\nthe range row reads:\n  %s\n\n", strlen($range) > 220 ? substr($range, 0, 220).'…' : $range);

$values = [
    'G-32',
    'Three mechanism sentences about the enrollment lifecycle described a build the code had already left behind, and the record row written for the next batch then invented a section. '
        .'§7.1 said the `enrolled → dropped` edge had no call site while the Registrar had been taking it since the drop act was built; the return row said only a paid record may be '
        .'sent back while the machine allows every status but dropped; §27\'s E-4 paragraph said a returned record cannot get back to paid and cited a method this batch had retired. '
        .'The record row cited §7.9, a section this document does not have — it belongs to the development prompt — and cited §6.4, which this document does have and does not mean here.',
    'Every range in those sentences lands on a live line of the right file, so the bounds sweep, the meaning sweep and the §-reference sweep each agreed with them; a numbering collision '
        .'resolves as a match for any checker that only asks whether a §-number exists. What found the stale claims was reading a row\'s own sentence against the method it names, and '
        .'what found the invented section was the §-reference sweep running after the row existed.',
    'Closed 2026-10-27 by docx passes 258-267: each pointer re-taken against the line it names, the E-4 paragraph rewritten to the round trip the return test now proves end to end, §7.1 '
        .'given the missing `returnedToAdmission → pending` row with its measured call site, and the record row\'s pointers qualified with the word "prompt" so they name the book they '
        .'mean. The §-reference sweep\'s other-document cue list gained "prompt", which is what makes that qualification honoured rather than silently resolved.',
    '§7.1, §27, §1.1, §29.4; lane 37, 61, 70, 79',
];

if (count($values) !== count($cells)) {
    fwrite(STDERR, 'REFUSED: the §28.3 row holds '.count($cells).' cells and this pass writes '.count($values)."; read the cells above and match them.\n");

    exit(1);
}

$clone = $template['row'];
$pairs = [];

// Offsets, explicitly: the print above matched cells WITHOUT `PREG_OFFSET_CAPTURE` so each entry is a plain
// string, and reusing `$cm[0]` here made `$cell[0]` the first CHARACTER of the cell — the same defect pass
// 260c and pass 263 each met, and the reason the guard reported "0 text runs, body was: <".
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $template['row'], $cmOff, PREG_OFFSET_CAPTURE);

foreach ($cmOff[0] as $j => $cell) {
    $pairs[] = [$j, (int) $cell[1], strlen($cell[0]), $cell[0]];
}

usort($pairs, static fn (array $a, array $b): int => $b[1] <=> $a[1]);

foreach ($pairs as [$j, $at, $len, $src]) {
    $cell = $src;
    // Paragraphs are matched by either opening form: a cell whose SECOND paragraph is written
    // `<w:p w:rsidR=…>` is invisible to a `/<w:p>/` scan, and `str_replace` below then inserts a text run
    // into every close it finds — which is the "more than one text run" this guard caught.
    preg_match_all('/<w:p[ >].*?<\/w:p>/s', $cell, $pm);

    foreach (array_slice($pm[0], 1) as $extra) {
        if ($flatten($extra) !== '') {
            fwrite(STDERR, "REFUSED: cell {$j} of the template row holds a second paragraph with text.\n");

            exit(1);
        }

        $cell = str_replace($extra, '', $cell);
    }

    preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
    preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
    $body = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
    $new = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
        .$escape($values[$j]).'</w:t></w:r></w:p>', $body);

    $runCount = substr_count($new, '<w:t xml:space="preserve">');

    if ($runCount !== 1) {
        fwrite(STDERR, "REFUSED: cell {$j} of the clone holds {$runCount} text runs, expected 1 — body was: {$body}\n");

        exit(1);
    }

    $clone = substr_replace($clone, $new, $at, $len);
}

preg_match_all('/<w:tc>.*?<\/w:tc>/s', $clone, $check);
$written = array_map($flatten, $check[0]);

if ($written !== $values) {
    fwrite(STDERR, "REFUSED: a cloned cell is not the text supplied (cell 0: {$written[0]}, cell ".(count($written) - 1).': '.$written[count($written) - 1].").\n");

    exit(1);
}

printf("clone  §28.3 G-32 row, %d bytes, %d cells verified by flattened equality\n", strlen($clone), count($written));

$rangeNeedle = 'G-1 … G-31';
$rangeHits = substr_count($xml, $rangeNeedle);

if ($rangeHits < 1) {
    fwrite(STDERR, "REFUSED: the needle '{$rangeNeedle}' occurs {$rangeHits} time(s) in document.xml, though the rows say it is stated; the run is split and this pass cannot rewrite it as one needle.\n");

    exit(1);
}

printf("the range needle occurs %d time(s) in document.xml; all of them move together\n", $rangeHits);

// Insert the row, then rewrite the range — one pass over the bytes so a single write carries both.
$withRow = substr_replace($xml, $clone, $template['offset'] + strlen($template['row']), 0);
$final = str_replace($rangeNeedle, 'G-1 … G-32', $withRow);

foreach ([['<w:tc>', '</w:tc>'], ['<w:tr', '</w:tr>'], ['<w:tbl>', '</w:tbl>']] as [$open2, $close]) {
    if (substr_count($final, $open2) !== substr_count($final, $close)) {
        fwrite(STDERR, "REFUSED: the edited document is unbalanced in {$open2}.\n");

        exit(1);
    }
}

if (substr_count($final, '<w:tr') !== substr_count($xml, '<w:tr') + 1) {
    fwrite(STDERR, "REFUSED: the document did not gain exactly one row.\n");

    exit(1);
}

if (simplexml_load_string($final) === false) {
    fwrite(STDERR, "REFUSED WRITE: document.xml no longer parses.\n");

    exit(1);
}

printf("%s§28.3 would gain G-32 and §29.4's range would read G-1 … G-32\n", $apply ? '' : 'DRY RUN — ');

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass268-backup-'.date('Ymd-His').'.docx';

if (! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup.\n");

    exit(1);
}

$zip = new ZipArchive;

if (! $zip->open($docx, ZipArchive::CREATE)) {
    fwrite(STDERR, "REFUSED: cannot reopen for writing.\n");

    exit(1);
}

$zip->addFromString('word/document.xml', $final);
$zip->close();

$again = new ZipArchive;
$again->open($docx);
$read = (string) $again->getFromName('word/document.xml');
$again->close();

if ($read !== $final) {
    fwrite(STDERR, "REFUSED: the archive did not read back the bytes it was handed. Restore from {$backup}.\n");

    exit(1);
}

foreach (['G-1 … G-31' => 0, 'G-1 … G-32' => $rangeHits] as $needle => $expected) {
    if (substr_count($read, $needle) !== $expected) {
        fwrite(STDERR, "REFUSED (post-write): '{$needle}' occurs ".substr_count($read, $needle).", expected {$expected}.\n");

        exit(1);
    }

    printf("after %-16s %d\n", $needle, substr_count($read, $needle));
}

echo "\npass 268 applied: the register carries G-32 and the family range counts it.\n";
