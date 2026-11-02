<?php

/**
 * Pass 407 — the two school figures this repository cannot invent are put where the document asks school questions.
 *
 * §28.5 is the document's only call on somebody else's time, and it holds neither of these. The ADS guide's own
 * Problem 13 (Maximum Academic Load, ¶161-170) and Problem 14 (Faculty Teaching Load, ¶171-180) are both recorded
 * as "partially built": the units are summed, the assignments are stored, and the NUMBER that would refuse a load
 * is absent — because it is the school's to state. `lane92`-style coverage of the prompt cannot see that shape, and
 * §28.3's register has no row for a figure nobody chose, so until this pass the questions existed only in an ADS
 * deliverable and a dated §1.1 row, which is the gap-list-is-a-register lesson met from the other side.
 *
 * Rows are INSERTED on the archive bytes, not through `docxApply()`: the engine refuses any pass that moves a
 * `<w:tr>`, and a cloned row is a moved row. §29.4's count of this table is re-taken by a second pass, because the
 * engine re-reads the archive and would discard a row written in memory — the split that lane 61's cascade already
 * documents.
 *
 * The clone inherits every run of the template cell, so each cell is emptied of ALL its runs before its one text run
 * is written, and the read-back requires the flattened cell to equal the supplied text.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass407.php [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);

$apply = false;
foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
    if ($arg === '--apply') {
        $apply = true;
    } else {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");
        exit(1);
    }
}

$fail = static function (string $m): never {
    fwrite(STDERR, "REFUSED: {$m}\n");
    exit(1);
};

$docx = $root.'/Documentation/EMS Complete Documentation.docx';

$rows = [
    [
        'What is the maximum study load one student may carry in a term — in units, or in subjects, or both?',
        'The build adds units and refuses a proposal outside an elective group’s own min–max band, but no figure for a term ceiling exists '
            .'anywhere in app/: grepping minUnits, maxUnits, maxLoad and unitLimit answers nothing, so the proposal loop cannot refuse an '
            .'overloaded student and no screen warns one. The number is the school’s, and inventing a default would let the application '
            .'certify a rule the school never stated. ADS guide problem 13.',
        '§7.3; ADS problem 13',
    ],
    [
        'What is a faculty member’s teaching load limit — per term, per year, or none?',
        'Assignments are stored and a timetable collision is judged inside one term, so the record can ANSWER how many units one instructor '
            .'carries at any moment but nothing decides when that is too many. §20’s scale measurement counts the heaviest instructor today; '
            .'that is a reading, not a limit. The school has never stated the figure, so the guard is deliberately absent rather than guessed. '
            .'ADS guide problem 14.',
        '§7.9; ADS problem 14',
    ],
];

$flatten = static function (string $fragment): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
};
$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    $fail('the docx did not open.');
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
if ($xml === '') {
    $fail('word/document.xml read empty.');
}

foreach ($rows as $r) {
    if (substr_count($xml, $escape($r[0])) !== 0) {
        $fail('a question this pass writes is already in the document — the pass has run, or the row was added by hand.');
    }
    foreach ($r as $cell) {
        if (preg_match('/[<>]/', $cell) === 1) {
            $fail('a cell holds a raw angle bracket.');
        }
    }
}

// Locate §28.5's table by its heading text, then take the table that follows it.
$headingPos = strpos($xml, 'Consolidated list of decisions to put to the school');
if ($headingPos === false) {
    $fail('§28.5’s heading was not found, so its table cannot be located without guessing.');
}
$tblStart = strpos($xml, '<w:tbl>', $headingPos);
$tblEnd = strpos($xml, '</w:tbl>', $tblStart);
if ($tblStart === false || $tblEnd === false) {
    $fail('no table follows §28.5’s heading.');
}
$table = substr($xml, $tblStart, $tblEnd + 8 - $tblStart);

preg_match_all('/<w:tr\b.*?<\/w:tr>/s', $table, $rm);
$rowCount = count($rm[0]);
if ($rowCount !== 27) {
    $fail("§28.5's table holds {$rowCount} rows; this pass measured 27 (one header plus 26 questions) when it was written and refuses to clone a table it has not re-read.");
}
$template = $rm[0][$rowCount - 1];
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $template, $cm);
if (count($cm[0]) !== 3) {
    $fail('the last row of §28.5 holds '.count($cm[0]).' cells, expected 3 — read the table before cloning it.');
}
foreach ($cm[0] as $cell) {
    preg_match_all('/<w:p>.*?<\/w:p>/s', $cell, $pm);
    foreach (array_slice($pm[0], 1) as $extra) {
        if ($flatten($extra) !== '') {
            $fail('a template cell holds a SECOND paragraph with text — a clone would drop it silently.');
        }
    }
}

$clones = '';
foreach ($rows as $ri => $values) {
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
        preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rmm);
        preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rmm[0] ?? '', $pr);
        $cellXml = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
        $new = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
            .$escape($values[$j]).'</w:t></w:r></w:p>', $cellXml);
        if (substr_count($new, '<w:t xml:space="preserve">') !== 1) {
            $fail("row {$ri} cell {$j} holds ".substr_count($new, '<w:t xml:space="preserve">')." text runs after the write.");
        }
        $clone = substr_replace($clone, $new, $at, $len);
    }
    if (substr_count($clone, '<w:tc>') !== 3) {
        $fail("row {$ri} clone lost or gained a cell.");
    }
    $flat = array_map($flatten, array_slice((preg_match_all('/<w:tc>.*?<\/w:tc>/s', $clone, $back) ? $back[0] : []), 0, 3));
    foreach ($values as $j => $v) {
        if ($flat[$j] !== $v) {
            $fail("row {$ri} cell {$j} does not read back as written: [".mb_substr($flat[$j], 0, 70).']');
        }
    }
    $clones .= $clone;
}

$before = substr_count($xml, '<w:tr');
$xml = substr_replace($xml, $clones, $tblEnd, 0);
printf("two §28.5 rows built (%d bytes); the table goes from %d to %d rows, one of which is its header.\n",
    strlen($clones), $rowCount, $rowCount + count($rows));

if (! $apply) {
    echo "DRY RUN — nothing written.\n";
    exit(0);
}

$backup = $root.'/storage/app/scratch/pass407-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    $fail('the backup copy could not be written, so the pass has no way to undo itself.');
}

$zip = new ZipArchive;
if ($zip->open($docx, ZipArchive::CREATE) !== true) {
    $fail('the docx did not reopen for writing.');
}
$zip->addFromString('word/document.xml', $xml);
$zip->close();

// Container guards: an inserted row must add exactly the paragraph, run and text tags its own cells hold, and the
// file must still parse. A pass that closes and reopens the same elements can balance a count while corrupting the
// body, so the parse is the guard and the count is only the tripwire.
$again = new ZipArchive;
if ($again->open($docx) !== true) {
    $fail('the written docx did not reopen for the guards.');
}
$read = (string) $again->getFromName('word/document.xml');
$again->close();

if (substr_count($read, '<w:tr') !== $before + count($rows)) {
    $fail('row count moved by something other than the two rows inserted.');
}
if (substr_count($read, '<w:tbl>') !== substr_count($read, '</w:tbl>')) {
    $fail('table containers unbalanced.');
}
$prev = libxml_use_internal_errors(true);
$doc = new DOMDocument;
$parsed = $doc->loadXML($read);
libxml_use_internal_errors($prev);
if (! $parsed) {
    copy($backup, $docx);
    $fail('document.xml no longer parses — restored from '.$backup);
}
foreach ($rows as $r) {
    if (substr_count($read, $escape($r[0])) !== 1) {
        copy($backup, $docx);
        $fail('a new question did not read back exactly once — restored from '.$backup);
    }
}

printf("written; backup %s\n", basename($backup));
echo "Next: §29.4 still counts this table as twenty-six — run refresh-docx-pass408.php.\n";
