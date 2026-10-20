<?php

/**
 * Pass 160 — §28.3 gains G-25 (the timetable's own hour, and the term a collision belongs to), the audit's rule
 * count moves from fourteen to sixteen in every place that states it, the revision row carries lane 55, and every
 * citation into the two files this batch edited is re-pointed.
 *
 * Two kinds of pointer are handled, and they are not the same claim:
 *
 *   SHIFTED — the code did not change, the file grew above it. The one offset that keeps EVERY cited line
 *             byte-identical is searched across -400..400 and the pointer moves by it. A shortlist of candidate
 *             offsets is how a first attempt reported "NONE" for a pointer that had merely moved by a number
 *             nobody had tried.
 *   RE-POINTED BY MEANING — the prose names a construct and the number no longer lands inside it. No arithmetic
 *             can find these; each was read, and each is written with the span measured off the current file.
 *             §17's Blocking evidence cells are all of this kind: `storeSchedule()` cited as :193-252, the entry
 *             filter as :78-91, the block cap as :433. They pass a line sweep because they land on live lines.
 *
 * Every occurrence of every token is bound to a decision or the run refuses — a bare `:461` is claimed by the
 * sentence around it, because four `:461`s sit in this document and one of them is not the block total.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$suiteLog = $root.'/storage/app/scratch/lane55-suite.txt';

$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $fails++;
    }
};

// ------------------------------------------------------------------ 0. the suite figure, read from its own log
$tests = 0;
$assertions = 0;
$log = (string) @file_get_contents($suiteLog);
foreach (explode("\n", $log) as $line) {
    if (str_contains($line, 'Tests:') && preg_match('/(\d+) passed/', $line, $m) === 1
        && preg_match('/\((\d+) assertions\)/', $line, $m2) === 1) {
        $tests = (int) $m[1];
        $assertions = (int) $m2[1];
    }
}
if ($tests === 0) {
    echo "REFUSED: the suite log carries no green `Tests:` line — a broken instrument, not a result.\n";
    exit(1);
}
if (preg_match('/(\d+) failed/', $log, $f) === 1 && (int) $f[1] > 0) {
    printf("REFUSED: the suite log reports %s failing tests.\n", $f[1]);
    exit(1);
}
printf("Suite figure read from the log: %d passed, %d assertions.\n", $tests, $assertions);

$linesOf = fn (string $p): array => array_map(fn (string $l): string => rtrim($l, "\r\n"), (array) file($p));
$braceSpan = function (array $lines, string $name): array {
    foreach ($lines as $i => $line) {
        if (preg_match('/^\s*(?:public |private )?(?:static )?function '.preg_quote($name, '/').'\(/', $line) === 1) {
            $depth = 0;
            $opened = false;
            for ($j = $i; $j < count($lines); $j++) {
                $depth += substr_count($lines[$j], '{') - substr_count($lines[$j], '}');
                if ($depth > 0) {
                    $opened = true;
                }
                if ($opened && $depth === 0) {
                    return [$i + 1, $j + 1];
                }
            }
        }
    }

    throw new RuntimeException("function {$name} not found");
};

$blocking = 'app/Http/Controllers/Blocking/BlockingController.php';
$audit = 'app/Console/Commands/AuditRecordStates.php';
$bNow = $linesOf($root.'/'.$blocking);
$bHead = $linesOf($root.'/storage/app/scratch/lane55-head-BlockingController.php');
$aNow = $linesOf($root.'/'.$audit);
$aHead = $linesOf($root.'/storage/app/scratch/lane55-head-AuditRecordStates.php');
foreach ([$bHead, $aHead] as $i => $head) {
    if (count($head) === 0) {
        echo "REFUSED: a HEAD export is missing — export both files from HEAD first.\n";
        exit(1);
    }
}

/** The one shift over the whole file that keeps every line of the range byte-identical. */
$shiftOf = function (array $head, array $now, int $from, int $to): ?int {
    $ok = [];
    for ($s = -400; $s <= 400; $s++) {
        $good = 0;
        for ($i = $from; $i <= $to; $i++) {
            $j = $i - 1 + $s;
            if (isset($now[$j]) && $now[$j] === ($head[$i - 1] ?? '__missing__')) {
                $good++;
            }
        }
        if ($good === $to - $from + 1) {
            $ok[] = $s;
        }
    }

    return count($ok) === 1 ? $ok[0] : null;
};

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$partCount = $zip->numFiles;
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
$new = $xml;

