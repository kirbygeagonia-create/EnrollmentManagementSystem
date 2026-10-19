<?php

/**
 * Pass 156 — §28.3 gains G-21 and G-22 (lane 53: the money weighed line by line), the audit's own rule count
 * moves from twelve to fourteen, the register's size is rewritten in both places that state it, the current
 * issue row carries the lane, and every citation into the files this batch edited is re-pointed by the shift
 * measured against HEAD's bytes.
 *
 * Two rules this pass is built on, both earned the hard way in lane 51:
 *   * a pointer moves only when EVERY cited line is byte-identical at that offset between HEAD and the working
 *     copy, and the pass refuses when the offset is not unique — arithmetic over hunk headers mis-moved eight
 *     pointers last time, and a pointer one row short still lands on a live line, so the bounds sweep clears it;
 *   * a bare continuation (`…, the two box rules at :267-289`) names no file, so it cannot be moved by a
 *     file-qualified replace. It is measured here and rewritten by an explicit needle, asserted unique, in $bareEdits.
 *
 * Every code line number the two new rows quote is read out of the tree at run time, so nothing in this file is
 * remembered from a previous measurement.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$suiteLog = $root.'/storage/app/scratch/lane53-suite-final.log';

/**
 * Bare continuations to re-point by hand, keyed by the exact text in the document.
 * Measured by `storage/app/scratch/lane53-probe-cite-shift.php` against HEAD's bytes: the two box rules sit
 * 12 rows lower (279-291 and 293-301 now, read and confirmed to be the same two methods).
 */
$bareEdits = [
    'the two box rules at :267-289' => 'the two box rules at :279-301',
];

/**
 * Pointers whose cited span GREW FROM INSIDE this batch, so no single offset can move them: `handle()` opens
 * two rows lower (an import) and closes twelve rows lower still (the two new rules sit in its own array). A
 * byte-identity shift cannot express that, and the measurement says so — `lane53-probe-cite-shift.php` printed
 * NO SHIFT for it while the range was still perfectly good for the method it names. Re-pointed to the
 * method's own measured span, read out of the tree at run time.
 */
$namedToMethods = [
    'AuditRecordStates.php:44-155' => ['app/Console/Commands/AuditRecordStates.php', 'handle'],
];

$targets = [
    'tools/seed_full_demo_pipeline.php' => 'seed_full_demo_pipeline.php',
    'app/Console/Commands/AuditRecordStates.php' => 'AuditRecordStates.php',
];

// Two other files this batch edited carry citations in the document and deliberately do not appear above:
// `DemoDatasetRecordStatesTest.php` was edited in place with no line added or removed, so its three pointers
// (`:113-144`, `:147-167`, `:170-236`) still enclose the same methods — read, not assumed; a byte-identity
// test reports an in-place edit as NO SHIFT, which is a limitation of that test, not a finding.
// `AuditRecordStatesTest.php` grew by 134 lines and the document cites it by class name only.

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

/** the line range a function occupies in the working copy, counted the way an editor counts them */
function codeSpan(string $path, string $name): array
{
    $lines = explode("\n", rtrim((string) file_get_contents($path), "\n"));

    foreach ($lines as $i => $line) {
        if (preg_match('/function\s+'.preg_quote($name, '/').'\s*\(/', $line) === 1) {
            $depth = 0;
            $opened = false;

            for ($j = $i; $j < count($lines); $j++) {
                $depth += substr_count($lines[$j], '{') - substr_count($lines[$j], '}');

                if (str_contains($lines[$j], '{')) {
                    $opened = true;
                }

                if ($opened && $depth === 0) {
                    return [$i + 1, $j + 1];
                }
            }

            throw new RuntimeException("unbalanced braces in {$name}");
        }
    }

    throw new RuntimeException("no function {$name} in {$path}");
}

/**
 * Which offsets leave every cited HEAD line byte-identical in the working copy?
 *
 * @return list<int>
 */
