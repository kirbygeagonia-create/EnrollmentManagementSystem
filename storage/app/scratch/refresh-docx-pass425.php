<?php

/**
 * Pass 425 — the §1.1 row for the twenty-seventh and twenty-eighth sortable tables, and the three counts §28.3 carries.
 *
 * Table 27 (the curriculum catalogue) moved every wired and owed figure in both registers when it landed and wrote no
 * revision row, so this row carries both screens and says so rather than back-dating the missing one — the same
 * remedy the scholarship-types batch recorded.
 *
 * The row is written on the archive bytes FIRST and only then does `docxApply()` run: the engine re-reads the file
 * from disk, so a row written in memory would be discarded by the figure edits that follow it.
 *
 * The pointer guard is RE-CUT from its predecessors. Those refused when the highest cited number sat at or below the
 * line the batch grew, which was right when every insertion landed at the end of the file; here the insertion sits
 * at line 590 and the shifted pointer is 630, so that guard would refuse a write that has already been resolved.
 * This pass therefore re-reads the pair the byte resolver produced — HEAD's line 606 against the working line 630 —
 * and refuses unless both still hold the same bytes, and unless no cited number carries a leading digit that would
 * make the needle a tail of a longer figure.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass425.php [--apply]
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

// The day comes from the school's own civil clock, not from this box (the box runs ~19 h ahead of UTC). Read through
// a child so this file needs no bootstrap of its own — the import-below-first-use trap this document records.
$schoolToday = trim((string) shell_exec('"'.PHP_BINARY.'" -r "require \'vendor/autoload.php\'; $a=require_once \'bootstrap/app.php\'; $k=$a->make(Illuminate\Contracts\Console\Kernel::class); $k->bootstrap(); echo App\Support\SchoolTime::today();"'));
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
// Anchored by TITLE, not date-plus-title: the newest row in §1.1 carries the previous school day.
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

// ---------------------------------------------------- guard: the aged pointer is RESOLVED, and still true
$controller = 'app/Http/Controllers/Admin/ReferenceDataController.php';
$headExport = $root.'/storage/app/scratch/lane76-head-ReferenceDataController.php';
if (! is_file($headExport)) {
    $fail('no HEAD export of the controller — run `git show HEAD:app/Http/Controllers/Admin/ReferenceDataController.php > '
        .$headExport.'` before this pass can weigh its own pointers.');
}
$headLines = file($headExport);
$liveLines = file($root.'/'.$controller);
if ($headLines === false || $liveLines === false) {
    $fail('the controller could not be read in both versions.');
}

$dump = (string) file_get_contents($root.'/storage/app/scratch/docx-flat.txt');
$cited = [];
if (preg_match_all('/ReferenceDataController\.php\D{0,12}(\d{1,4})/', $dump, $dm) >= 1) {
    foreach ($dm[1] as $n) {
        if ((int) $n > 1) {
            $cited[] = (int) $n;
        }
    }
}
if ($cited === []) {
    $fail('the flat dump names no citation into ReferenceDataController.php — the reader is blind, not the document clean.');
}
$cited = array_values(array_unique($cited));
sort($cited);

// The pair `lane76-shift-cites.php` resolved this batch: HEAD's line 606 (the school-year dropdown read) now lives
// at 630. Every other cited number had to be a no-move. Each is asserted against the BYTES of both files.
$moves = [630 => 606];
foreach ($cited as $n) {
    $headAt = $moves[$n] ?? $n;
    if (! isset($headLines[$headAt - 1], $liveLines[$n - 1])) {
        $fail("cited line {$n} has no line {$headAt} in HEAD's copy — the pointer is past the end of one version.");
    }
    if ($headLines[$headAt - 1] !== $liveLines[$n - 1]) {
        $fail("cited line {$n} does not carry HEAD line {$headAt}'s bytes: HEAD held[".rtrim($headLines[$headAt - 1]).
            '] and the working file holds ['.rtrim($liveLines[$n - 1]).'] — re-read the pointer, do not write this row.');
    }
}
printf("guard: %d cited number(s) into the changed file [%s], every one byte-equal to the HEAD line its sentence names%s\n",
    count($cited), implode(', ', $cited),
    $moves === [] ? ' (no moves this batch)' : ' — '.count($moves).' moved by the resolver and re-read here');

// ------------------------------------------------------------------ stage 1: the §1.1 row
$opening = 'The twenty-seventh and twenty-eighth list tables sort, and this row carries both because the first of the two landed without a row of its own.';
$clauses = [
    $opening,
    'The curriculum catalogue — `ReferenceDataController::curriculums` serving `Admin/ReferenceData/Curriculums.jsx` — was the first screen in this family whose hop had to be LEFT: the major key is nullable in the migration AND null on every one of the sixteen programs live `ems` holds, so an INNER join would not have mis-ordered the list, it would have emptied it. That batch re-took every wired and owed figure in both registers and wrote no revision row, so the record arrives here a day late rather than not at all — a register that grows while its own history stays silent is the same defect as a count that stops growing.',
    'The academic term list — `ReferenceDataController::terms` serving `Admin/ReferenceData/Terms.jsx` — is the twenty-eighth. Its School Year header reaches the school-year table through a key the migration declares NOT NULL with a foreign key, and live `ems` answers nine terms, zero nulls and zero orphans, so INNER is proven here rather than copied from the curriculum screen one action away: the rule stays ask the schema and the rows, never copy the last screen.',
    'Its Semester header is an ENUM, so the sequence is asked of the connection, and its falsifier had to aim that leg at a key every fixture row shares — pointing the header at a date column reproduced the enum\'s own order and the leg would have survived over a correct screen. The Status cell is refused a header for the reason the block roster refuses its Term label: the term table holds no status column at all, the screen computes Active or Inactive from the row\'s own dates against the school\'s civil day, and a header there would promise an order the query cannot answer.',
    'The same screen carried the two-copies defect this tranche keeps meeting on new pages: the page typed the three semester values out beside the picker that filters that very field, while the handler already answered them from the enum, so a fourth semester would reach the create form and be silently refused by the filter. Both controls now read one list the server sends, and a case asserts the hand-typed constant is gone.',
    'That class\'s own first run found a wrong premise rather than a wrong code path. A case that set the search, the semester and the status together narrowed to nothing, because the Active filter asks the school which day it is and the fixture\'s term had already closed — the case was true only while the calendar agreed with the day it was written. The day is now frozen inside the case and released afterwards, so the claim is a measurement rather than a reading of the date.',
    'Each screen carries its own case file and its own falsifier, and both print their own totals, so none is written here.',
    'The counts were written by `storage/app/scratch/lane140-measure-wired-screens.php`, and this batch changed the instrument as much as the prose. Its matrix half had read only the gap row, so the two coverage rows above it restated the tranche\'s opening figures for weeks with nothing disagreeing; and its numeral reader could harvest an eighteen but not an eight, which meant the run reported FEWER claims immediately after the sentences were corrected — the instrument going quiet on a number the prose can write is the silent-absence defect, not a cleaner document. Both were widened, the owed-plan sentence joined its vocabulary, and the run now judges every wired and owed claim in both registers and refuses until each equals the tree; the dated §1.1 rows it names as records were left as written.',
    'One pointer into the changed controller aged — the school-year dropdown read the data dictionary cites — and it was moved by `lane76-shift-cites.php` after HEAD\'s line was compared byte for byte with the working line, never by arithmetic; this pass re-reads that pair itself and refuses if either side has moved since.',
    'The plan for the eight screens still owed is derived by `lane215-plan-remaining-screens.php`, and two of those are vendor-owned tables this project\'s migrations never create, which that reader says out loud rather than inventing a column list for. `lane46-filter-contract.php` reads the page-to-handler contract after the term list\'s echo joined the filters, and its own harness answers in full.',
];
$body = implode(' ', $clauses);

// No citation-shaped token may be invented by this row's own prose; assert the property, do not trust the draft.
if (preg_match('/[A-Za-z0-9_]+\.(php|jsx|js|blade\.php|json|mjs):\d/', $body) === 1) {
    $fail('the new row carries a File.php:NN citation this pass cannot verify — spell the pointer in words.');
}

$xml = $readPart($docx, 'word/document.xml');
if (substr_count($xml, $escape($body)) !== 0) {
    $fail('this pass has already run: its own row text is in the document.');
}

$rows = $rowsForTitle($xml, 'The twenty-fifth and twenty-sixth list tables sort');
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
        $fail("the replacement for cell {$j} holds more than one text run — read it before cloning.");
    }
    $clone = substr_replace($clone, $new, $at, $len);
}
if (substr_count($clone, '<w:tc>') !== 2) {
    $fail('the clone lost or gained a cell.');
}
$xml2 = substr_replace($xml, $clone, $end, 0);

// ------------------------------------------------- stage 2: the §28.3 counts and the row's own stale enumeration
$pairs = [
    ['twenty-seven of the thirty-six tables that render the shared component sort on the server',
        'twenty-eight of the thirty-six tables that render the shared component sort on the server'],
    ['9 of the 36 pages that use the shared table still offer no order',
        '8 of the 36 pages that use the shared table still offer no order'],
    ['the twenty-seven wired pages call', 'the twenty-eight wired pages call'],
    // The enumeration in the same row stopped growing at eighteen names while the count beside it reached
    // twenty-eight — a membership claim, not a ratio, and lane140 reads neither.
    ['on the majors list and on the grade-scale bands',
        'on the majors list, on the grade-scale bands, on the workflow monitor, on the fee-type and scholarship '
        .'catalogues, on the office, room and block rosters, on the intake and clearance requirement catalogues, '
        .'on the curriculum catalogue and on the academic term list'],
];
$textEdits = [];
foreach ($pairs as $i => [$from, $to]) {
    $hits = substr_count($xml2, $from);
    if ($hits !== 1) {
        $fail("pre-flight: [{$from}] occurred {$hits} time(s) in the bytes this pass will hand the engine, wanted exactly 1.");
    }
    if (substr_count($xml2, $to) !== 0) {
        $fail('pre-flight: the replacement ['.mb_substr($to, 0, 44).'…] is already in the document — this pass has run.');
    }
    // A needle that is the TAIL of a longer figure would rewrite inside that figure and print a number nobody wrote.
    if (preg_match('/\d'.preg_quote($from, '/').'/u', $xml2) === 1) {
        $fail("pre-flight: [".mb_substr($from, 0, 40).'…] occurs only as the tail of a longer digit run.');
    }
    $textEdits[] = [$from, $to];
}

printf("stage 1 built: one §1.1 row (%d bytes); stage 2 holds %d edits. %s\n",
    strlen($body), count($textEdits), $apply ? 'applying' : 'DRY RUN');

if (! $apply) {
    // Prove the figure edits resolve against the file as it stands today; the row does not touch their sentences.
    docxApply($docx, $root.'/storage/app/scratch/pass425-dryrun-backup.docx', $textEdits, [], false);
    exit(0);
}

$backup = $root.'/storage/app/scratch/pass425-backup-'.date('Ymd-His').'.docx';
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
docxApply($docx, $root.'/storage/app/scratch/pass425-engine-backup.docx', $textEdits, [], true);

$read = $readPart($docx, 'word/document.xml');

$newRows = $rowsForAnchor($read, $date.' The twenty-seventh and twenty-eighth list tables sort');
$checks = [
    'the row is present and exact' => count($newRows) === 1
        && $rowText(substr($read, $newRows[0][0], $newRows[0][1] - $newRows[0][0])) === $date.' '.$body,
    'both predecessor rows still exist' => count($rowsForTitle($read, 'The twenty-fifth and twenty-sixth list tables sort')) === 1
        && count($rowsForTitle($read, 'The twenty-fourth list table sorts')) === 1,
    'this row is the last in its table' => (function (string $read, array $newRows): bool {
        $tblEnd = strpos($read, '</w:tbl>', $newRows[0][1]);
        $nextTr = strpos($read, '<w:tr', $newRows[0][1]);

        return $nextTr === false || $tblEnd === false || $nextTr > $tblEnd;
    })($read, $newRows),
];
foreach ($pairs as $i => [$from, $to]) {
    $checks['edit '.($i + 1).' re-taken'] = substr_count($read, $from) === 0 && substr_count($read, $to) === 1;
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
    .' --check-matrix --check-docx 2>&1', $check, $code);
$out = preg_replace('/\e\[[0-9;]*m/', '', implode("\n", $check));
if ($code !== 0 || substr_count($out, 'AGREES') !== 2) {
    copy($backup, $docx);
    $fail("lane140 still disagrees after the write (exit {$code}) — restored from {$backup}:\n".mb_substr($out, -700));
}

printf("written; row present, exact, last in the table, predecessors intact; %d edits re-taken (27 to 28 wired, 9 to 8 owed, "
    ."the register's own enumeration widened); lane140 reads AGREES on both surfaces; backup %s\n",
    count($pairs), basename($backup));
echo "Now run sweep-docx-citations, lane37, verify-docx, docx-ooxml-check and the Word open test.\n";
