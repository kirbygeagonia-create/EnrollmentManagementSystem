<?php

/**
 * Pass 158 — §28.3 gains G-23 and G-24 (lane 54: the generator's ledger weighed against the desk's own rules),
 * G-11 is closed by the route its own row named, the issue row's host-clock claim is corrected by what lane 54c
 * measured, the register's size is rewritten in both places that state it, and every citation into the files
 * this batch edited is re-pointed by the shift proved against HEAD's bytes.
 *
 * Two rules this pass is built on, both earned in lanes 51 and 53:
 *   * a pointer moves only when EVERY cited line is byte-identical at that offset between HEAD and the working
 *     copy, and a span that GREW FROM INSIDE cannot be moved by a shift at all — it is re-pointed to the
 *     construct's own measured span (`checks()`, the G-2 population), which is what `lane53-probe-cite-shift`
 *     reported as NO SHIFT while the range was still good for the method it names;
 *   * every line number quoted by the new rows is read out of the tree at run time, so nothing here is
 *     remembered from a previous measurement.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$headSha = 'e40452f';
$suiteLog = $root.'/storage/app/scratch/lane54-suite-sqlite.txt';

$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $fails++;
    }
};

/** The suite's own summary line, read from the log this batch produced — never from memory. */
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
if ($tests === 0 || $assertions === 0 || ! str_contains($log, 'SUITE_EXIT=0')) {
    echo "REFUSED: the suite log does not carry a green `Tests: N passed (M assertions)` line — nothing is written.\n";
    exit(1);
}
printf("Suite figure read from the log: %d tests, %d assertions.\n", $tests, $assertions);

/**
 * HEAD's bytes for a file, read from an export made in bash.
 *
 * PHP cannot reach `git` through `system()`/`shell_exec()` on this box (measured in lane 50), so a probe that
 * wants HEAD must read an exported copy — and refuse when one is missing rather than concluding from an empty
 * array. Export first:
 *   git show e40452f:tools/seed_full_demo_pipeline.php > storage/app/scratch/lane54-head-seed_full_demo_pipeline.php
 *   git show e40452f:app/Console/Commands/Preflight.php  > storage/app/scratch/lane54-head-Preflight.php
 */
$headFile = function (string $path) use ($root): array {
    $tmp = $root.'/storage/app/scratch/lane54-head-'.basename($path);
    $lines = @file($tmp);
    if ($lines === false || $lines === []) {
        throw new RuntimeException("no HEAD export for {$path} — expected {$tmp}");
    }

    return $lines;
};

$curFile = function (string $path) use ($root): array {
    $lines = file($root.'/'.$path);
    if ($lines === false) {
        throw new RuntimeException("cannot read {$path}");
    }

    return $lines;
};

/**
 * The single shift under which every line of the cited range is byte-identical, or null.
 *
 * Null means the range did not merely move — it grew or shrank from inside, and arithmetic over hunk headers
 * mis-moves pointers exactly like this.
 */
$shiftOf = function (array $head, array $cur, int $from, int $to): ?int {
    $found = [];
    foreach ($head as $offset => $hLine) {
        $line = $offset + 1;
        if ($line < $from || $line > $to) {
            continue;
        }
        foreach ([-14, -7, -3, -1, 0, 1, 2, 3, 5, 10, 12, 13, 154, 160] as $s) {
            $j = $line - 1 + $s;
            if ($j < 0 || ! isset($cur[$j])) {
                continue;
            }
            if (rtrim($cur[$j], "\r\n") === rtrim($hLine, "\r\n")) {
                $found[$s] = ($found[$s] ?? 0) + 1;
            }
        }
    }
    $want = $to - $from + 1;
    $ok = array_filter($found, fn (int $c): bool => $c === $want);
    if (count($ok) !== 1) {
        return null;
    }

    return (int) array_key_first($ok);
};