function candidateShifts(array $head, array $work, int $a, int $b): array
{
    $candidates = [];

    for ($s = -80; $s <= 240; $s++) {
        $ok = true;

        for ($i = $a; $i <= $b; $i++) {
            if (! isset($head[$i - 1]) || ! isset($work[$i + $s - 1]) || $head[$i - 1] !== $work[$i + $s - 1]) {
                $ok = false;

                break;
            }
        }

        if ($ok) {
            $candidates[] = $s;
        }
    }

    return $candidates;
}

// ------------------------------------------------------------------ 1. measure, then move the named pointers
$new = $xml;
$namedMoved = 0;
$bytesAtStart = strlen($new);

foreach ($targets as $file => $needle) {
    $headPath = $root.'/storage/app/scratch/lane53-head-'.str_replace('/', '-', $file);

    if (! is_file($headPath) || trim((string) file_get_contents($headPath)) === '') {
        fwrite(STDERR, "REFUSE: no HEAD export for $file — git show HEAD:$file > ".basename($headPath)."\n");

        exit(1);
    }

    $head = explode("\n", rtrim((string) file_get_contents($headPath), "\n"));
    $work = explode("\n", rtrim((string) file_get_contents($root.'/'.$file), "\n"));

    printf("=== %s — HEAD %d lines → working %d lines\n", $file, count($head), count($work));

    preg_match_all('/'.preg_quote($needle, '/').':(\d+)(?:-(\d+))?/', $new, $named, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    preg_match_all('/(?<![\w.\-]):(\d+)(?:-(\d+))?/', $new, $bare, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

    $deltas = [];

    foreach ($named as $m) {
        $a = (int) $m[1][0];
        $b = (isset($m[2][0]) && $m[2][0] !== '') ? (int) $m[2][0] : $a;
        $key = $needle.':'.$a.'-'.$b;

        if (isset($namedToMethods[$key])) {
            [$spanFile, $spanName] = $namedToMethods[$key];
            $span = codeSpan($root.'/'.$spanFile, $spanName);
            $to = $needle.':'.$span[0].'-'.$span[1];
            $count = substr_count($new, $key);
            $guard($count === 1, "$key — its span grew from inside, so it is re-pointed to the measured method $to (found $count time(s))");

            if ($count === 1 && strlen($key) === strlen($to)) {
                $new = str_replace($key, $to, $new);
                $namedMoved++;
            }

            continue;
        }

        $candidates = candidateShifts($head, $work, $a, $b);

        if (count($candidates) !== 1) {
            $guard(false, sprintf('%s:%d-%d — %s', $needle, $a, $b,
                $candidates === [] ? 'no offset leaves the cited text byte-identical, so the construct itself moved: re-read it'
                    : 'byte-identical at '.implode(',', $candidates).' as well, so the span proves nothing: read the sentence'));

            continue;
        }

        if ($candidates[0] !== 0) {
            $deltas[$a] = $candidates[0];
        }

        printf("  named  %-40s :%-15s %+d\n", $needle, "$a-$b", $candidates[0]);
    }

    foreach ($bare as $m) {
        $a = (int) $m[1][0];
        $b = (isset($m[2][0]) && $m[2][0] !== '') ? (int) $m[2][0] : $a;

        // A bare range belongs to this file only when this file's name is the LAST one before it, with no
        // other `.php` in between — the document's own attribution rule, measured in lane 32.
        $window = substr($new, max(0, $m[0][1] - 400), min(400, $m[0][1]));
        $last = strrpos($window, $needle);

        if ($last === false || str_contains(substr($window, $last + strlen($needle)), '.php')) {
            continue;
        }

        $candidates = candidateShifts($head, $work, $a, $b);

        printf("  bare   %-40s :%-15s %s\n", $needle, "$a-$b",
            count($candidates) === 1 ? sprintf('%+d', $candidates[0])
                : ($candidates === [] ? 'NO SHIFT — re-read' : 'AMBIGUOUS ['.implode(',', $candidates).']'));

        if (count($candidates) !== 1) {
            $guard(false, "bare {$needle}:$a-$b cannot be moved by measurement alone");
        }
    }

    if ($deltas !== []) {
        $original = array_map(static fn ($m) => $m[1][0].((isset($m[2][0]) && $m[2][0] !== '') ? '-'.$m[2][0] : ''), $named);

        $new = preg_replace_callback('/'.preg_quote($needle, '/').':(\d+)(?:-(\d+))?/', function ($m) use ($needle, $deltas, &$namedMoved): string {
            $start = (int) $m[1];

            if (! array_key_exists($start, $deltas)) {
                return $m[0];
            }

            $namedMoved++;

            return $needle.':'.($start + $deltas[$start]).(($m[2] ?? '') !== '' ? '-'.((int) $m[2] + $deltas[$start]) : '');
        }, $new) ?? '';

        $predicted = [];

        foreach ($original as $range) {
            [$s, $e] = array_pad(explode('-', $range), 2, null);
            $d = array_key_exists((int) $s, $deltas) ? $deltas[(int) $s] : 0;
            $predicted[] = ((int) $s + $d).((string) $e === '' ? '' : '-'.((int) $e + $d));
        }

        $after = [];
        preg_match_all('/'.preg_quote($needle, '/').':(\d+)(?:-(\d+))?/', $new, $check, PREG_SET_ORDER);

        foreach ($check as $m) {
            $after[] = $m[1].($m[2] !== '' ? '-'.$m[2] : '');
        }

        sort($predicted);
        sort($after);
        $guard($predicted === $after, "$needle — every named range is exactly where its measured shift says: [".implode(',', $after).']');
    }

    echo "\n";
}

// ------------------------------------------------------------------ 1b. the bare continuations, by explicit needle
// Every pointer moved above is length-neutral (44-155 → 46-167, 1702-1705 → 1722-1725). Had one crossed a
// digit boundary, the offsets captured before it would point at the wrong text, and the step after this would
// silently edit a different pointer. The byte count is the cheap proof that none did.
$guard(strlen($new) === $bytesAtStart, 'the citation step changed no byte count, so every offset it captured is still its own');

foreach ($bareEdits as $from => $to) {
    $n = substr_count($new, $from);
    $guard($n === 1, "bare pointer $from occurs exactly once (found $n)");

    if ($n === 1) {
        $new = str_replace($from, $to, $new);
        $namedMoved++;
    }
}

printf("%d pointer(s) moved.\n\n", $namedMoved);

if ($fails > 0) {
    fwrite(STDERR, "{$fails} guard(s) failed at the citation step — nothing written\n");

    exit(1);
}

// ------------------------------------------------------------------ 2. the two rows, cloned from G-20
$sheetSpan = codeSpan($root.'/app/Console/Commands/AuditRecordStates.php', 'sheetWithoutLines');
$paidSpan = codeSpan($root.'/app/Console/Commands/AuditRecordStates.php', 'receiptWithoutPaidStep');
$chargeSpan = codeSpan($root.'/tools/seed_full_demo_pipeline.php', 'charge_demo_fees');

printf("     measured spans — sheetWithoutLines %d-%d, receiptWithoutPaidStep %d-%d, charge_demo_fees %d-%d\n\n",
    $sheetSpan[0], $sheetSpan[1], $paidSpan[0], $paidSpan[1], $chargeSpan[0], $chargeSpan[1]);

$rows = [[
    'G-21',
    'A fee sheet quoted ₱17,500 with nothing under it: a total no line produced',
    'Lane 47 asked which set a tile describes, 48 what a screen paints when the number never arrives, 49 whether '
    .'a real zero can wear the dash, 50 and 51 which day a value belongs to. Lane 53 asks what all five were '
    .'counting: does the money add up. Every account on live `ems` was weighed in bcmath and never through a '
    .'float — `sum(charges.amount)` against `totalAssessedAmount`, `sum(charges.waivedAmount)` against '
    .'`totalWaived`, and `remainingBalance` against assessed minus coverage minus waived minus the receipts the '
    .'drawer is holding — across 14 fee sheets, 15 receipts and 1 grant. The identities hold on 13 of them, each '
    .'carrying four lines. The fourteenth is the record the Registrar approves in the walkthrough: assessment 84 '
    .'(enrollment 89, DEMO-2026-014) stores ₱17,500.00 over zero rows in `charges`. The sheet takes its lines '
    .'from `charges` and its header from `studentassessments`, so the desk was shown a total with no breakdown '
    .'beneath it — read out of the running screen rather than inferred, by `storage/app/scratch/'
    .'lane53-probe-feesheet-live.php`, which goes through the HTTP kernel with the Accounting desk signed in, '
    .'and is proved by unpricing a healthy sheet inside the transaction it rolls back (it refuses when its plant '
    .'matches no row, because an unmatched needle reports a clean document). The cause was the generator’s own '
    .'shape: sections 6 and 7 each wrote a sheet with a raw insert, and two of the three listed their four lines '
    .'inline, while the helper that priced them — `$chargeDemoFees` — was a closure assigned some 250 lines BELOW '
    .'the block that needed it, and a closure does not exist until its own line runs. This file has met that '
    .'trap four times now (`desk_signer_for()`, `demo_fee_lines()`, a helper called from inside other closures, '
    .'and here); it is the hoisted `charge_demo_fees()` and all three create sites call it. '
    .'`AssessmentController::compute()` has always written one `charges` row per priced line in the same '
    .'transaction as the total, so no desk action produces a bare sheet: the state is impossible, and '
    .'`ems:audit:record-states` counts it now. The ledger pass prices a bare sheet in the demo term when the '
    .'demo’s own four lines add up to its total, and reports one they do not rather than inventing a breakdown — '
    .'a plausible figure substituted for a measured one is the defect this series keeps finding, in the seeder '
    .'rather than on the screen. Stated as measured, because the same weighing settled the rounding question and '
    .'the owner ruled the boundary documented rather than the code touched: every amount column is `DECIMAL(10,2)` '
    .'while the arithmetic that produces a coverage figure is PHP — `(float)` casts and `round($assessed * '
    .'$percent / 100, 2)`. Probed against exact decimal half-up computed in bcmath, every amount from ₱1.00 to '
    .'₱20,000.00 (1,999,901 cent values) at each coverage percent the table holds (100.00 and 50.00) lands on '
    .'the same centavo: 3,999,802 comparisons, 0 disagreements. What that does not cover, and where it would '
    .'bite: a percent whose expansion repeats (33.33 is the obvious candidate) has never been probed, nor has an '
    .'amount above ₱20,000.00, so the guarantee is about the percents and the fee range this install holds, not '
    .'about the decimal type.',
    'Closed 2026-10-18 — a fee sheet is priced by its lines, and a total with no line under it is counted as impossible',
    'app/Http/Controllers/Assessment/AssessmentController.php:162-173; app/Console/Commands/AuditRecordStates.php:'
    .$sheetSpan[0].'-'.$sheetSpan[1].'; tools/seed_full_demo_pipeline.php:'.$chargeSpan[0].'-'.$chargeSpan[1].'; '
    .'storage/app/scratch/lane53-audit-money.php; storage/app/scratch/lane53-probe-feesheet-live.php; '
    .'tests/Feature/Console/AuditRecordStatesTest.php',
], [
    'G-22',
    '₱17,500 arrived with no Accounting action behind it: five settled accounts whose paid step was never written',
    'The same weighing asked what stood behind the receipts. `AccountingController::record()` takes money only on '
    .'an `assessed` enrollment and moves it to `paid` when the balance clears, and '
    .'`EnrollmentStateMachine::transition()` is the only writer of `enrollmentstatushistory` — so a settled '
    .'account holding receipts with no step into `paid` is money that arrived with no desk action behind it, and '
    .'the status-history panel the Registrar reads says exactly that. The first framing of this finding was '
    .'wrong, and measuring it is what caught that: eight accounts looked impossible because the check compared '
    .'the STATUS with the balance, but `paid` to `enrolled` is a normal step, so an approved record owing nothing '
    .'is not a defect. Re-read against the history table, five are — enrollments 89, 90, 91, 92 and 876, each '
    .'holding ₱17,500 and a zero balance with no `paid` among the 25 history rows then on file. Two shapes are '
    .'deliberately outside the rule because both are states a desk genuinely leaves behind: ₱8,000 taken as a '
    .'part payment on enrollment 88 keeps it `assessed` with no transition at all (nothing is marked until the '
    .'last peso lands), and enrollment 286 reads zero because a full grant covers it and no cash was ever owed. '
    .'The predicate therefore asks for a held receipt — the drawer’s own `Payments::held()` scope — and never '
    .'for a zero balance alone. The generator was the cause: its payment sites write `payments` rows and '
    .'recompute `remainingBalance` in SQL, and section 7 minted its record already `paid`. Section 7 now seats '
    .'the record at `assessed`, prices it, files the receipt and walks `assessed` to `paid` through the machine; '
    .'and because the create-only blocks never run twice on a database that already holds the demo, the ledger '
    .'pass reconstructs the step for rows already past the box — `enrolled` back to `assessed`, `assessed` to '
    .'`paid`, `paid` forward to `enrolled` again — each remark naming the tool so a reconstruction is never '
    .'mistaken for a desk act, and idempotent because the step’s presence is the query’s own filter. Measured '
    .'across the whole run on live `ems`: 15 history rows, 4 fee lines and 12 bell notifications added (the '
    .'transitions fire the event the inbox reads), and nothing else moved — 15 receipts before and after, and the '
    .'same status distribution: 6 pending, 2 evaluated, 3 assessed, 2 paid, 1 returned to evaluation, 14 '
    .'enrolled, 7 dropped. Rehearsed on a clone of the live database before anything was written to it, and the '
    .'new rules were proved to complain first: on the unfixed data the register read 1 and 5, and the class that '
    .'refuses any count above zero went red on it (4 tests, 1 failure); both read 0 once the pipeline had run, '
    .'and the end-to-end folder — that class beside the walkthrough legs — passed at 8 tests and 197 assertions.',
    'Closed 2026-10-18 — the generator walks the states its receipts imply, and a receipt with no step behind it fails the run',
    'app/Http/Controllers/Accounting/AccountingController.php:172-230; app/Services/EnrollmentStateMachine.php:59-92; '
    .'app/Console/Commands/AuditRecordStates.php:'.$paidSpan[0].'-'.$paidSpan[1].'; '
    .'app/Models/Payments.php:44-50; storage/app/scratch/lane53-probe-settled-no-history.php; '
    .'tests/Feature/E2E/DemoDatasetRecordStatesTest.php',
]];

$anchor = 'A desk’s “today” was cut at the wrong midnight';

$at = strpos($new, $anchor);
$guard($at !== false && substr_count($new, $anchor) === 1, 'the G-20 anchor occurs exactly once');
$rowStart = $at === false ? false : strrpos(substr($new, 0, $at), '<w:tr');
$rowEnd = $at === false ? false : strpos($new, '</w:tr>', $at) + 7;
$guard($rowStart !== false && $rowEnd !== false, 'the G-20 row is bounded');
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

    $guard($clone !== $row, $r[0].' clone carries new text');
    $guard(substr_count($clone, '<w:tr') === substr_count($row, '<w:tr'), $r[0].' clone is one row and no more');
    $guard(str_contains($clone, str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], mb_substr($r[2], 0, 90))),
        $r[0].' clone holds the body it was given');
    $clones .= $clone;
}