// ------------------------------------------------------------------ 1. the new row, cloned from G-24
$g25 = [
    'G-25',
    'The Blocking desk asked whether the officer and the room were free, and never whether the block already met at that hour',
    '`detectConflicts()` compared two schedules on the instructor and on the room. It never compared them on the '
        .'block, although the desk’s own route is `/blocking/{block}/schedules` — a screen whose whole purpose is '
        .'to add a subject to a block that already has one — so one group could be put in two rooms at one hour and '
        .'the form would accept it. Measured rather than argued, in both directions: with the pre-fix controller in '
        .'place the test that books a block twice lets the save through, and with this batch’s it refuses ('
        .'storage/app/scratch/mutate-lane55-conflicts.php), which is the only way to know the check is what changed '
        .'the answer. The same reading found a second thing: the query had no term, and could not have one from '
        .'`schedules` alone, because the term lives on the block — so a meeting logged in a retired term vetoed a '
        .'save in the term an office is building now, which is the opposite of how a school reuses a room each '
        .'year. Ruled 2026-10-19 on the measurement: a block cannot be in two places at once, and a collision is '
        .'judged inside the term the block belongs to. Both are now counted by `ems:audit:record-states`, which '
        .'runs sixteen rules where it ran fourteen. Measured first, and worth keeping on the record: neither state '
        .'exists today — live `ems` holds 9 schedules and 18 meetings with zero collisions on any reading, and so '
        .'does a schema built from empty in CI’s order — so this row closes the way a bad timetable would be made, '
        .'not a bad timetable. The third question this batch tested, the room branch on the create path, was found '
        .'honest against HEAD’s bytes: the new test for it passes before the fix as well, and is a cover the '
        .'register did not have, not a defect.',
    'Closed 2026-10-19 by the two rulings given on the measurement; the third question needed no ruling, only a test.',
    '§17.1, §17.2, §28.3 G-23, §28.3 G-24; found 2026-10-19 by '
        .'storage/app/scratch/lane55-timetable-probe.php and storage/app/scratch/lane55-collisions.sql, proved by '
        .'storage/app/scratch/mutate-lane55-conflicts.php',
];

$at = strpos($new, '>G-24<');
$guard($at !== false && substr_count($new, '>G-24<') === 1, 'the last existing row is findable by its own id cell');
$rowStart = $at === false ? false : strrpos(substr($new, 0, $at), '<w:tr');
$rowEnd = $at === false ? false : strpos($new, '</w:tr>', $at) + 7;
$row = substr($new, (int) $rowStart, (int) $rowEnd - (int) $rowStart);
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cells);
$guard(count($cells[0]) === 5, 'the template row has five cells');

$setText = function (string $cell, string $text): string {
    if (! preg_match('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell, $m, PREG_OFFSET_CAPTURE)) {
        throw new RuntimeException('the cell carries no text run to replace');
    }

    return substr($cell, 0, $m[1][1])
        .str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text)
        .substr($cell, $m[1][1] + strlen($m[1][0]));
};

$clone = $row;
foreach ($cells[0] as $i => $cell) {
    $clone = str_replace($cell, $setText($cell, $g25[$i]), $clone);
}
$guard($clone !== $row, 'the clone carries new text');
$guard(substr_count($clone, '<w:tr') === substr_count($row, '<w:tr'), 'the clone is one row and no more');
foreach (['</w:tr>', '<w:tc>', '</w:p>', '</w:t>'] as $n) {
    $guard(substr_count($clone, $n) === substr_count($row, $n), "container {$n}: exactly one row's worth");
}
$new = substr_replace($new, $clone, (int) $rowEnd, 0);