/** A function's own measured span in the working copy, by name. */
$functionSpan = function (array $lines, string $name): array {
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

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$partCount = $zip->numFiles;
$xml = $zip->getFromName('word/document.xml');
$zip->close();
if ($xml === false || strlen($xml) < 100000) {
    echo "REFUSED: word/document.xml did not read.\n";
    exit(1);
}
$new = $xml;
printf("\nOpened word/document.xml: %d bytes over %d parts.\n\n", strlen($xml), $partCount);

$pipeline = $curFile('tools/seed_full_demo_pipeline.php');
$pipelineHead = $headFile('tools/seed_full_demo_pipeline.php');
$preflight = $curFile('app/Console/Commands/Preflight.php');
$preflightHead = $headFile('app/Console/Commands/Preflight.php');

// ------------------------------------------------------------------ 1. the two new rows, cloned from G-22
[$heldFrom, $heldTo] = $functionSpan($pipeline, 'demo_held');
[$seedFrom, $seedTo] = $functionSpan($pipeline, 'record_demo_payment');
printf("     demo_held() measures :%d-%d | record_demo_payment() :%d-%d\n", $heldFrom, $heldTo, $seedFrom, $seedTo);

$g23 = [
    'G-23',
    'The demo generator measured money the desk does not: five sums read `paid` receipts only',
    'Five places in `tools/seed_full_demo_pipeline.php` added up `paymentStatus = \'paid\'` by hand to decide '
        .'what an account had been given. The application asks a different question: `Studentassessments::outstandingBalance()` '
        .'sums `Payments::held()`, which is `paid` *or* `partial`, and `AccountingController::record()` marks a '
        .'receipt `partial` whenever what it took left a balance (ruling 13). So the generator’s figure and the '
        .'desk’s figure part company on any account holding an installment: the ledger pass would rewrite '
        .'`remainingBalance` from the paid-only sum — putting the amount back to what the cashier had already '
        .'part-collected — and the shortfall loop beside it would then take a second receipt for money nobody '
        .'handed over. Measured rather than argued, on a schema built in CI’s own order: an installment of '
        .'₱2,000.00 on DEMO-2026-012 left the sheet storing ₱15,500.00, and the paid-only tool moved it to '
        .'₱17,500.00. Nothing in the suite could have shown this, because the tool never writes a `partial` row '
        .'itself (that is §28.3 G-24) and live `ems` held none on 2026-10-18, so the divergence needed a desk act '
        .'to exist. Fixed by one helper asked of the application — `demo_held()` reads `Payments::held()` and all '
        .'four sums and the receipt split now call it, instead of restating what money held means — and proved '
        .'both ways: the defect put back into a copy of the tool reproduces the erasure, the real tool leaves the '
        .'installment standing and the run still exits 0.',
    'Closed in the same pass. `tools/seed_full_demo_pipeline.php:'.$heldFrom.'-'.$heldTo.'` is the helper; its '
        .'call sites are the two create blocks, the shortfall loop and the ledger recompute.',
    '§17 (ruling 13, the part payment), §28.3 G-22; found 2026-10-18 by '
        .'storage/app/scratch/lane54-live-ledger-baseline.php and proved by '
        .'storage/app/scratch/lane54e-installment-survives.php',
];

$g24 = [
    'G-24',
    'A seeded part payment reads `paid`, which is a receipt no desk can write',
    'The cashier’s demo row is described in the tool’s own output as “PARTIAL payment (₱8,000 paid, ₱10,500 '
        .'balance)”, and the money is right: ₱8,000.00 held against a ₱18,500.00 sheet leaves ₱10,500.00. The '
        .'receipt is not. It is written with `paymentStatus = \'paid\'`, and `AccountingController::record()` '
        .'cannot produce that pair — it takes the money, reads the balance back through the model, and marks the '
        .'OR `partial` if anything is still owed, which is the whole content of ruling 13: before it, every '
        .'receipt read `paid` and a student who had paid ₱2,000 of a ₱7,500 account held a paper claiming the '
        .'account was settled, while `partial` was a value no desk could produce. Measured on live `ems` 2026-10-18 '
        .'(payment 83, DEMO-OR-0002, ₱8,000.00, sheet owing ₱10,500.00) and on a schema built from empty in CI’s '
        .'order (payment 2, same OR, same figures), so it is the generator, not one repaired box. '
        .'`ems:audit:record-states` runs fourteen rules over these tables and reads 0 on every one of them, '
        .'because none asks whether a receipt’s own status agrees with the balance it left. Left OPEN and not '
        .'changed here: closing it is the register’s usual pair — the generator marks by the balance the model '
        .'computes, the audit gains the fifteenth rule, and the walkthrough’s Cashier line is re-read — and each '
        .'of those is a decision about what the demo is supposed to show, which is the owner’s, not a tool’s.',
    'Owner — mark the seeded receipt `partial` in `record_demo_payment()` ('
        .'tools/seed_full_demo_pipeline.php:'.$seedFrom.'-'.$seedTo.') and the hand-written block above it, add '
        .'the rule to `ems:audit:record-states`, or leave the demo’s installment as a receipt that reads settled '
        .'and say so in the guide.',
    '§17 (ruling 13), §28.3 G-23, §28.3 G-22; found 2026-10-18 while proving G-23',
];

$at = strpos($new, '>G-22<');
$guard($at !== false && substr_count($new, '>G-22<') === 1, 'the last existing row is findable by its own id cell');
$rowStart = $at === false ? false : strrpos(substr($new, 0, $at), '<w:tr');
$rowEnd = $at === false ? false : strpos($new, '</w:tr>', $at) + 7;
$guard($rowStart !== false && substr($new, (int) $rowStart - 7, 7) === '</w:tr>', 'a sibling row precedes it');
$guard($rowEnd !== false && substr($new, (int) $rowEnd, 6) === '<w:tr>', 'the row is the table’s last and the clones land inside it');

$row = substr($new, (int) $rowStart, (int) $rowEnd - (int) $rowStart);
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cells);
$guard(count($cells[0]) === 5, 'the template row has five cells');