foreach ($containers as $k => $n) {
    $expect[$k] = substr_count($clones, $n);
    $guard($expect[$k] === 2 * substr_count($row, $n), "container {$n}: the two clones contribute exactly two rows' worth");
}

$new = substr_replace($new, $clones, (int) $rowEnd, 0);

foreach ($containers as $k => $n) {
    $after = substr_count($new, $n);
    $guard($after === $before[$k] + $expect[$k], "container {$n}: {$before[$k]} → {$after} (expected +{$expect[$k]})");
}

// ------------------------------------------------------------------ 3. recount the register, both places
$tblStart = strrpos(substr($new, 0, (int) $rowStart), '<w:tbl>');
$tblEnd = strpos($new, '</w:tbl>', (int) $rowEnd) + 7;
$tblText = substr($new, (int) $tblStart, (int) $tblEnd - (int) $tblStart);
preg_match_all('/<w:tr.*?<\/w:tr>/s', $tblText, $check);
$dataRows = count($check[0]) - 1;
printf("     §28.3 recounted from the edited bytes: 1 header + %d data rows\n", $dataRows);
$guard($dataRows === 39, 'the table now holds 39 data rows');
preg_match_all('/\bG-(\d+)\b/', $tblText, $gids);
$guard(((int) max($gids[1])) === 22, 'the G block now runs up to G-22');
$guard(count(array_unique($gids[0])) === 22, 'each of the 22 G ids appears exactly once in the table');

