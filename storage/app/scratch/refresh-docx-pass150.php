<?php

/**
 * Pass 150 — §28.3 gains G-20 (lane 51: the school's calendar, one declaration), and every citation into a file
 * this batch moved is re-pointed by the shift that was *measured* against HEAD, not arithmetic.
 *
 * Each pointer moves only when the cited range is byte-identical at the new offset (lane51-probe-cite-shift2.php
 * and its predecessor produced the table below; four of the deltas are +1, one is +8, one +4, one +6, one +2,
 * and one is −5 because this batch also deleted a dead local helper). The register's size is recounted from the
 * edited bytes and rewritten in both places that state it; the suite figure is read out of the whole-suite log
 * with its ANSI decoration stripped, because an anchored regex on Pest's own line matches nothing.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$suiteLog = $root.'/storage/app/scratch/lane51-suite-final.log';

/** needle => [ cited start => measured shift ] */
$shifts = [
    'AccountingController.php' => [175 => 1, 179 => 1, 186 => 1, 187 => 1, 196 => 1, 197 => 1, 200 => 1, 206 => 1, 220 => 1, 226 => 1, 243 => 1, 332 => 8],
    'UserManagementController.php' => [470 => 1, 508 => 4],
    'DashboardController.php' => [43 => 1],
    'Clearance/Index.jsx' => [58 => 2, 501 => 2],
    'ID/Show.jsx' => [297 => -5],
    'calendarDay.js' => [14 => 6],
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

// ------------------------------------------------------------------ re-point the moved citations
$new = $xml;
$moved = 0;

foreach ($shifts as $needle => $deltas) {
    $seen = [];

    $new = preg_replace_callback('/'.preg_quote($needle, '/').':(\d+)(?:-(\d+))?/', function ($m) use ($needle, $deltas, &$seen, &$moved): string {
        $start = (int) $m[1];
        $seen[$start] = ($seen[$start] ?? 0) + 1;

        if (! array_key_exists($start, $deltas)) {
            return $m[0];
        }

        $delta = $deltas[$start];
        $end = isset($m[2]) && $m[2] !== '' ? '-'.((int) $m[2] + $delta) : '';

        $moved++;

        return $needle.':'.($start + $delta).$end;
    }, $new) ?? '';

    $missed = array_diff(array_keys($deltas), array_keys($seen));
    $guard($missed === [], "$needle — every cited start occurs in the document (missing: ".implode(',', $missed).')');
}

// The shift is only believed if the document's whole set of cited ranges for that needle is exactly what the
// mapping predicts. Comparing "is the old number gone" would be wrong here: 186 moves onto 187 while 187 moves
// off it, so both numbers exist in the middle of the edit and only the multiset tells the truth.
$reads = function (string $haystack, string $needle): array {
    preg_match_all('/'.preg_quote($needle, '/').':(\d+)(?:-(\d+))?/', $haystack, $all, PREG_SET_ORDER);
    $list = [];

    foreach ($all as $m) {
        $list[] = $m[1].(isset($m[2]) && $m[2] !== '' ? '-'.$m[2] : '');
    }

    sort($list);

    return $list;
};

foreach ($shifts as $needle => $deltas) {
    $original = $reads($xml, $needle);
    $predicted = [];

    foreach ($original as $range) {
        [$s, $e] = array_pad(explode('-', $range), 2, null);

        if (array_key_exists((int) $s, $deltas)) {
            $d = $deltas[(int) $s];
            $predicted[] = ((int) $s + $d).(null === $e ? '' : '-'.((int) $e + $d));
        } else {
            $predicted[] = $range;
        }
    }

    sort($predicted);
    $actual = $reads($new, $needle);
    $guard($predicted === $actual, "$needle — every range is where the measured shift says ("
        .count($actual).' ranges'.($predicted === $actual ? '' : '; expected ['.implode(',', $predicted).'] got ['.implode(',', $actual).']').')');
}

$guard(substr_count($new, 'UserManagementController.php:512-531') === 1, 'the audit-filter range moved as a range (+4)');
$guard(substr_count($new, 'ID/Show.jsx:292-305') === 1, 'the ID page range moved backwards as a range (−5)');
$guard(substr_count($new, 'calendarDay.js:20') === 1, 'the day formatter’s own line moved with the file (+6)');
printf("%d citation pointer(s) re-pointed.\n\n", $moved);

if ($fails > 0) {
    fwrite(STDERR, "{$fails} guard(s) failed at the citation step — nothing written\n");
    exit(1);
}

// ------------------------------------------------------------------ the G-20 row, cloned from G-19
$anchor = 'A calendar day showed the day before it: every date cell was painted through the viewer';

$rows = [[
    'G-20',
    'A desk’s “today” was cut at the wrong midnight: day and month boundaries drawn in UTC',
    'The fourth face of the same question. G-17 and G-18 were about numbers, G-19 about a day painted through the '
    .'viewer’s zone; lane 51 is about which midnight the system itself uses when it answers a person. The '
    .'application stores instants in UTC (`config/app.php`’s default, and `+00:00` forced on every MySQL session '
    .'since lane 31) and that is right for storing. It was wrong for answering: the school keeps Manila time, so '
    .'UTC midnight cut every desk-facing day eight hours early. Measured on live `ems` before anything changed: '
    .'1,459 of 3,426 `auditlogs` rows, 56 of 187 signed `workflowsteps`, 11 of 15 payment rows and 9 of 35 '
    .'enrollments fall on a different calendar date depending on which midnight you use. Four answers reached '
    .'desks in the wrong zone. The audit log’s date filter used `whereDate`, so an officer who picked a day lost '
    .'its first eight hours and gained another day’s evening; the cashier’s report took its default date from '
    .'`now()` and matched refunds with `whereDate` on `refundedAt`, so between midnight and 08:00 Manila the desk '
    .'was told that yesterday was today; and the dashboard compared `now()` against DATE columns and month '
    .'numbers, so a term that opened on the 1st read inactive until 08:00, and the first eight hours of each '
    .'month’s receipts were counted onto the month before it. `config/school.php` now declares the institution’s '
    .'civil time once and `App\Support\SchoolTime` is the only thing that reads it: `startOfSchoolDay()` and '
    .'`startOfNextSchoolDay()` return the UTC instants that bracket a school day (2026-10-18 opens at '
    .'2026-10-17 16:00Z and spans exactly 86,400 seconds), `civilDateOf()` answers “which day is this instant, at '
    .'the school”, and each query is now a half-open interval on two instants instead of a database date function '
    .'— which is also why MySQL and the SQLite suite agree. The browser holds the same line: the module declares '
    .'SCHOOL_TIMEZONE and `formatSchoolDay` / `formatSchoolMoment` render institutional moments in it, so a '
    .'signature reads identically to a Manila desk, a visiting reviewer and this box; 16 cells moved to them. The '
    .'checker that governs the class now refuses a *day* given to the school-zone formatter as loudly as it '
    .'refuses an instant left in the viewer’s zone (26 day cells, 16 school-moment cells, 9 blind spots printed, '
    .'UNBOUND refuses the run). Two rules came out of writing this and are pinned rather than assumed: '
    .'`dayOrNull` consults `checkdate`, because a shape-only test let `2026-13-45` through and Carbon rolled it '
    .'into 2027-02-13 — a desk that typed nonsense would have received a genuine report for a day that does not '
    .'exist — and a filter bound the system cannot read is ignored and echoed back rather than answered, because '
    .'being visibly ignored is survivable and being confidently wrong is not. '
    .'`tests/Feature/SchoolTimeTest.php` (6 cases / 33 assertions) is proved by '
    .'`storage/app/scratch/mutate-lane51-tests.php`: six pre-fix rules planted one at a time — the UTC '
    .'`whereDate` restored, the term window asking UTC what day it was, a school day opened at UTC midnight, the '
    .'calendar guard dropped back to shape-only, the zone undeclared, and the browser’s constant drifted — each '
    .'required to turn its own named test red, with the tree restored byte-for-byte after every case. Stated '
    .'rather than counted as covered: the refund sheet and the two dashboard aggregates moved with the same two '
    .'helpers the audit case exercises, but neither has a screen test of its own, and the case that would have '
    .'proved the term window through `civilDateOf()` alone was dropped when it turned out the test never called '
    .'that branch.',
    'Closed 2026-10-18 — instants are stored in UTC and every desk-facing boundary is drawn in the school’s declared zone',
    'config/school.php:21; app/Support/SchoolTime.php:45; app/Support/SchoolTime.php:101; '
    .'app/Http/Controllers/Admin/UserManagementController.php:504; app/Http/Controllers/Accounting/AccountingController.php:292; '
    .'app/Http/Controllers/DashboardController.php:93; app/Models/Academicterms.php:36; '
    .'resources/js/Components/ui/calendarDay.js:18; tests/Feature/SchoolTimeTest.php',
]];

$at = strpos($new, $anchor);
$guard($at !== false && substr_count($new, $anchor) === 1, 'the G-19 anchor occurs exactly once');
$rowStart = $at === false ? false : strrpos(substr($new, 0, $at), '<w:tr');
$rowEnd = $at === false ? false : strpos($new, '</w:tr>', $at) + 7;
$guard($rowStart !== false && $rowEnd !== false, 'the G-19 row is bounded');
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

    $guard($clone !== $row, 'the G-20 clone carries new text');
    $guard(substr_count($clone, '<w:tr') === substr_count($row, '<w:tr'), 'the G-20 clone is one row and no more');
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

// ------------------------------------------------------------------ recount §28.3 and move both statements of its size
$tblStart = strrpos(substr($new, 0, (int) $rowStart), '<w:tbl>');
$tblEnd = strpos($new, '</w:tbl>', (int) $rowEnd) + 7;
$tblText = substr($new, (int) $tblStart, (int) $tblEnd - (int) $tblStart);
preg_match_all('/<w:tr.*?<\/w:tr>/s', $tblText, $check);
$dataRows = count($check[0]) - 1;
printf("     §28.3 recounted from the edited bytes: 1 header + %d data rows\n", $dataRows);
$guard($dataRows === 37, 'the table now holds 37 data rows');
preg_match_all('/\bG-(\d+)\b/', $tblText, $gids);
$guard(((int) max($gids[1])) === 20 && count(array_unique($gids[0])) === 20, 'the G block now runs G-1 … G-20');

$sentenceOld = 'and lane 50 added G-19 the same day, so §28.3 holds 36 data rows';
$sentenceNew = 'lane 50 added G-19, and lane 51 added G-20 the same day, so §28.3 holds '.$dataRows.' data rows';
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
$guard($fc[0] !== [] && substr_count($fc[0][1], '>36<') === 1, 'its Rows cell reads exactly 36');
$famNew = str_replace($fc[0][1] ?? '', str_replace('>36<', '>'.$dataRows.'<', $fc[0][1] ?? ''), $famRow);
$guard($famNew !== $famRow, 'the Rows cell was rewritten');
$new = substr_replace($new, $famNew, (int) $famRowStart, strlen($famRow));

// ------------------------------------------------------------------ the revision row: lane-51 sentence, then the suite figure
$laneOld = 'midnight, a day typed as an instant.';
$laneNew = $laneOld.' Lane 51 then asked the same question of the boundaries the system itself draws. Its clock '
    .'is UTC and its session forces `+00:00`, but the school keeps Manila time, so every desk-facing day was cut '
    .'eight hours early: an officer filtering the audit log by a day lost its first eight hours, the cashier’s '
    .'report answered “today” with yesterday until 08:00, and a term that opened on the 1st looked inactive until '
    .'then. One declaration now answers the question — `config/school.php`, read only by `App\Support\SchoolTime`, '
    .'with the browser holding the same zone for institutional moments — and G-20 records it, including a guard '
    .'that consults the calendar rather than the shape, after measurement showed `2026-13-45` arriving as a real '
    .'report for 2027-02-13.';
$guard(substr_count($new, $laneOld) === 1, 'the revision row’s lane-50 sentence is present and unique');
$new = str_replace($laneOld, $laneNew, $new);

if (! is_file($suiteLog)) {
    fwrite(STDERR, "REFUSE: no whole-suite log at $suiteLog — the document's suite figure must come from a measured run.\n");
    exit(1);
}

// Pest decorates its own summary, so the escape sequences come off before anything is matched.
$log = preg_replace('/\e\[[0-9;]*m/', '', file_get_contents($suiteLog));

if (! preg_match('/Tests:\s*([0-9,]+)\s+passed\s*\(([0-9,]+)\s+assertions?\)/', $log, $m)) {
    fwrite(STDERR, "REFUSE: the suite log prints no \"Tests: … passed (… assertions)\" line.\n");
    exit(1);
}

$tests = (int) str_replace(',', '', $m[1]);
$assertions = (int) str_replace(',', '', $m[2]);
printf("     suite figure read from the log: %d tests / %d assertions\n", $tests, $assertions);

$suiteOld = 'The suite stands at 749 tests and 5097 assertions';
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

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-18-pass150.docx');
$z0 = new ZipArchive();
$z0->open($src);
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
