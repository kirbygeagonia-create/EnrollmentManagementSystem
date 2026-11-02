<?php

/**
 * Pass 416 — the §1.1 row for the twenty-first sortable table, and the three counts §28.3 carries for it.
 *
 * The scholarship catalogue now orders through the shared mechanism, and the fee-sheet catalogue joined one batch
 * earlier without writing a row of its own — so this row carries both records rather than back-dating the missing
 * one. The register's own counts are re-taken here, and read back by the instrument that owns them rather than by
 * this pass's arithmetic (the shape passes 413 and 415 established).
 *
 * The row is written on the archive bytes FIRST and only then does `docxApply()` run: the engine re-reads the file
 * from disk, so a row written in memory would be discarded by the figure edits that follow it.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass416.php [--apply]
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

// The day comes from the school's own civil clock, not from this box: the box runs ~19 h ahead of UTC, so a typed
// date and a `date()` call are both claims about the wrong clock. Read through a child so this file needs no boot
// of its own (the import-below-first-use trap this document records for scripts that bootstrap the framework).
$schoolToday = trim((string) shell_exec('C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe -r "require \'vendor/autoload.php\'; $a=require_once \'bootstrap/app.php\'; $k=$a->make(Illuminate\Contracts\Console\Kernel::class); $k->bootstrap(); echo App\Support\SchoolTime::today();"'));
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $schoolToday) !== 1) {
    $fail("the school's civil day answered [{$schoolToday}], which is not a date — fix the reader, do not type a date.");
}
$date = $schoolToday;

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
// The template row is found by its own TITLE, not by `date + title`: this row is dated 2026-11-03 (the school's
// clock rolled past midnight while the batch ran, which is what `SchoolTime::today()` is for), while the newest row
// in §1.1 carries the previous school day. A date-joined anchor would have refused a correct write — and the
// refusal is the right outcome, because the anchor's date is a claim about the day, not about the row.
$rowsForTitle = static function (string $xml, string $title) use ($rowText): array {
    $hits = [];
    $at = 0;
    while (($start = strpos($xml, '<w:tr', $at)) !== false) {
        $end = strpos($xml, '</w:tr>', $start);
        if ($end === false) {
            break;
        }
        $end += 7;
        if (str_contains($rowText(substr($xml, $start, $end - $start)), $title)) {
            $hits[] = [$start, $end];
        }
        $at = $end;
    }

    return $hits;
};
$readPart = static function (string $docx, string $part) use ($fail): string {
    $zip = new ZipArchive;
    if ($zip->open($docx) !== true) {
        $fail("could not open {$part} inside the docx.");
    }
    $xml = (string) $zip->getFromName($part);
    $zip->close();
    if ($xml === '') {
        $fail("{$part} read back empty.");
    }

    return $xml;
};

// ------------------------------------------------------------------ stage 1: the §1.1 row
$opening = 'The twentieth and twenty-first catalogue tables sort, and the second pinned a pairing no backend case can see.';
$clauses = [
    $opening,
    'The scholarship catalogue — `ReferenceDataController::scholarshipTypes` serving `Admin/ReferenceData/ScholarshipTypes.jsx` — now orders through `App\Support\ListSort` with four columns offered and none of them reached through a hop: the name, the coverage type, the coverage figure and the record key all sit on the row the board paints, so no order can widen a payload (§28.3 G-51) or drop a record, which is the lesson the audit log taught from a nullable key. Its `coverageType` is `enum(full, partial)`, whose declaration order and alphabetical order happen to coincide, and the case asks the connection for the order anyway rather than typing the pair, so the owner’s ruling of 2026-10-31 stays a derived expectation instead of a habit.',
    'What is new is the promise on the screen side. The shared table paints a header only for a key listed in `sort.available`, so a `sortKey` spelled for a column the handler does not know is a control that simply never appears — every number on the page stays correct while the desk quietly loses the affordance. One case now reads the page’s own `sortKey` list and asks the payload for each of them, and its falsifier plants the invented key in the page file rather than in the controller: two surfaces patched, because the promise spans two runtimes and this repository has no JavaScript suite.',
    'The fee-sheet catalogue joined the mechanism one batch earlier and left no row of its own in this table. This row carries both records rather than back-dating the missing one, because a dated row must state what was true on its own day — and a batch that retook the register’s counts without recording itself is exactly the gap this document names for every other register.',
    'Both registers’ counts were written by `storage/app/scratch/lane140-measure-wired-screens.php`, which named three stale phrases in §28.3 and four in the requirement matrix and refused until each equalled the tree; the dated §1.1 rows it reported as records were left as written. The instrument then found a defect in itself: its number reader took `twenty` out of “twenty-one of the 36 pages” and reported a true sentence as a claim of ONE, because a compound it cannot add was truncated rather than refused. The reader now sums compounds and orders its alternatives longest-first so `sixteen` cannot be harvested as `six`; its harness carries a leg for the compound spelling and a second for the single word `ten`, which is the check that a widening has not become an off switch.',
    'The falsifier’s own first run reported a BAD leg rather than a survivor, and the defect was its landing check: it demanded that every plant retire its own needle, which an inserted line cannot do — the append hazard this document records for docx read-backs, arriving inside a mutation harness. The check now follows the plant’s shape: a deletion must leave nothing behind, an insertion must change bytes and add its own text.',
    'This row carries the day the school’s clock says, which is one ahead of the box this batch was written on: `SchoolTime::today()` had rolled over while the work ran, and the pass reads that answer rather than a typed date. The row above it, dated the previous school day, is left exactly as written — dated rows are records, and re-aging one states a figure that was not true on its own day.',
];
$body = implode(' ', $clauses);

// No citation-shaped token may be invented by this row's own prose; assert the property, do not trust the draft.
if (preg_match('/[A-Za-z0-9_]+\.(php|jsx|js|blade\.php|json):\d/', $body) === 1) {
    $fail('the new row carries a File.php:NN citation this pass cannot verify — spell the pointer in words.');
}

$xml = $readPart($docx, 'word/document.xml');
if (substr_count($xml, $escape($body)) !== 0) {
    $fail('this pass has already run: its own row text is in the document.');
}

$rows = $rowsForTitle($xml, 'The nineteenth list table in the shared control sorts');
if (count($rows) !== 1) {
    $fail('the newest revision row matched '.count($rows).' row(s) by title, expected exactly 1 — re-read §1.1 before cloning.');
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
$cellJobs = [];
foreach ($cm2[0] as $j => $x) {
    $cellJobs[] = [$j, (int) $x[1], strlen($x[0]), $x[0]];
}
usort($cellJobs, static fn (array $a, array $b): int => $b[1] <=> $a[1]);
foreach ($cellJobs as [$j, $at, $len, $src]) {
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

// ------------------------------------------------------------------ stage 2: the three §28.3 counts
$pairs = [
    ['the twenty wired pages call', 'the twenty-one wired pages call'],
    ['twenty of the thirty-six tables that render the shared component', 'twenty-one of the thirty-six tables that render the shared component'],
    ['16 of the 36 pages that use the shared table still offer no order', '15 of the 36 pages that use the shared table still offer no order'],
];
$textEdits = [];
foreach ($pairs as [$from, $to]) {
    $hits = substr_count($xml2, $from);
    if ($hits !== 1) {
        $fail("pre-flight: [{$from}] occurred {$hits} time(s) in the bytes this pass will hand the engine, wanted exactly 1.");
    }
    if (substr_count($xml2, $to) !== 0) {
        $fail('pre-flight: the replacement ['.mb_substr($to, 0, 44).'…] is already in the document — this pass has run.');
    }
    $textEdits[] = [$from, $to];
}

printf("stage 1 built: one §1.1 row (%d bytes); stage 2 holds %d count edits. %s\n",
    strlen($body), count($textEdits), $apply ? 'applying' : 'DRY RUN');

if (! $apply) {
    // Prove the figure edits resolve against the file as it stands today; the row does not touch their sentences.
    docxApply($docx, $root.'/storage/app/scratch/pass416-dryrun-backup.docx', $textEdits, [], false);
    exit(0);
}

$backup = $root.'/storage/app/scratch/pass416-backup-'.date('Ymd-His').'.docx';
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
docxApply($docx, $root.'/storage/app/scratch/pass416-engine-backup.docx', $textEdits, [], true);

$read = $readPart($docx, 'word/document.xml');

$newRows = $rowsForAnchor($read, $date.' The twentieth and twenty-first catalogue tables sort');
$checks = [
    'the row is present and exact' => count($newRows) === 1
        && $rowText(substr($read, $newRows[0][0], $newRows[0][1] - $newRows[0][0])) === $date.' '.$body,
    'both predecessor rows still exist' => count($rowsForTitle($read, 'The nineteenth list table in the shared control sorts')) === 1
        && count($rowsForTitle($read, 'The ninth dashboard card now counts the rows its own page lists')) === 1,
    'this row is the last in its table' => (function (string $read, array $newRows): bool {
        $tblEnd = strpos($read, '</w:tbl>', $newRows[0][1]);
        $nextTr = strpos($read, '<w:tr', $newRows[0][1]);

        return $nextTr === false || $tblEnd === false || $nextTr > $tblEnd;
    })($read, $newRows),
];
foreach ($pairs as $i => [$from, $to]) {
    $checks['count '.($i + 1).' re-taken'] = substr_count($read, $from) === 0 && substr_count($read, $to) === 1;
}
$failed = array_keys(array_filter($checks, static fn (bool $v): bool => ! $v));
if ($failed !== []) {
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

exec('"'.PHP_BINARY.'" '.escapeshellarg($root.'/storage/app/scratch/dump-docx-flat.php').' 2>&1');
$check = [];
exec('"'.PHP_BINARY.'" '.escapeshellarg($root.'/storage/app/scratch/lane140-measure-wired-screens.php')
    ." --check-matrix --check-docx 2>&1", $check, $code);
$out = preg_replace('/\e\[[0-9;]*m/', '', implode("\n", $check));
if ($code !== 0 || substr_count($out, 'AGREES') !== 2) {
    copy($backup, $docx);
    $fail("lane140 still disagrees after the write (exit {$code}) — restored from {$backup}:\n".mb_substr($out, -700));
}

printf("written; row present, exact, last in the table, predecessor intact; three counts re-taken; "
    ."lane140 --check-matrix --check-docx reads AGREES on both surfaces; backup %s\n", basename($backup));
echo "Now run sweep-docx-citations, lane37, verify-docx, docx-ooxml-check and the Word open test.\n";