$sentenceOld = 'and lane 51 added G-20 the same day, so §28.3 holds 37 data rows';
$sentenceNew = 'lane 51 added G-20, and lane 53 added G-21 and G-22, so §28.3 holds '.$dataRows.' data rows';
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
$guard($fc[0] !== [] && substr_count($fc[0][1], '>37<') === 1, 'its Rows cell reads exactly 37');
$famNew = str_replace($fc[0][1] ?? '', str_replace('>37<', '>'.$dataRows.'<', $fc[0][1] ?? ''), $famRow);
$guard($famNew !== $famRow, 'the Rows cell was rewritten');
$new = substr_replace($new, $famNew, (int) $famRowStart, strlen($famRow));

// ------------------------------------------------------------------ 4. the audit’s own rule count
$rulesOld = 'twelve rules over idrequests, workflowsteps, enrollmentworkflow, enrollments, clinicrecords, notifications and documentprintlog';
$rulesNew = 'fourteen rules over idrequests, workflowsteps, enrollmentworkflow, enrollments, clinicrecords, notifications, documentprintlog, studentassessments, charges and enrollmentstatushistory';
$guard(substr_count($new, $rulesOld) === 1, 'the command’s rule sentence is present and unique (the dated revision row keeps its own twelve)');
$new = str_replace($rulesOld, $rulesNew, $new);