// ------------------------------------------------------------------ 2. recount the register, both places
$tblStart = strrpos(substr($new, 0, (int) $rowStart), '<w:tbl>');
$tblEnd = strpos($new, '</w:tbl>', (int) $rowEnd) + 7;
$tblText = substr($new, (int) $tblStart, (int) $tblEnd - (int) $tblStart);
preg_match_all('/<w:tr.*?<\/w:tr>/s', $tblText, $check);
$dataRows = count($check[0]) - 1;
printf("     §28.3 recounted from the edited bytes: 1 header + %d data rows\n", $dataRows);
$guard($dataRows === 42, 'the table now holds 42 data rows');
preg_match_all('/\bG-(\d+)\b/', $tblText, $gids);
$guard(((int) max($gids[1])) === 25, 'the G block now runs up to G-25');
$guard(count(array_unique($gids[0])) === 25, 'each of the 25 G ids appears exactly once in the table');

$enumOld = 'lane 53 added G-21 and G-22, and lane 54 added G-23 and G-24, so §28.3 holds 41 data rows';
$enumNew = 'lane 53 added G-21 and G-22, lane 54 added G-23 and G-24, and lane 55 added G-25, so §28.3 holds '
    .$dataRows.' data rows';
$guard(substr_count($new, $enumOld) === 1, 'the arithmetic sentence is present and unique');
$new = str_replace($enumOld, $enumNew, $new);

$famAt = strpos($new, 'G / D / U / X / N');
$famRowStart = $famAt === false ? false : strrpos(substr($new, 0, $famAt), '<w:tr');
$famRowEnd = $famAt === false ? false : strpos($new, '</w:tr>', $famAt) + 7;
$famRow = $famRowStart === false ? '' : substr($new, (int) $famRowStart, (int) $famRowEnd - (int) $famRowStart);
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $famRow, $fc);
$guard(count($fc[0]) === 4 && substr_count($fc[0][1] ?? '', '>41<') === 1, 'the family row’s Rows cell reads exactly 41');
$famNew = str_replace($fc[0][1] ?? '', str_replace('>41<', '>'.$dataRows.'<', $fc[0][1] ?? ''), $famRow);
$guard($famNew !== $famRow, 'the Rows cell was rewritten');
$new = substr_replace($new, $famNew, (int) $famRowStart, strlen($famRow));

// ------------------------------------------------------------------ 3. the rule count and the two claims it breaks
$auditRules = $braceSpan($aNow, 'handle');
$boxRules = [$braceSpan($aNow, 'clinicBoxWithoutRecord'), $braceSpan($aNow, 'idBoxWithoutValidatedRequest')];
$sheetRule = $braceSpan($aNow, 'sheetWithoutLines');
$receiptRule = $braceSpan($aNow, 'receiptWithoutPaidStep');
printf("     handle() :%d-%d | the two box rules :%d-%d and :%d-%d | lane 53 pair :%d-%d and :%d-%d\n",
    $auditRules[0], $auditRules[1], $boxRules[0][0], $boxRules[0][1], $boxRules[1][0], $boxRules[1][1],
    $sheetRule[0], $sheetRule[1], $receiptRule[0], $receiptRule[1]);

$edits = [
    'fourteen rules over idrequests, workflowsteps, enrollmentworkflow, enrollments, clinicrecords, notifications, documentprintlog, studentassessments, charges and enrollmentstatushistory — a validated request'
        => 'sixteen rules over idrequests, workflowsteps, enrollmentworkflow, enrollments, clinicrecords, notifications, documentprintlog, studentassessments, charges, enrollmentstatushistory, schedulemeetings, schedules and blocks — a validated request',
    'a receipt on an account that never entered paid — each counted'
        => 'a receipt on an account that never entered paid, two schedules of one block at one hour, and a room or an '
            .'officer in two schedules at one hour — each counted',
    'AuditRecordStates.php:46-167, the two box rules at :279-301'
        => 'AuditRecordStates.php:'.$auditRules[0].'-'.$auditRules[1].', the two box rules at :'
            .$boxRules[0][0].'-'.$boxRules[1][1],
    'runs fourteen rules over these tables and reads 0 on every one of them'
        => 'runs sixteen rules over these tables and reads 0 on every one of them',
    '`ems:audit:record-states` reads 0 across all fourteen rules'
        => '`ems:audit:record-states` reads 0 across all sixteen rules',
    'The suite stands at 763 tests and 5164 assertions' => 'The suite stands at '.$tests.' tests and '.$assertions.' assertions',
    // The register's own inventory row asserted a state that the file this batch read now contradicts.
    'Blocking has stored no class sessions, so the conflict check (§10.4) has never run on seeded data'
        => 'Blocking holds 9 schedules and 18 meetings on live `ems` as measured 2026-10-19, so the conflict check '
            .'(§10.4) does run on seeded data — and from this pass it refuses an hour the officer, the room, or the '
            .'block itself has already taken inside one term, with no collision present in that dataset',
    // What the desk-step row promises the form checks.
    'conflicts against instructor, room, and time are detected before saving'
        => 'conflicts against the instructor, the room, and the block’s own hour are detected before saving, and the '
            .'comparison is made inside the term that block belongs to',
];