$setText = function (string $cell, string $text): string {
    if (! preg_match('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell, $m, PREG_OFFSET_CAPTURE)) {
        throw new RuntimeException('the cell carries no text run to replace');
    }
    $escaped = str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);

    return substr($cell, 0, $m[1][1]).$escaped.substr($cell, $m[1][1] + strlen($m[1][0]));
};

$clones = '';
foreach ([$g23, $g24] as $r) {
    $clone = $row;
    foreach ($cells[0] as $i => $cell) {
        $clone = str_replace($cell, $setText($cell, $r[$i]), $clone);
    }
    $guard($clone !== $row, $r[0].' clone carries new text');
    $guard(substr_count($clone, '<w:tr') === substr_count($row, '<w:tr'), $r[0].' clone is one row and no more');
    $guard(str_contains($clone, str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], mb_substr($r[2], 0, 90))),
        $r[0].' clone holds the body it was given');
    $clones .= $clone;
}

$containers = ['tr' => '</w:tr>', 'tc' => '<w:tc>', 'p' => '</w:p>', 't' => '</w:t>'];
foreach ($containers as $name => $needle) {
    $expect = substr_count($clones, $needle);
    $guard($expect === 2 * substr_count($row, $needle), "container {$needle}: the two clones contribute exactly two rows' worth");
}

$new = substr_replace($new, $clones, (int) $rowEnd, 0);

// ------------------------------------------------------------------ 2. recount the register, both places
$tblStart = strrpos(substr($new, 0, (int) $rowStart), '<w:tbl>');
$tblEnd = strpos($new, '</w:tbl>', (int) $rowEnd) + 7;
$tblText = substr($new, (int) $tblStart, (int) $tblEnd - (int) $tblStart);
preg_match_all('/<w:tr.*?<\/w:tr>/s', $tblText, $check);
$dataRows = count($check[0]) - 1;
printf("     §28.3 recounted from the edited bytes: 1 header + %d data rows\n", $dataRows);
$guard($dataRows === 41, 'the table now holds 41 data rows');
preg_match_all('/\bG-(\d+)\b/', $tblText, $gids);
$guard(((int) max($gids[1])) === 24, 'the G block now runs up to G-24');
$guard(count(array_unique($gids[0])) === 24, 'each of the 24 G ids appears exactly once in the table');