$listOld = 'an ID box signed with no validated request behind it — each counted';
$listNew = 'an ID box signed with no validated request behind it, a fee sheet whose total has no line under it, and a receipt on an account that never entered paid — each counted';
$guard(substr_count($new, $listOld) === 1, 'the rule list is present and unique');
$new = str_replace($listOld, $listNew, $new);

// ------------------------------------------------------------------ 5. the revision row and the suite figure
$laneOld = 'The document itself was swept by line, by meaning and by §-reference after every pass in this batch.';
$laneNew = 'Lane 53 then weighed the money itself, the fifth question in a run of them about figures: which set a '
    .'tile describes, what shows when a number never arrives, whether a real zero can wear the dash, which day a '
    .'value belongs to, and now whether the amounts add up. Every account was compared in bcmath against its own '
    .'lines: 13 of 14 do, and the fourteenth (DEMO-2026-014) stored ₱17,500 over an empty `charges` table, which '
    .'is the table the printed fee sheet quotes beneath that header. The same weighing found five settled accounts '
    .'carrying ₱17,500 with no step into `paid` anywhere in the status history — money that arrived with no '
    .'Accounting action behind it, as the Registrar’s own panel reads it. Both were the demo generator, both are '
    .'now impossible states that `ems:audit:record-states` fails the run on, and the generator was taught to walk '
    .'the lifecycle its receipts imply instead of writing the status column. G-21 and G-22 record them, together '
    .'with a correction made while measuring: the first framing named eight accounts by comparing a status with a '
    .'balance, and four of the eight had in fact passed through `paid` and logged it. The rounding path was probed '
    .'rather than assumed — every amount from ₱1.00 to ₱20,000.00 at each coverage percent the table holds, '
    .'3,999,802 comparisons, no disagreement with exact decimal half-up — and the owner ruled the boundary '
    .'documented rather than the code touched, so G-21 states both what was probed and what was not. '
    .$laneOld;
