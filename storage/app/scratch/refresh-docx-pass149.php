<?php

/**
 * Pass 149 — §28.3 gains G-19 (lane 50: a calendar day painted through the viewer's zone), and every citation
 * into the sixteen pages this batch touched moves +1.
 *
 * The shift is applied per file to the exact numbers that were verified first: for each one, HEAD's line N was
 * compared byte-equal to the working line N+1, so the pointer moves with its code rather than with arithmetic.
 * The row is CLONED from G-18 so it inherits widths, borders and run properties; the register's size is
 * recounted from the edited bytes and rewritten in both places that state it; the suite figure is read out of
 * the whole-suite log, never typed.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$suiteLog = $root.'/storage/app/scratch/lane50-suite-final.log';

// ---------- citations this batch moved: file needle => the cited start numbers, verified N vs N+1 ----------
$shifts = [
    'Students/Show.jsx' => [37, 82, 139, 254],
    'Assessment/Show.jsx' => [100],
    'ID/Show.jsx' => [35, 296],
    'Registrar/Show.jsx' => [243],
    'Evaluation/Show.jsx' => [340],
    'Admission/Show.jsx' => [275],
    'Accounting/DailyReport.jsx' => [61],
    'Accounting/Index.jsx' => [13],
    'PrintCertificate.jsx' => [47, 93, 125],
];

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open docx\n");
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $fails++;
    }
};

$new = $xml;
$moved = 0;
$verify = [];

foreach ($shifts as $needle => $numbers) {
    $pattern = '/'.preg_quote($needle, '/').':(\d+)(?:-(\d+))?/';
    $seen = [];

    $new = preg_replace_callback($pattern, function ($m) use ($needle, $numbers, &$seen, &$moved): string {
        $start = (int) $m[1];
        $seen[$start] = ($seen[$start] ?? 0) + 1;

        if (! in_array($start, $numbers, true)) {
            return $m[0];
        }

        $moved++;
        $end = isset($m[2]) && $m[2] !== '' ? '-'.((int) $m[2] + 1) : '';

        return $needle.':'.($start + 1).$end;
    }, $new) ?? '';

    $missed = array_diff($numbers, array_keys($seen));
    $guard($missed === [], "$needle — every listed number occurs in the document (missing: ".implode(',', $missed).')');
    $verify[$needle] = $seen;
}

// Re-read the document with the same parser: the listed numbers must now appear shifted, and none of the old
// spellings may survive. This is what catches a range that was rewritten as if it were a single number.
foreach ($shifts as $needle => $numbers) {
    preg_match_all('/'.preg_quote($needle, '/').':(\d+)(?:-(\d+))?/', $new, $after, PREG_SET_ORDER);
    $now = [];

    foreach ($after as $m) {
        $now[(int) $m[1]] = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : null;
    }

    foreach ($numbers as $n) {
        $count = $verify[$needle][$n] ?? 0;

        // A citation with no range stores a null endpoint, and isset() is false for a null value — the key is
        // what is being asserted here, so ask about the key.
        if (! array_key_exists($n + 1, $now)) {
            printf("  DEBUG %s: looking for :%d — regex keys are [%s]; substr_count(\"%s:%d\")=%d\n",
                $needle, $n + 1, implode(',', array_keys($now)), $needle, $n + 1, substr_count($new, $needle.':'.($n + 1)));
        }

        $guard(array_key_exists($n + 1, $now), "$needle:$n now reads $needle:".($n + 1)." ($count place(s))");

        if ($n === 37) {
            $guard(($now[38] ?? null) === 43, 'the Students/Show.jsx range moved as a range: 37-42 → 38-43');
        }

        if ($needle === 'Accounting/Index.jsx' && $n === 13) {
            $guard(($now[14] ?? null) === 18, 'the Accounting/Index.jsx range moved as a range: 13-17 → 14-18');
        }

        if ($needle === 'PrintCertificate.jsx' && $n === 93) {
            $guard(($now[94] ?? null) === 95, 'the PrintCertificate.jsx range moved as a range: 93-94 → 94-95');
        }

        $guard(! array_key_exists($n, $now), "no unshifted $needle:$n is left");
    }
}

printf("%d citation pointer(s) moved +1.\n\n", $moved);

// ---------- the G-19 row, cloned from G-18 ----------
$anchor = 'A real zero painted itself as absence: a money figure that was its own guard';

$rows = [[
    'G-19',
    'A calendar day showed the day before it: every date cell was painted through the viewer\'s zone',
    'The third face of the same question. G-17 made an absent figure dash; G-18 made a real zero render as a '
    .'zero; lane 50 asked what happens when the value is a day. Every calendar day reaches the browser in one of '
    .'two spellings — a zoneless `2026-05-12`, or, through Laravel’s `date` cast, `2026-05-12T00:00:00.000000Z` — '
    .'and JavaScript parses both as an instant at UTC midnight. A screen that then calls '
    .'`new Date(value).toLocaleDateString()` re-projects that instant into the *viewer’s* zone, so anywhere west '
    .'of UTC it paints the day before the one the record holds. Live `ems` carries 21 DATE columns, 18 of them '
    .'cast `date` by a model, and a sweep of 11 desk screens on this machine (zone `Etc/GMT+11`, which is the '
    .'open host-clock finding) showed every day column that reaches them shifting — 13 distinct date keys, among '
    .'them `birthdate`, `examDate`, `requestDate`, `enrolledDate`, `approvalDate`, `evaluatedDate` and the term '
    .'and clearance windows: `/students/92` read `5/11/2004` for a stored '
    .'`2004-05-12`, `/exam` read `9/12/2026` for `2026-09-13`, and the Registrar’s certificate page formats '
    .'`enrolledDate` the same way, so the wrong day would have gone out on paper. In the Philippines (UTC+8) all '
    .'of this reads correctly, which is why it survived every earlier pass. The fix is one shared module, '
    .'`calendarDay.js`: `formatCalendarDay()` takes the year, month and day out of the text and formats them '
    .'with `timeZone: \'UTC\'`, so no client zone can move a school day, and 24 cells across 16 pages were '
    .'re-pointed to it. `tests/Feature/CalendarDayPayloadTest.php` pins the payload spelling the module depends '
    .'on — it fails if the application stops serialising in UTC — because a PHP test cannot run the browser’s '
    .'Date, and the browser half is what the checker and a UTC-11 measurement of the painted cells cover. '
    .'Instants are deliberately left rendering in the viewer’s zone (receivedDate, validatedDate, printedDate, '
    .'refundedAt, signedDate, the audit log — 17 cells), because there the local zone is the right answer; what '
    .'is wrong about them on this box is the clock, not the code. Two measurements came out of the same sweep '
    .'and are recorded rather than silently fixed: `payments.paymentDate` is a `datetime` column whose every one '
    .'of its 15 rows is exactly midnight, because a date-only input writes it — a calendar day typed as an '
    .'instant, now displayed as a day, and the cashier’s report stops labelling it `Time`, which painted 12:00 '
    .'AM for every receipt — and the term-status badge compares `new Date()` against those midnights, an '
    .'instant-versus-day comparison the checker classifies as arithmetic and leaves. The class is machine-checked '
    .'by storage/app/scratch/lane50-date-day-shift.php, which reads column types from 97 migrations and casts '
    .'from 54 models, refuses the run on any name it cannot bind, and prints its own 9 blind spots; proved by '
    .'mutate-lane50-checker.php with seven cases, including the local-helper shape that a first version of the '
    .'checker let through untouched. Every citation into the sixteen touched pages moved +1 only after HEAD’s '
    .'line N was compared byte-equal to the working line N+1 — 17 pointers, verified rather than assumed.',
    'Closed 2026-10-18 — a school day is read from the record’s own text; only an instant is rendered in the viewer’s zone',
    'resources/js/Components/ui/calendarDay.js:14; resources/js/Pages/Students/Show.jsx:201; '
    .'resources/js/Pages/Exam/Index.jsx:73; resources/js/Pages/Admin/ReferenceData/Curriculums.jsx:25; '
    .'resources/js/Pages/Accounting/DailyReport.jsx:41; resources/js/Pages/Clearance/Periods.jsx:15; '
    .'storage/app/scratch/lane50-date-day-shift.php; tests/Feature/CalendarDayPayloadTest.php',
]];

$at = strpos($new, $anchor);
$guard($at !== false && substr_count($new, $anchor) === 1, 'the G-18 anchor occurs exactly once');
$rowStart = $at === false ? false : strrpos(substr($new, 0, $at), '<w:tr');
$rowEnd = $at === false ? false : strpos($new, '</w:tr>', $at) + 7;
$guard($rowStart !== false && $rowEnd !== false, 'the G-18 row is bounded');
$guard($rowStart !== false && substr($new, (int) $rowStart - 7, 7) === '</w:tr>', 'a sibling row precedes it');
$guard(substr($new, (int) $rowEnd, 6) === '<w:tr>', 'a sibling row follows it — the clone lands inside the table');

$row = substr($new, (int) $rowStart, (int) $rowEnd - (int) $rowStart);
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);
$guard(count($cm[0]) === 5, 'the template row has five cells');

$setText = function (string $cell, string $text): string {
    if (! preg_match('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell, $m, PREG_OFFSET_CAPTURE)) {
        throw new RuntimeException('the cell carries no text run to replace');
    }
    $escaped = str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);

    return substr($cell, 0, $m[1][1]).$escaped.substr($cell, $m[1][1] + strlen($m[1][0]));
};

$containers = ['tr' => '</w:tr>', 'tc' => '<w:tc>', 'p' => '</w:p>', 't' => '</w:t>'];
$before = $expect = [];

foreach ($containers as $k => $n) {
    $before[$k] = substr_count($new, $n);
}

$clones = '';

foreach ($rows as $r) {
    $clone = $row;

    foreach ($cm[0] as $i => $cell) {
        $clone = str_replace($cell, $setText($cell, $r[$i]), $clone);
    }

    $guard($clone !== $row, 'the G-19 clone carries new text');
    $guard(substr_count($clone, '<w:tr') === substr_count($row, '<w:tr'), 'the G-19 clone is one row and no more');
    $clones .= $clone;
}

foreach ($containers as $k => $n) {
    $expect[$k] = substr_count($clones, $n);
    $guard($expect[$k] === substr_count($row, $n), "container {$n}: the clone contributes exactly one row's worth");
}

$new = substr_replace($new, $clones, (int) $rowEnd, 0);

foreach ($containers as $k => $n) {
    $after = substr_count($new, $n);
    $guard($after === $before[$k] + $expect[$k], "container {$n}: {$before[$k]} → {$after} (expected +{$expect[$k]})");
}

// ---------- recount §28.3 from the edited bytes, and move both places that state its size ----------
$tblStart = strrpos(substr($new, 0, (int) $rowStart), '<w:tbl>');
$tblEnd = strpos($new, '</w:tbl>', (int) $rowEnd) + 7;
$tblText = substr($new, (int) $tblStart, (int) $tblEnd - (int) $tblStart);
preg_match_all('/<w:tr.*?<\/w:tr>/s', $tblText, $check);
$dataRows = count($check[0]) - 1;
printf("     §28.3 recounted from the edited bytes: 1 header + %d data rows\n", $dataRows);
$guard($dataRows === 36, 'the table now holds 36 data rows');
preg_match_all('/\bG-(\d+)\b/', $tblText, $gids);
$guard(((int) max($gids[1])) === 19 && count(array_unique($gids[0])) === 19, 'the G block now runs G-1 … G-19');

$sentenceOld = 'lane 49 added G-18 the same day, so §28.3 holds 35 data rows';
$sentenceNew = 'lane 49 added G-18, and lane 50 added G-19 the same day, so §28.3 holds '.$dataRows.' data rows';
$guard(substr_count($new, $sentenceOld) === 1, 'the arithmetic sentence is present and unique');
$new = str_replace($sentenceOld, $sentenceNew, $new);
$guard(substr_count($new, 'holds '.$dataRows.' data rows') === 1, 'the paragraph now reads '.$dataRows);

$famAt = strpos($new, 'G / D / U / X / N');
$guard($famAt !== false && substr_count($new, 'G / D / U / X / N') === 1, 'the family row is present and unique');
$famRowStart = $famAt === false ? false : strrpos(substr($new, 0, $famAt), '<w:tr');
$famRowEnd = $famAt === false ? false : strpos($new, '</w:tr>', $famAt) + 7;
$famRow = $famRowStart === false ? '' : substr($new, (int) $famRowStart, (int) $famRowEnd - (int) $famRowStart);
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $famRow, $fc);
$guard(count($fc[0]) === 4, 'the family row has four cells');
$guard($fc[0] !== [] && substr_count($fc[0][1], '>35<') === 1, 'its Rows cell reads exactly 35');
$famNew = str_replace($fc[0][1] ?? '', str_replace('>35<', '>'.$dataRows.'<', $fc[0][1] ?? ''), $famRow);
$guard($famNew !== $famRow, 'the Rows cell was rewritten');
$new = substr_replace($new, $famNew, (int) $famRowStart, strlen($famRow));

// ---------- the revision row: the lane-50 sentence, and the suite figure from the log that measured it ----------
$laneOld = 'and the class is now checked by storage/app/scratch/lane49-zero-shown-as-absence.php with its blind spots '
    .'printed; G-18 records it.';
$laneNew = $laneOld.' Lane 50 turned to the third case — a value that is neither absent nor zero but a day. '
    .'A calendar day reaches the browser as `2026-05-12` or as `2026-05-12T00:00:00.000000Z`, and both are '
    .'parsed as an instant at UTC midnight, so a cell that formats it through the viewer’s own zone paints the '
    .'day before the record on any client west of UTC: this machine, at Etc/GMT+11, showed yesterday’s date on '
    .'all 21 of its DATE columns, including the Registrar’s certificate page. One shared module now reads the '
    .'day out of the text and formats it in UTC, 24 cells across 16 pages use it, real instants keep local '
    .'rendering, and G-19 records it — with `payments.paymentDate` noted as a `datetime` whose every row is '
    .'midnight, a day typed as an instant.';
$guard(substr_count($new, $laneOld) === 1, 'the revision row’s lane-49 sentence is present and unique');
$new = str_replace($laneOld, $laneNew, $new);

if (! is_file($suiteLog)) {
    fwrite(STDERR, "REFUSE: no whole-suite log at $suiteLog — the document's suite figure must come from a measured run.\n");
    exit(1);
}

// Pest decorates its own summary, so the escape sequences come off before anything is matched — an anchored
// regex on a decorated line silently matches nothing.
$log = preg_replace('/\e\[[0-9;]*m/', '', file_get_contents($suiteLog));

if (! preg_match('/Tests:\s*([0-9,]+)\s+passed\s*\(([0-9,]+)\s+assertions?\)/', $log, $m)) {
    fwrite(STDERR, "REFUSE: the suite log prints no \"Tests: … passed (… assertions)\" line.\n");
    exit(1);
}

$tests = (int) str_replace(',', '', $m[1]);
$assertions = (int) str_replace(',', '', $m[2]);
printf("     suite figure read from the log: %d tests / %d assertions\n", $tests, $assertions);

$suiteOld = 'The suite stands at 747 tests and 5092 assertions';
$suiteNew = 'The suite stands at '.$tests.' tests and '.$assertions.' assertions';
$guard(substr_count($new, $suiteOld) === 1, 'the revision row’s suite sentence is present and unique');
$new = str_replace($suiteOld, $suiteNew, $new);
$guard(substr_count($new, $suiteNew) === 1, 'the suite figure now reads the measured run');

libxml_use_internal_errors(true);
$doc = new DOMDocument;
$guard($doc->loadXML($new) === true, 'document.xml parses after the citation moves, the insert and the rewrites');
libxml_clear_errors();

printf("\none row: %d bytes | counts rewritten to %d | suite %d/%d\n", strlen($clones), $dataRows, $tests, $assertions);

if ($fails > 0) {
    fwrite(STDERR, "{$fails} guard(s) failed — nothing written\n");
    exit(1);
}

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-18-pass149.docx');
$z0 = new ZipArchive();
if ($z0->open($src) !== true) {
    fwrite(STDERR, "cannot count parts\n");
    exit(1);
}
$partsBefore = count($z0);
$z0->close();

$z1 = new ZipArchive();
if ($z1->open($src, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "cannot open the docx for updating — restore the backup\n");
    exit(1);
}
$z1->addFromString('word/document.xml', $new);
$z1->close();

$check2 = new ZipArchive();
$check2->open($src);
$onDisk = $check2->getFromName('word/document.xml');
$partsAfter = count($check2);
$check2->close();
if ($onDisk !== $new) {
    fwrite(STDERR, "the file on disk does not hold the edited document.xml — restore the backup\n");
    exit(1);
}
if ($partsAfter !== $partsBefore) {
    fwrite(STDERR, "the archive went from {$partsBefore} parts to {$partsAfter} — restore the backup\n");
    exit(1);
}
printf("WROTE %s — byte-verified, all %d parts present\n", $src, $partsAfter);