foreach ($edits as $from => $to) {
    $n = substr_count($new, $from);
    $guard($n === 1, "needle present once ({$n}): ".mb_substr($from, 0, 52).'…');
    $new = $n === 1 ? str_replace($from, $to, $new) : $new;
}

// ------------------------------------------------------------------ 4. the pointers into the two edited files
// SHIFTED: proved by byte identity. RE-POINTED: proved by reading the construct the prose names.
$bs = fn (string $m) => ($s = $braceSpan($bNow, $m)) === [] ? '?' : $s[0].'-'.$s[1];
$spans = [
    'storeSchedule' => $bs('storeSchedule'),
    'detectConflicts' => $bs('detectConflicts'),
    'finalize' => $bs('finalize'),
    'assignStudents' => $bs('assignStudents'),
    'unassignStudents' => $bs('unassignStudents'),
    'printBlockSchedule' => $bs('printBlockSchedule'),
    'downloadBlockSchedule' => $bs('downloadBlockSchedule'),
    'show' => $bs('show'),
];
printf("     measured spans: %s\n", implode(', ', array_map(fn ($k, $v) => "{$k}:{$v}", array_keys($spans), $spans)));

$shift = function (int $from, int $to, string $who) use ($bHead, $bNow, $aHead, $aNow, $shiftOf, $guard): string {
    [$head, $now] = str_starts_with($who, 'Blocking') ? [$bHead, $bNow] : [$aHead, $aNow];
    $s = $shiftOf($head, $now, $from, $to);
    if ($s === null) {
        $guard(false, "{$who}:{$from}-{$to} resolves to exactly one shift");

        return "{$from}-{$to}";
    }
    printf("  ok   %s:%d-%d is %s%d%s (%+d)\n", $who, $from, $to, $s === 0 ? 'unmoved ' : 'moved to ',
        $from + $s, $from === $to ? '' : '-'.($to + $s), $s);

    // A one-line pointer stays one line: the document cites `File.php:NN`, and `:600-600` is a different style
    // from the one every neighbour in the same cell uses.
    return $from === $to ? (string) ($from + $s) : ($from + $s).'-'.($to + $s);
};

$B = 'BlockingController.php:';
$A = 'AuditRecordStates.php:';
// [token, context that must sit within 680 bytes (empty when the token is unique in the document or every
//  occurrence takes the same target), replacement, how many occurrences this binds]
$bind = [
    [$B.'457-538', '', $B.$shift(457, 538, 'Blocking'), 1],
    [$B.'469-476', '', $B.'515-520', 1],
    [$B.'475', '', $B.'489', 2],
    [$B.'573-601', '', $B.$shift(573, 601, 'Blocking'), 2],
    [$B.'573', '', $B.'587', 3],
    [$B.'586', '', $B.$shift(586, 586, 'Blocking'), 1],
    [$B.'594', '', $B.$shift(594, 594, 'Blocking'), 1],
    // Claimed so that the unbound sweep has seen every pointer, and proved unmoved by the same byte test.
    [$B.'68-79', '', $B.$shift(68, 79, 'Blocking'), 1],
    [$B.'172-191', '', $B.$shift(172, 191, 'Blocking'), 1],
    [$B.'179', '', $B.$shift(179, 179, 'Blocking'), 1],
    [$B.'264-271', '', $B.$shift(264, 271, 'Blocking'), 4],
    [':288-289', 'BlockingController.php:264-271', ':'.$shift(288, 289, 'Blocking'), 1],
    [':311-317', 'BlockingController.php:264-271', ':'.$shift(311, 317, 'Blocking'), 3],
    [$B.'385-387', '', $B.$shift(385, 387, 'Blocking'), 2],
    [$A.'46-'.$auditRules[1], '', $A.'46-'.$auditRules[1], 1],
    [$A.'309-314', '', $A.$sheetRule[0].'-'.$sheetRule[1], 1],
    [$A.'324-333', '', $A.$receiptRule[0].'-'.$receiptRule[1], 1],
    // Bare continuations. Only :467-478 and :461 repeat, and of the four :461s one is not the block total,
    // so those are the two tokens claimed by the sentence around them.
    [':467-478', '', ':495-507', 4],
    [':461', 'must be the Blocking box', ':519-520', 1],
    [':461', 'the block total', ':483-493', 3],
    [':433', '', ':502', 1],
    [':588', '', ':616', 1],
    [':193-252', '', ':'.$spans['storeSchedule'], 1],
    [':365+', '', ':'.$spans['detectConflicts'], 1],
    [':339-363', '', ':'.$spans['finalize'], 1],
    ['415-495', '', ':'.$spans['assignStudents'], 1],
    [':497-530', '', ':'.$spans['unassignStudents'], 1],
    [':78-91', '', ':113-135', 1],
    [':455-458', '', ':515-517', 1],
    [':102', '', ':116', 1],
];