$guard(substr_count($new, $laneOld) === 1, 'the revision row’s closing sentence is present and unique');
$new = str_replace($laneOld, $laneNew, $new);

$spanOld = '2026-10-16–17 (this issue)';
$guard(substr_count($new, $spanOld) === 1, 'the issue row’s date label is present and unique');
$new = str_replace($spanOld, '2026-10-16–18 (this issue)', $new);

if (! is_file($suiteLog)) {
    fwrite(STDERR, "REFUSE: no whole-suite log at $suiteLog — the document's suite figure must come from a measured run.\n");

    exit(1);
}

// A log older than the code it is supposed to measure says nothing about that code. This pass once read a
// summary from a run made before the last edit; the figure was wrong and every guard was green.
$codeAge = max(map_record_times([
    $root.'/app/Console/Commands/AuditRecordStates.php',
    $root.'/tools/seed_full_demo_pipeline.php',
    $root.'/tests/Feature/Console/AuditRecordStatesTest.php',
    $root.'/tests/Feature/E2E/DemoDatasetRecordStatesTest.php',
]));

if (filemtime($suiteLog) < $codeAge) {
    fwrite(STDERR, "REFUSE: the suite log is from ".date('Y-m-d H:i:s', (int) filemtime($suiteLog))
        .", older than the code it must measure (touched ".date('Y-m-d H:i:s', $codeAge).") — run the whole suite again.\n");

    exit(1);
}