$sentenceOld = 'lane 51 added G-20, and lane 53 added G-21 and G-22, so §28.3 holds 39 data rows';
$sentenceNew = 'lane 51 added G-20, lane 53 added G-21 and G-22, and lane 54 added G-23 and G-24, so §28.3 holds '
    .$dataRows.' data rows';
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
$guard($fc[0] !== [] && substr_count($fc[0][1], '>39<') === 1, 'its Rows cell reads exactly 39');
$famNew = str_replace($fc[0][1] ?? '', str_replace('>39<', '>'.$dataRows.'<', $fc[0][1] ?? ''), $famRow);
$guard($famNew !== $famRow, 'the Rows cell was rewritten');
$new = substr_replace($new, $famNew, (int) $famRowStart, strlen($famRow));

// ------------------------------------------------------------------ 3. G-11 is closed, and by the route its row named
$popFrom = 0;
$popTo = 0;
foreach ($pipeline as $i => $line) {
    if (str_contains($line, 'foreach (Enrollments::where(fn ($q) => $q')) {
        $popFrom = $i + 1;
    }
    if ($popFrom > 0 && str_contains($line, '->get() as $claim) {')) {
        $popTo = $i + 1;
        break;
    }
}
$guard($popFrom > 0 && $popTo > $popFrom, 'the G-2 pass’s own population span is measurable (:'.$popFrom.'-'.$popTo.')');

$edits = [
    // the suite figure this batch moved, and the only place it is stated in the present tense
    'The suite stands at 758 tests and 5144 assertions'
        => 'The suite stands at '.$tests.' tests and '.$assertions.' assertions',
    // the citation inside G-11's body, whose span grew from inside when the population widened
    '(tools/seed_full_demo_pipeline.php:1722-1725)' => '(tools/seed_full_demo_pipeline.php:'.$popFrom.'-'.$popTo.')',
    // the body's verdict
    'Open and not a code defect: closing it is either a demo-data decision or a deliberate widening of the '
        .'generator’s year floor, and this repository’s own rule says a data decision a tool regenerates is not '
        .'a decision, so the second route would have to be the durable one.'
        => 'Closed 2026-10-18 by that second route: the pass now asks the records themselves whether they claim '
            .'to be returning, so a year-1 `old` enrollment on the demo term is seated its completed year and '
            .'raised to year 2 as the year arrives, and its load is re-sliced only while every row on it is still '
            .'`proposed` with no block and no schedule — a load a desk has taken up is left alone and reported, '
            .'because quietly re-slicing signed work is the substitution this register refuses elsewhere. The two '
            .'rows moved on live `ems` in the same batch: enrollment 596 (Maria Reyes) and 598 (Rico Navarro) read '
            .'year 2 behind new records 1284 and 1285, and the report line the tool now keeps, '
            .'“Ledger — returning claim with no year behind it”, went from 2 to 0 — on live `ems` and on a schema '
            .'built from empty in CI’s order, where one such record is seeded and now closes itself.',
    // who decides
    'Owner — a demo-data decision, or the generator’s year floor, which the regeneration rule says must be the '
        .'durable route'
        => 'Closed by the generator’s year floor, the durable route the row named: the widening is in the tool, so '
            .'a reseed cannot re-open it.',
    // the issue row's host-clock claim, which lane 54c measured and disproved
    'One thing came back still open, and it is the owner’s: a host clock the school will feel on demo day — this '
        .'machine reports its zone as UTC-11 while its wall clock is kept on Manila time, so an evening run rolled '
        .'the application’s day forward and the standing test that requires a receipt dated today failed once for '
        .'no other reason. ems:preflight compares PHP to MySQL, so it structurally cannot see that gap.'
        => 'One thing came back still open, and it is the owner’s: this machine keeps UTC-11 while the school keeps '
            .'Manila, so its own clock is nineteen hours behind the civil day a desk stands in — an evening run '
            .'rolls the application’s day forward for anything that reads the wall instead of the declaration, '
            .'which is what once failed the standing test that requires a receipt dated today. What changed is '
            .'that the gap is no longer invisible: ems:preflight now prints a Host time zone row that reads the '
            .'operating system from MySQL’s @@system_time_zone and compares it with the school’s declaration, '
            .'because inside a booted process nothing else says so — date_default_timezone_get() is pinned to '
            .'app.timezone by the framework, php_uname carries no zone, and php.ini’s date.timezone names what '
            .'PHP was told (UTC here) rather than what the machine keeps. It is a WARN and never a FAIL, because '
            .'a preflight cannot move a clock and the stored instants are already right, and it refuses with '
            .'“could not be read” rather than guessing when the source answers a name instead of an hour.',
];