$claimed = [];
$splices = [];
foreach ($bind as [$token, $ctx, $to, $want]) {
    $hits = [];
    $off = 0;
    while (($at = strpos($token === '' ? '¦' : $new, $token, $off)) !== false) {
        $off = $at + 1;
        $overlap = false;
        foreach ($claimed as [$cs, $cl]) {
            if ($at < $cs + $cl && $at + strlen($token) > $cs) {
                $overlap = true;
                break;
            }
        }
        if ($overlap) {
            continue;
        }
        $window = preg_replace('/\[[^\]]*\]/u', '', str_replace(['<', '>'], ['[', ']'],
            substr($new, max(0, $at - 170 * 4), 170 * 4 + strlen($token) + 170 * 4)));
        if (! str_contains($window, $ctx)) {
            continue;
        }
        $hits[] = $at;
    }
    if (count($hits) !== $want) {
        $guard(false, sprintf('%-32s bound %d occurrence(s) by “%s”, expected %d', $token, count($hits), $ctx, $want));
    } else {
        $guard(true, sprintf('%-32s → %-14s ×%d  “%s”', $token, $to, $want, $ctx));
    }
    foreach ($hits as $at) {
        $claimed[] = [$at, strlen($token)];
        $splices[] = [$at, strlen($token), $to];
    }
}
// Anything with the shape of a pointer into these two files that no rule bound is a defect, not a pass.
$left = [];
foreach ([$B, $A] as $prefix) {
    $off = 0;
    while (($at = strpos($new, $prefix, $off)) !== false) {
        $off = $at + 1;
        if (preg_match('/^(BlockingController|AuditRecordStates)\.php:(\d+)(-(\d+))?(\+?)/', substr($new, $at, 60), $m) !== 1) {
            continue;
        }
        $len = strlen($m[0]) - ($m[5] === '+' ? 0 : 0);
        $overlap = false;
        foreach ($claimed as [$cs, $cl]) {
            if ($at < $cs + $cl && $at + strlen(rtrim($m[0], '+')) > $cs) {
                $overlap = true;
                break;
            }
        }
        if (! $overlap) {
            $left[] = rtrim($m[0], '+');
        }
    }
}
$guard($left === [], 'no pointer into either file was left unbound'.($left === [] ? '' : ': '.implode(', ', array_unique($left))));

usort($splices, fn (array $x, array $y): int => $y[0] <=> $x[0]);
foreach ($splices as [$at, $len, $to]) {
    $new = substr_replace($new, $to, $at, $len);
}