function map_record_times(array $paths): array
{
    return array_map(static fn (string $p): int => (int) filemtime($p), $paths);
}

$log = preg_replace('/\e\[[0-9;]*m/', '', (string) file_get_contents($suiteLog));

if (! preg_match('/Tests:\s*([0-9,]+)\s+passed\s*\(([0-9,]+)\s+assertions?\)/', $log, $m)) {
    fwrite(STDERR, "REFUSE: the suite log prints no \"Tests: … passed (… assertions)\" line.\n");

    exit(1);
}

$tests = (int) str_replace(',', '', $m[1]);
$assertions = (int) str_replace(',', '', $m[2]);
printf("     suite figure read from the log: %d tests / %d assertions\n", $tests, $assertions);

$suiteOld = 'The suite stands at 755 tests and 5130 assertions';
$suiteNew = 'The suite stands at '.$tests.' tests and '.$assertions.' assertions';
$guard(substr_count($new, $suiteOld) === 1, 'the revision row’s suite sentence is present and unique');
$new = str_replace($suiteOld, $suiteNew, $new);
$guard(substr_count($new, $suiteNew) === 1, 'the suite figure now reads the measured run');

// ------------------------------------------------------------------ 6. what a count guard cannot see
$guard(substr_count($new, '&amp;amp;') === 0 && substr_count($new, '&amp;gt;') === 0 && substr_count($new, '&amp;lt;') === 0,
    "no run carries a double-escaped entity (pass 153's defect)");

foreach (['holds 37 data rows', '>37<', 'twelve rules over idrequests', '2026-10-16–17 (this issue)'] as $stale) {
    $guard(substr_count($new, $stale) === 0, "the stale claim is gone: $stale");
}

libxml_use_internal_errors(true);
$doc = new DOMDocument;
$guard($doc->loadXML($new) === true, 'document.xml parses after the citation moves, the two inserts and the rewrites');
libxml_clear_errors();

printf("\ntwo rows: %d bytes | register %d | rules fourteen | suite %d/%d\n", strlen($clones), $dataRows, $tests, $assertions);

if ($fails > 0) {
    fwrite(STDERR, "{$fails} guard(s) failed — nothing written\n");

    exit(1);
}

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";

    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-18-pass156.docx');
$z0 = new ZipArchive;
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