foreach ($edits as $from => $to) {
    $guard(substr_count($new, $from) === 1, 'needle found exactly once: '.mb_substr($from, 0, 58).'…');
    $new = str_replace($from, $to, $new);
}

// ------------------------------------------------------------------ 4. the revision row carries the lane
$laneOld = 'The document itself was swept by line, by meaning and by §-reference after every pass in this batch.';
$laneNew = 'Lane 54 then asked the same question of the tool that writes the demo, and of the machine the tool '
    .'runs on. Four findings. The paid step: five settled accounts were settled by a reconstruction loop, not by '
    .'the code that created them, so the creation sites now walk `assessed → paid → enrolled` themselves through '
    .'the state machine and a fresh install reconstructs none — measured on a schema built in CI’s order, where '
    .'“Ledger — paid step this run had to reconstruct” reads 0 and `ems:audit:record-states` reads 0 across all '
    .'fourteen rules. The year floor: §28.3 G-11 closed by widening that pass to records claiming to be returning '
    .'(§28.3 G-11’s own row tells the rest). The report’s own eyes: the tool repaired lane 53’s two impossible '
    .'states in silence, so its desk report now carries four Ledger lines — an unpriceable fee sheet, a paid step '
    .'it had to write, a returning claim with no year behind it, receipts exceeding their sheet — and each was '
    .'proved able to be non-zero by handing it exactly that defect, one case to a freshly built schema, with a '
    .'clean control beside it reading 0 on all four (storage/app/scratch/mutate-lane54-report.php). The money a '
    .'desk took: §28.3 G-23 and G-24, which are the same weighing found the generator’s paid-only sums and the '
    .'receipt its own status column cannot describe. And the host: ems:preflight’s new row reports the machine’s '
    .'zone against the school’s declaration, with the readings that looked suitable and are not written down '
    .'beside it. The class is pinned on both drivers — the assertion asks the database what it can answer, so '
    .'SQLite gets “could not be read” and MySQL gets an hour, and a test that had assumed the suite was always '
    .'SQLite went red the moment this batch ran it against MySQL. Six mutants of the new row, each killed by the '
    .'test that names it (storage/app/scratch/mutate-lane54-host-zone.php). '
    .$laneOld;
$guard(substr_count($new, $laneOld) === 1, 'the revision row’s closing sentence is present and unique');
$new = str_replace($laneOld, $laneNew, $new);

// ------------------------------------------------------------------ 5. citations this batch moved
$pipelineShift = $shiftOf($pipelineHead, $pipeline, 1367, 1375);
$guard($pipelineShift === 10, 'charge_demo_fees()’s cited span moved by one offset only ('.var_export($pipelineShift, true).')');
if ($pipelineShift !== null) {
    $from = 'seed_full_demo_pipeline.php:1367-1375';
    $to = 'seed_full_demo_pipeline.php:'.(1367 + $pipelineShift).'-'.(1375 + $pipelineShift);
    $guard(substr_count($new, $from) === 1, 'the fee-sheet citation is present and unique');
    $new = str_replace($from, $to, $new);
}