// ------------------------------------------------------------------ 5. the revision row carries lane 55
$laneOld = 'The document itself was swept by line, by meaning and by §-reference after every pass in this batch.';
$laneNew = 'Lane 55 then asked the timetable what the desks had never been asked: whose hour is it. '
    .'`detectConflicts()` compared a proposed schedule against the officer and the room, and not against the '
    .'block the schedule belongs to — while the screen that writes it is `/blocking/{block}/schedules`, a form '
    .'whose only job is to add a subject to a block that already has one. It also carried no term, and could not '
    .'have taken one from `schedules`, which has none: the term is on the block, so a meeting from a retired term '
    .'vetoed a save in the term an office was building. Both were ruled on the measurement — a group cannot be in '
    .'two rooms at one hour, and a collision is judged inside the term — and both are counted by the audit '
    .'command, which now runs sixteen rules where it ran fourteen. Measured before the change and after: live '
    .'`ems` holds 9 schedules and 18 meetings with no collision on any reading, as does a schema built from empty '
    .'in CI’s order, so no row was repaired and §28.3 G-25 records the way one would be made. The swap harness '
    .'proved each change moved a verdict, and caught itself doing the same for the third question: the room branch '
    .'refused the double-booked room before this batch too, so its new test is coverage rather than a fix, and the '
    .'harness that had asserted red for it was the thing that was wrong. Re-pointing the pointers into that '
    .'controller found a second, older problem: §17.1 and §17.2 named construct after construct that no longer '
    .'sat at the number given — the schedule save cited forty lines above `storeSchedule()`, the block’s own '
    .'capacity cited at a blank line inside what used to be the conflict builder, the room’s cited beside a '
    .'different check entirely, and the roster filter attributed to the block-create pair — every one of them on '
    .'a live line, so the line sweep cleared all of them and only reading the sentence failed any. Twelve '
    .'pointers were re-taken from the file this batch edited, and one present-tense claim in the §20 table '
    .'inventory (`schedulemeetings`) had been asserting since September that Blocking stored no class sessions at '
    .'all, which 18 meeting rows contradict. '
    .$laneOld;
$guard(substr_count($new, $laneOld) === 1, 'the revision row’s closing sentence is present and unique');
$new = str_replace($laneOld, $laneNew, $new);

$guard(substr_count($new, '>G-25<') === 1, 'the new id is in the table once');
$guard(substr_count($new, 'fourteen rules') === 0, 'no stale fourteen-rule claim remains');
$guard(substr_count($new, 'Blocking has stored no class sessions') === 0, 'the stale schedulemeetings inventory claim is gone');
foreach ([':193-252', ':365+', ':339-363', ':497-530', ':78-91', ':455-458', '415-495', ':467-478', ':461', ':433', ':588', ':102'] as $gone) {
    $guard(substr_count($new, $gone) === 0, "the pre-pass pointer {$gone} no longer appears");
}
$guard(strlen($new) > strlen($xml), 'the edited bytes grew by '.(strlen($new) - strlen($xml)));

if ($fails > 0) {
    printf("\n%d guard(s) failed — NOTHING WRITTEN.\n", $fails);
    exit(1);
}
if (! $apply) {
    printf("\nDRY RUN. One row (%d bytes), register %d rows, sixteen rules, %d pointer splices, suite %d/%d. Re-run with --apply.\n",
        strlen($clone), $dataRows, count($splices), $tests, $assertions);
    exit(0);
}

$backup = $root.'/storage/app/scratch/ems-docx-pre-pass160.docx';
copy($src, $backup);
$out = $root.'/Documentation/.ems-docx-pass160.tmp';
$write = new ZipArchive();
if ($write->open($out, ZipArchive::CREATE) !== true) {
    echo "REFUSED: cannot build the replacement archive.\n";
    exit(1);
}
$read = new ZipArchive();
$read->open($src);
for ($i = 0; $i < $read->numFiles; $i++) {
    $name = $read->getNameIndex($i);
    $write->addFromString($name, $name === 'word/document.xml' ? $new : $read->getFromIndex($i));
}
$write->close();
$read->close();
if (! rename($out, $src)) {
    echo "REFUSED: could not move the new archive; backup at {$backup}.\n";
    exit(1);
}
$check = new ZipArchive();
$check->open($src);
$guard($check->numFiles === $partCount, 'the archive still holds '.$partCount.' parts');
$round = (string) $check->getFromName('word/document.xml');
$check->close();
$guard(hash('sha256', $round) === hash('sha256', $new), 'word/document.xml on disk is the bytes this pass built');
if ($fails > 0) {
    printf("\n%d guard(s) failed after writing; backup at %s\n", $fails, $backup);
    exit(1);
}

printf("\nPass 160 written; backup at %s\n", $backup);
exit(0);
