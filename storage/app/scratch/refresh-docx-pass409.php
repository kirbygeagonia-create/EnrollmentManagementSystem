<?php

/**
 * Pass 409 — two things the morning's closures left unsaid, and the §1.1 row that records them.
 *
 * First: §20.2's column list for `auditlogs` states `action` as `created | updated | deleted`. That became false the
 * moment §28.3 G-37 closed — the column now also holds the name of the desk act while one runs — and no instrument
 * weighs a column's value list against the code that writes it, so the sentence would have stayed. The cell is
 * re-taken whole through the engine's cell kind: its contents sit in a dozen styled runs, so no text needle spans
 * the vocabulary, and the cost is that the identifiers inside the cell lose their monospace, which is stated here
 * rather than hidden.
 *
 * Second: §28.5 gained two questions (ADS problems 13 and 14) and §29.4's count of that register was re-taken from
 * the rows by `lane212-weigh-question-register.php` rather than typed from "twenty-six plus two". This pass adds the
 * §1.1 row for that batch FIRST, on the archive bytes, and only then calls `docxApply()` — which re-reads the file
 * from disk and would otherwise discard the row written in memory.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass409.php [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);
require $root.'/storage/app/scratch/docx-edit-engine.php';

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

$flatten = static function (string $fragment): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
};
$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);
$rowText = static function (string $row) use ($flatten): string {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);

    return implode(' ', array_map($flatten, $cm[0]));
};
$rowsForAnchor = static function (string $xml, string $anchor) use ($rowText): array {
    $hits = [];
    $at = 0;
    while (($start = strpos($xml, '<w:tr', $at)) !== false) {
        $end = strpos($xml, '</w:tr>', $start);
        if ($end === false) {
            break;
        }
        $end += 7;
        if (strncmp($rowText(substr($xml, $start, $end - $start)), $anchor, strlen($anchor)) === 0) {
            $hits[] = [$start, $end];
        }
        $at = $end;
    }

    return $hits;
};

// ------------------------------------------------------------------ stage 1: the §1.1 row
$date = '2026-11-02';
$clauses = [
    'The two numbers the ADS guide asks the school for are now asked in the register that asks school questions, and the count beside that register is weighed by an instrument.',
    '§28.5 held neither the term unit ceiling nor a faculty teaching-load limit, though both had been recorded as open in the ADS deliverables and in a dated §1.1 row — so the questions existed in every document an office reads except the one that gathers them. Two rows are now in §28.5, items 10 and 11 are on the 2026-10-29 sheet, and the sheet\'s own header tally moved with them.',
    'Inserting them moved §29.4\'s count of that register from twenty-six to twenty-eight, which is a membership claim of exactly the kind §28 records elsewhere: a stated number beside a table does not follow the table. `storage/app/scratch/lane212-weigh-question-register.php` now reads both — the physical rows of §28.5\'s table out of `document.xml`, minus its header, and the count cell of §29.4 — and refuses when either side cannot be located, when the cell holds no number, or when the two disagree. `refresh-docx-pass408.php` reads its answer from that instrument rather than adding two by hand, and re-runs it after the write so the claim "the cell now equals the rows" is the checker\'s verdict, not the pass\'s arithmetic.',
    'The falsifier found two defects in the new weigher before its verdict could be trusted, both classes this document already names: `Open decisions` is a phrase the narrative uses as well as a table label, so the row is now identified by its shape — a cell whose whole text is the label followed by a numeric cell — and anything else is refused; and `strrpos($xml, $needle, $offset)` finds the last occurrence AT OR AFTER the offset, not the nearest one before it, so bounding a row backwards needs the offset measured against the end of the string, which had the checker reading no cells at all.',
    'The same morning\'s closure left a sentence in §20.2 stating `auditlogs.action` as created, updated or deleted. No instrument weighs a column\'s value list against the code that writes it, so the cell is re-taken whole through the engine\'s cell kind — its contents sit in a dozen styled runs, so no text needle spans them — and the identifiers inside it lose their monospace as a result.',
];
$body = implode(' ', $clauses);

$cellEdits = [[
    'PK, auto',
    'auditId',
    'auditId (PK, auto), userId (nullable FK → staffusers.userId), action (created, updated or deleted for a write that belongs to no named act, or the name of the desk act while one is running — §28.3 G-37 closed 2026-11-02), adminOverride (bool, default false), entityTable, entityId, oldValues (JSON), newValues (JSON), ipAddress (45), createdAt',
]];

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    $fail('the docx did not open.');
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
if ($xml === '') {
    $fail('word/document.xml read empty.');
}

if (substr_count($xml, $escape($body)) !== 0) {
    $fail('this pass has already run: its own row text is in the document.');
}
$anchors = substr_count($xml, 'PK, auto');
if ($anchors !== 1) {
    $fail("the columns cell's anchor occurred {$anchors} time(s), expected exactly 1.");
}

$rows = $rowsForAnchor($xml, $date.' A deactivated account now stops at its next request');
if (count($rows) !== 1) {
    $fail('the newest revision row matched '.count($rows).' row(s), expected exactly 1 — re-read §1.1 before cloning.');
}
[$start, $end] = $rows[0];
$template = substr($xml, $start, $end - $start);
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $template, $cm);
if (count($cm[0]) !== 2) {
    $fail('the revision row this pass clones holds '.count($cm[0]).' cells, expected 2.');
}
foreach ($cm[0] as $cell) {
    preg_match_all('/<w:p>.*?<\/w:p>/s', $cell, $pm);
    foreach (array_slice($pm[0], 1) as $extra) {
        if ($flatten($extra) !== '') {
            $fail('a cell of the revision row holds a SECOND paragraph with text — read it before cloning.');
        }
    }
}

$values = [$date, $body];
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
    $cellXml = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
    $new = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
        .$escape($values[$j]).'</w:t></w:r></w:p>', $cellXml);
    if (substr_count($new, '<w:t xml:space="preserve">') !== 1) {
        $fail("the replacement for cell {$j} holds more than one text run.");
    }
    $clone = substr_replace($clone, $new, $at, $len);
}
if (substr_count($clone, '<w:tc>') !== 2) {
    $fail('the clone lost or gained a cell.');
}
$xml2 = substr_replace($xml, $clone, $end, 0);

printf("stage 1 built: one §1.1 row (%d bytes) plus one cell rewrite. %s\n", strlen($body), $apply ? 'applying' : 'DRY RUN');

if (! $apply) {
    docxApply($docx, $root.'/storage/app/scratch/pass409-engine-backup.docx', [], $cellEdits, false);
    exit(0);
}

$backup = $root.'/storage/app/scratch/pass409-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    $fail('the backup copy could not be written.');
}
$zip = new ZipArchive;
if ($zip->open($docx, ZipArchive::CREATE) !== true) {
    $fail('the docx did not reopen for writing the row.');
}
$zip->addFromString('word/document.xml', $xml2);
$zip->close();

// Stage 2 on the file as it now stands: the engine re-reads the archive, so the row written above is in what it edits.
$engineBackup = $root.'/storage/app/scratch/pass409-engine-backup.docx';
docxApply($docx, $engineBackup, [], $cellEdits, true);

// Read back both halves.
$again = new ZipArchive;
if ($again->open($docx) !== true) {
    $fail('the written docx did not reopen.');
}
$read = (string) $again->getFromName('word/document.xml');
$again->close();

$newRows = $rowsForAnchor($read, $date.' The two numbers the ADS guide asks the school for');
$checks = [
    'the row is present and exact' => count($newRows) === 1
        && $rowText(substr($read, $newRows[0][0], $newRows[0][1] - $newRows[0][0])) === $date.' '.$body,
    'the action column now names the act' => str_contains($read, 'or the name of the desk act while one is running'),
    'the old three-word list is gone from that cell' => ! str_contains($read, 'action ( created | updated | deleted )'),
];
$failed = array_keys(array_filter($checks, static fn (bool $v): bool => ! $v));
if ($failed !== []) {
    copy($engineBackup, $docx);
    copy($backup, $docx);
    $fail('read-back refused ['.implode('; ', $failed).'] — restored from '.$backup);
}

$prev = libxml_use_internal_errors(true);
$doc = new DOMDocument;
$parsed = $doc->loadXML($read);
libxml_use_internal_errors($prev);
if (! $parsed) {
    copy($backup, $docx);
    $fail('document.xml no longer parses — restored from '.$backup);
}

printf("written; row present, cell re-taken, document parses; backup %s\n", basename($backup));
echo "Now re-dump and run lane212, sweep-docx-citations, verify-docx and docx-ooxml-check.\n";