// Preflight: three unchanged methods move by a shift, and `checks()` grew from inside so it is re-pointed by name.
[$checksFrom, $checksTo] = $functionSpan($preflight, 'checks');
printf("     checks() measures :%d-%d\n", $checksFrom, $checksTo);
$keySpan = $functionSpan($preflight, 'applicationKeyIsReal');
$servedSpan = $functionSpan($preflight, 'servedFolderStaysPrivate');
$diskSpan = $functionSpan($preflight, 'uploadDiskIsPrivate');
printf("     applicationKeyIsReal :%d-%d | servedFolderStaysPrivate :%d-%d | uploadDiskIsPrivate :%d-%d\n",
    $keySpan[0], $keySpan[1], $servedSpan[0], $servedSpan[1], $diskSpan[0], $diskSpan[1]);

$citeOld = 'Preflight.php:83-99, the two new rules at :117-132 and :147-174, the disk rule at :344-367';
$citeNew = 'Preflight.php:'.$checksFrom.'-'.$checksTo.', the two new rules at :'
    .$keySpan[0].'-'.$keySpan[1].' and :'.$servedSpan[0].'-'.$servedSpan[1].', the disk rule at :'
    .$diskSpan[0].'-'.$diskSpan[1].', and since lane 54 a host-zone row at :'
    .$functionSpan($preflight, 'hostZoneMatchesTheSchools')[0].'-'.$functionSpan($preflight, 'offsetOf')[1];
$guard(substr_count($new, $citeOld) === 1, 'the preflight citation chain is present and unique');
$new = str_replace($citeOld, $citeNew, $new);

// the suite figure in the issue row was already rewritten above; make sure no stale one is left
$guard(substr_count($new, '758 tests') === 0, 'no stale 758-test figure survives in the document');

// ------------------------------------------------------------------ 6. verify, then write
$guard(substr_count($new, '>G-23<') === 1 && substr_count($new, '>G-24<') === 1, 'the two new ids are each in the table once');
$guard(strlen($new) > strlen($xml), 'the edited bytes are larger than the original (by '.(strlen($new) - strlen($xml)).')');

if ($fails > 0) {
    printf("\n%d guard(s) failed — NOTHING WRITTEN.\n", $fails);
    exit(1);
}

if (! $apply) {
    printf("\nDRY RUN. Two rows: %d bytes | register %d data rows | suite %d/%d. Re-run with --apply.\n",
        strlen($clones), $dataRows, $tests, $assertions);
    exit(0);
}

// The pre-pass archive is copied whole below; the XML string must not be written beside the docx, because a
// '.docx.bak' holding raw XML is not a backup of anything.
$backup = $root.'/storage/app/scratch/ems-docx-pre-pass158.docx';
copy($src, $backup);

$out = $root.'/Documentation/.ems-docx-pass158.tmp';
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
    echo "REFUSED: could not move the new archive into place; the backup is {$backup}.\n";
    exit(1);
}

$check = new ZipArchive();
if ($check->open($src) !== true) {
    echo "WRITTEN but the result will not open — restore from {$backup}.\n";
    exit(1);
}
$guard($check->numFiles === $partCount, 'the archive still holds '.$partCount.' parts');
$round = $check->getFromName('word/document.xml');
$check->close();
$guard($round !== false && hash('sha256', $round) === hash('sha256', $new), 'word/document.xml on disk is the bytes this pass built');

printf("\nPass 158 written. Two rows added, G-11 closed, register at %d data rows, suite %d/%d; backup at %s\n",
    $dataRows, $tests, $assertions, $backup);
exit($fails === 0 ? 0 : 1);
