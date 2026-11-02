<?php

/**
 * Pass 418 — the §1.1 row for the twenty-second wired table, and the three counts §28.3 carries for it.
 *
 * The office directory now orders through the shared mechanism. `lane140-measure-wired-screens.php` named the three
 * phrases in the register and refused the run; this pass writes exactly those three, then re-runs that instrument as
 * its own read-back, so the claim is the checker's verdict rather than this pass's arithmetic.
 *
 * A guard this batch needs and the last one did not: the insertion into `ReferenceDataController.php` sits at the
 * `offices()` action, and the document cites that file at numbers read off an older layout. The pass therefore
 * collects every cited number for that file out of the flat dump and REFUSES if any of them is at or below the line
 * the batch grew — a bounds sweep prints the identical clean verdict for a file that never moved and for one whose
 * every pointer aged.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass418.php [--apply]
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

// ---------------------------------------------------------------- guard: did any pointer into the changed file age?
$controller = 'app/Http/Controllers/Admin/ReferenceDataController.php';
$grew = 0;
foreach (file($root.'/'.$controller) as $i => $x) {
    if (str_contains((string) $x, 'public function offices(')) {
        $grew = $i + 1;
        break;
    }
}
if ($grew < 1) {
    $fail('the offices() action was not found, so the pass cannot say where the file grew.');
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
$max = max($cited);
printf("guard: %d cited number(s) into the changed file, highest %d; the insertion begins at line %d => %s\n",
    count($cited), $max, $grew, $max < $grew ? 'no pointer aged' : 'POINTERS AGED');
if ($max >= $grew) {
    $fail('a citation into ReferenceDataController.php sits at or below the line this batch grew — run lane76 and re-read, do not write this row.');
}

// ---------------------------------------------------------------- stage 1: the §1.1 row
$schoolToday = trim((string) shell_exec('C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe -r "require \'vendor/autoload.php\'; $a=require_once \'bootstrap/app.php\'; $k=$a->make(Illuminate\Contracts\Console\Kernel::class); $k->bootstrap(); echo App\Support\SchoolTime::today();"'));
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $schoolToday) !== 1) {
    $fail("the school's civil day answered [{$schoolToday}], which is not a date — fix the reader, do not type a date.");
}
$date = $schoolToday;

$opening = 'The twenty-second list table sorts, and its real claim is an order the screen is refused.';
$clauses = [
    $opening,
    'The office directory — `ReferenceDataController::offices` serving `Admin/ReferenceData/Offices.jsx` — paints four columns and holds two in the query. The desk name is answered from `App\\Enums\\OfficeId` after the rows come back, and the staff, clearance-requirement and workflow-step figures are grouped counts over the tables that point at an office, so neither can be ordered by a column that exists. They are therefore left out of the offered set with the reason written where the code is read, and `only_the_two_columns_the_query_holds_are_offered_as_orders` asserts that set EXACTLY rather than merely present: an offer list that gains a derived label is §28.3’s filter defect reached from the sort side, and a header over something the server cannot obey is worse than no header, because the desk would click it and see nothing change.',
    '`mutate-g40-office-directory-tests.php` plants that inversion twice over, once on each side of the pairing: the controller is handed a `workflowName` key whose only honest answer is a different column, and the page is handed a `sortKey` for `staffCount` that no handler lists. The rest of the harness is the family’s standard set — the name column pointed at the record key, the ascending default flipped to the queue’s answer, the applied order never echoed, a forged key falling back to the wrong column, the retired hard-coded order returning — every one killed by the case that owns it, and both files restored by hash.',
    'No pointer aged, and that is asserted rather than assumed: the pass collects every citation into this controller out of the flat dump, finds the highest, and refuses if it sits at or below the line the batch grew. The register’s counts were written by `storage/app/scratch/lane140-measure-wired-screens.php`, which named the three stale phrases here and the four in the requirement matrix, and the dated §1.1 rows it reported as records were left as written.',
];
$body = implode(' ', $clauses);
if (preg_match('/[A-Za-z0-9_]+\.(php|jsx|js|blade\.php|json):\d/', $body) === 1) {
    $fail('the new row carries a File.php:NN citation this pass cannot verify — spell the pointer in words.');
}

$xml = $readPart($docx, 'word/document.xml');
if (substr_count($xml, $escape($body)) !== 0) {
    $fail('this pass has already run: its own row text is in the document.');
}
$rows = $rowsForTitle($xml, 'The twentieth and twenty-first catalogue tables sort');
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

// ---------------------------------------------------------------- stage 2: the three register counts
$pairs = [
    ['the twenty-one wired pages call', 'the twenty-two wired pages call'],
    ['twenty-one of the thirty-six tables that render the shared component', 'twenty-two of the thirty-six tables that render the shared component'],
    ['15 of the 36 pages that use the shared table still offer no order', '14 of the 36 pages that use the shared table still offer no order'],
];
$textEdits = [];
foreach ($pairs as [$from, $to]) {
    $hits = substr_count($xml2, $from);
    if ($hits !== 1) {
        $fail("pre-flight: [{$from}] occurred {$hits} time(s) in the bytes this pass hands the engine, wanted exactly 1.");
    }
    if (substr_count($xml2, $to) !== 0) {
        $fail('pre-flight: the replacement ['.mb_substr($to, 0, 44).'…] is already in the document — this pass has run.');
    }
    $textEdits[] = [$from, $to];
}

printf("stage 1 built: one §1.1 row (%d bytes); stage 2 holds %d count edits. %s\n",
    strlen($body), count($textEdits), $apply ? 'applying' : 'DRY RUN');

if (! $apply) {
    docxApply($docx, $root.'/storage/app/scratch/pass418-dryrun-backup.docx', $textEdits, [], false);
    exit(0);
}

$backup = $root.'/storage/app/scratch/pass418-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    $fail('the backup copy could not be written.');
}
$zip = new ZipArchive;
if ($zip->open($docx, ZipArchive::CREATE) !== true) {
    $fail('the docx did not reopen for writing the row.');
}
$zip->addFromString('word/document.xml', $xml2);
$zip->close();

docxApply($docx, $root.'/storage/app/scratch/pass418-engine-backup.docx', $textEdits, [], true);

$read = $readPart($docx, 'word/document.xml');
$newRows = $rowsForTitle($read, 'The twenty-second list table sorts');
$checks = [
    'the row is present and exact' => count($newRows) === 1
        && $rowText(substr($read, $newRows[0][0], $newRows[0][1] - $newRows[0][0])) === $date.' '.$body,
    'both predecessor rows still exist' => count($rowsForTitle($read, 'The twentieth and twenty-first catalogue tables sort')) === 1
        && count($rowsForTitle($read, 'The nineteenth list table in the shared control sorts')) === 1,
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
    .' --check-matrix --check-docx 2>&1', $check, $code);
$out = preg_replace('/\e\[[0-9;]*m/', '', implode("\n", $check));
if ($code !== 0 || substr_count($out, 'AGREES') !== 2) {
    copy($backup, $docx);
    $fail("lane140 still disagrees after the write (exit {$code}) — restored from {$backup}:\n".mb_substr($out, -700));
}

printf("written; row present, exact, last in the table, predecessors intact; three counts re-taken; "
    ."lane140 reads AGREES on both surfaces; backup %s\n", basename($backup));
echo "Now run sweep-docx-citations, lane37, verify-docx, docx-ooxml-check and the Word open test.\n";
