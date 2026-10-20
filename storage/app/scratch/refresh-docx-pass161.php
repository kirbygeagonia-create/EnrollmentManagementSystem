<?php

/**
 * Pass 161 — G-24 is closed, and the register's own account of it moves from "left open" to what was done.
 *
 * The row wrote its own remedy: "the generator marks by the balance the model computes, the audit gains the
 * rule, and the walkthrough's Cashier line is re-read". All three are now true, so the row's body and its
 * decision cell are rewritten rather than appended to — and the sentence in G-23 that explained why no test
 * could have caught the paid-only sums ("the tool never writes a `partial` row itself") is now false and is
 * dated instead of left standing.
 *
 * The rule count moves sixteen -> seventeen everywhere it is stated as a present fact, and the two historical
 * "sixteen where it ran fourteen" sentences keep their number and gain a date, because they were true on the
 * day they were written and rewriting them would make the record read as if lane 55 had shipped seventeen.
 *
 * Also re-pointed, from the queue lane 55 left behind: two pointers into RegistrarController that name
 * `returnToEvaluation` and the printed artefacts while landing on other methods entirely. Measured off the
 * current file, by reading the sentence, not by arithmetic.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$suiteLog = $root.'/storage/app/scratch/lane56-suite.txt';

$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $fails++;
    }
};

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
    echo "REFUSED: the suite log carries no `Tests:` summary line — a run that was killed prints nothing, and\n"
        ."nothing is not a green gate.\n";
    exit(1);
}
// Pest's own summary line is the contract: it names a failing count when anything failed ("1 failed, 770
// passed"). The check is made on that line only, because test *names* contain the word "failed"
// ("a failed subject in a previous term derives irregular standing") and a whole-log search would refuse a
// green run. The `.bat` runner's SUITE_EXIT marker is belt-and-braces over the same evidence.
$summary = '';
foreach (explode("\n", $log) as $line) {
    if (str_contains($line, 'Tests:')) {
        $summary = $line;
    }
}
if (preg_match('/(\d+)\s+failed/', $summary, $f) === 1 && (int) $f[1] > 0) {
    printf("REFUSED: the summary line reports %s failing tests.\n", $f[1]);
    exit(1);
}
printf("Suite figure read from the log: %d passed, %d assertions%s.\n", $tests, $assertions,
    str_contains($log, 'SUITE_EXIT=0') ? ', SUITE_EXIT=0' : ' (no exit marker in the log)');

// The spans the two re-pointed pointers need, measured off the file rather than remembered.
$registrar = 'app/Http/Controllers/Registrar/RegistrarController.php';
$lines = array_map(fn (string $l): string => rtrim($l, "\r\n"), (array) file($root.'/'.$registrar));
$spanOf = function (string $name) use ($lines): string {
    foreach ($lines as $i => $line) {
        if (preg_match('/^\s*public function '.preg_quote($name, '/').'\(/', $line) === 1) {
            $depth = 0;
            $opened = false;
            for ($j = $i; $j < count($lines); $j++) {
                $depth += substr_count($lines[$j], '{') - substr_count($lines[$j], '}');
                if ($depth > 0) {
                    $opened = true;
                }
                if ($opened && $depth === 0) {
                    return ($i + 1).'-'.($j + 1);
                }
            }
        }
    }

    throw new RuntimeException("{$name} not found in {$registrar}");
};
$returnSpan = $spanOf('returnToEvaluation');
$printFrom = (int) explode('-', $spanOf('printCertificate'))[0];
$printTo = (int) explode('-', $spanOf('printSubjectLoad'))[1];

// `record_demo_payment()` is a plain function, not a method, so the brace walk above does not name it.
$pipeline = (array) file($root.'/tools/seed_full_demo_pipeline.php');
$pipeline = array_map(fn (string $l): string => rtrim($l, "\r\n"), $pipeline);
$recordSpan = 'NOT-FOUND';
foreach ($pipeline as $i => $line) {
    if (preg_match('/^function record_demo_payment\(/', $line) === 1) {
        $depth = 0;
        $opened = false;
        for ($j = $i; $j < count($pipeline); $j++) {
            $depth += substr_count($pipeline[$j], '{') - substr_count($pipeline[$j], '}');
            if ($depth > 0) {
                $opened = true;
            }
            if ($opened && $depth === 0) {
                $recordSpan = ($i + 1).'-'.($j + 1);
                break;
            }
        }
        break;
    }
}
$guard($recordSpan !== 'NOT-FOUND', 'record_demo_payment() measured at :'.$recordSpan.' (the docx cites 1429-1447)');
printf("     RegistrarController: returnToEvaluation :%s | the three desk prints :%d-%d | record_demo_payment :%s\n",
    $returnSpan, $printFrom, $printTo, $recordSpan);

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$partCount = $zip->numFiles;
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
$new = $xml;

// ------------------------------------------------------------------ 1. the register's own account
// [needle, replacement, how many times it must appear in the bytes]
$edits = [
    // G-24, body: the sentence about what the audit asked. It was written when the rule did not exist.
    ['`ems:audit:record-states` runs sixteen rules over these tables and reads 0 on every one of them, because none asks whether a receipt’s own status agrees with the balance it left.',
        '`ems:audit:record-states` ran sixteen rules over these tables and read 0 on every one of them, because none asked whether a receipt’s own status agreed with the balance it left. Its seventeenth rule does.', 1],
    // G-24, body: the row described its own remedy as work someone else owed.
    ['Left OPEN and not changed here: closing it is the register’s usual pair — the generator marks by the balance the model computes, the audit gains the fifteenth rule, and the walkthrough’s Cashier line is re-read — and each of those is a decision about what the demo is supposed to show, which is the owner’s, not a tool’s.',
        'Closed 2026-10-20 by exactly that pair, and nothing else was decided on the office’s behalf: the create site writes the installment `partial`, a ledger pass re-marks any receipt that says `paid` over a sheet still owing and reports how many it had to touch, and `ems:audit:record-states` counts the state as its seventeenth rule so a box this tool never ran cannot hold it quietly. The Cashier line the walkthrough reads was re-read and already said part payment, so the document was right and the data was wrong — the pair this register keeps meeting.', 1],
    // G-24, decision cell.
    ['Owner — mark the seeded receipt `partial` in `record_demo_payment()` (tools/seed_full_demo_pipeline.php:1429-1447) and the hand-written block above it, add the rule to `ems:audit:record-states`, or leave the demo’s installment as a receipt that reads settled and say so in the guide.',
        'Done 2026-10-20: `record_demo_payment()` (tools/seed_full_demo_pipeline.php:'.$recordSpan.') is the one writer and the hand-written block now calls it, the audit gained `receiptPaidWhileOwing()`, and `storage/app/scratch/mutate-lane56-ledger.php` plants the old shape and requires all three to answer it.', 1],
    // G-23's explanation of why no test could see the paid-only sums.
    ['because the tool never writes a `partial` row itself (that is §28.3 G-24) and live `ems` held none on 2026-10-18, so the divergence needed a desk act to exist.',
        'because the tool wrote no `partial` row at all until §28.3 G-24 was closed on 2026-10-20 and live `ems` held none on 2026-10-18, so the divergence needed a desk act to exist.', 1],
    // ------------------------------------------------------------------ 2. the rule count
    ['sixteen rules over idrequests, workflowsteps, enrollmentworkflow, enrollments, clinicrecords, notifications, documentprintlog, studentassessments, charges, enrollmentstatushistory, schedulemeetings, schedules and blocks — a validated request',
        'seventeen rules over idrequests, workflowsteps, enrollmentworkflow, enrollments, clinicrecords, notifications, documentprintlog, studentassessments, charges, payments, enrollmentstatushistory, schedulemeetings, schedules and blocks — a validated request', 1],
    ['a receipt on an account that never entered paid, two schedules of one block at one hour, and a room or an officer in two schedules at one hour — each counted',
        'a receipt on an account that never entered paid, two schedules of one block at one hour, a room or an officer in two schedules at one hour, and a receipt that says the account is settled while it still owes — each counted', 1],
    // The one sentence that states the count as a present fact over these tables is G-24's own, rewritten above;
    // this is the second register sentence that names the zero readings.
    ['`ems:audit:record-states` reads 0 across all sixteen rules',
        '`ems:audit:record-states` reads 0 across all seventeen rules', 1],
    // Both are historical sentences; they keep their number and gain the date that ended it.
    ['sixteen rules where it ran fourteen',
        'sixteen rules where it had run fourteen (seventeen since 2026-10-20)', 2],
    ['The suite stands at 769 tests and 5186 assertions',
        'The suite stands at '.$tests.' tests and '.$assertions.' assertions', 1],
];

foreach ($edits as [$from, $to, $want]) {
    $n = substr_count($new, $from);
    $guard($n === $want, sprintf('needle %d× (read %d): %s…', $want, $n, mb_substr($from, 0, 52)));
    $new = $n === $want ? str_replace($from, $to, $new) : $new;
}
$guard(substr_count($new, 'sixteen rules over idrequests') === 0, 'no stale sixteen-rule inventory sentence remains');
$guard(substr_count($new, 'Left OPEN and not changed here') === 0, 'G-24 no longer reads as an open row');
$guard(substr_count($new, 'the tool never writes a `partial` row') === 0, 'G-23’s reason is dated, not left standing');

// ------------------------------------------------------------------ 3. the two pointers lane 55 queued
// Bound by token and offset, because Word splits a file name and its `:NN-NN` into two <w:t> runs: the sentence
// reads continuously in the dump and is not one contiguous string in the bytes, so no needle of the whole pointer
// exists. Each occurrence is still required to be accounted for — the count is asserted, and anything left is a
// refusal rather than a silent miss.
$bind = [
    [':205-232', ':'.$returnSpan, 2, 'returnToEvaluation cited at the approval span'],
    [':234-320', ':'.$printFrom.'-'.$printTo, 1, 'the three desk print actions cited at approve()'],
];
foreach ($bind as [$token, $to, $want, $what]) {
    $n = substr_count($new, $token);
    $guard($n === $want, sprintf('%-11s appears %d× (read %d) — %s', $token, $want, $n, $what));
    $new = $n === $want ? str_replace($token, $to, $new) : $new;
}
$guard(substr_count($new, ':205-232') === 0, 'neither returnToEvaluation pointer survives');
$guard(substr_count($new, ':234-320') === 0, 'the printed-artefact pointer survives nowhere');

// ------------------------------------------------------------------ 4. the pointers into the generator itself
// This batch moved lines inside `tools/`, which PHPStan does not analyse and Pint excludes, so a citation there
// is verified only by measuring it against HEAD's bytes — the engine lane 55 proved on the two controllers.
$pipeHead = array_map(fn (string $l): string => rtrim($l, "\r\n"),
    (array) file($root.'/storage/app/scratch/lane56-head-seed_full_demo_pipeline.php'));
$pipeNow = $pipeline;
if (count($pipeHead) === 0) {
    echo "REFUSED: the HEAD export of the generator is missing — take it with\n"
        ."  git show HEAD:tools/seed_full_demo_pipeline.php > storage/app/scratch/lane56-head-seed_full_demo_pipeline.php\n";
    exit(1);
}
$pipeShift = function (int $from, int $to) use ($pipeHead, $pipeNow, $guard): string {
    $ok = [];
    for ($s = -400; $s <= 400; $s++) {
        $good = 0;
        for ($i = $from; $i <= $to; $i++) {
            $j = $i - 1 + $s;
            if (isset($pipeNow[$j]) && $pipeNow[$j] === ($pipeHead[$i - 1] ?? '__missing__')) {
                $good++;
            }
        }
        if ($good === $to - $from + 1) {
            $ok[] = $s;
        }
    }
    if (count($ok) !== 1) {
        $guard(false, "seed_full_demo_pipeline.php:{$from}-{$to} resolves to one shift (".count($ok).' found)');

        return $from.'-'.$to;
    }
    printf("  ok   generator :%d-%d is moved to :%d-%d (%+d)\n", $from, $to, $from + $ok[0], $to + $ok[0], $ok[0]);

    return ($from + $ok[0]).'-'.($to + $ok[0]);
};
$pipes = [
    ['1374-1382', [1374, 1382], 'charge_demo_fees() cited in G-21’s evidence cell'],
    ['1396-1399', [1396, 1399], 'demo_held() cited in G-23’s evidence cell'],
    ['1828-1843', [1828, 1843], 'the shortfall loop cited in G-23’s body'],
];
foreach ($pipes as [$spelled, [$from, $to], $what]) {
    $token = 'seed_full_demo_pipeline.php:'.$spelled;
    $n = substr_count($new, $token);
    $guard($n === 1, "generator pointer present once (read {$n}): {$spelled} — {$what}");
    $new = $n === 1 ? str_replace($token, 'seed_full_demo_pipeline.php:'.$pipeShift($from, $to), $new) : $new;
}
$guard(substr_count($new, ':1374-1382') === 0 && substr_count($new, ':1396-1399') === 0
    && substr_count($new, ':1828-1843') === 0, 'no pre-batch generator pointer survives in the document');

// ------------------------------------------------------------------ 4. the revision row carries lane 56
$laneOld = 'The document itself was swept by line, by meaning and by §-reference after every pass in this batch.';
$laneNew = 'Lane 56 then closed the row that had been left to the owner. The demo’s installment on Bea Alonzo’s '
    .'account — ₱8,000 taken against a ₱18,500 sheet that still owes ₱10,500 — was written `paid`, while the '
    .'tool’s own comment, its own success line and the walkthrough’s Cashier row all called it a part payment. '
    .'`held()` counts both words, so every peso figure on the screen and in the report was right and stayed right; '
    .'only the status lied, and the status is what `blockingReasons()` asks. The fix is the pair the register '
    .'named: one writer that marks by the balance the model computes, a ledger pass that re-marks any receipt '
    .'claiming settlement over a balance and reports how many it had to touch, and a seventeenth rule in '
    .'`ems:audit:record-states` so a database this tool never ran cannot hold the state quietly. '
    .'`storage/app/scratch/mutate-lane56-ledger.php` plants the old row and requires the audit to refuse it, the '
    .'report line to print 1, the generator to exit 1 and re-mark, and the next run to read 0 — and refuses its '
    .'own run against live `ems` or an unseeded schema. Measured on both datasets: live `ems` now holds 14 `paid` '
    .'and 1 `partial`, and the scratch schema built in CI’s order reads 17 rules at 0. Two pointers the previous '
    .'lane had queued were re-taken with it, both naming `returnToEvaluation` or the desk print actions while '
    .'landing on the approval transaction; each span was measured off the controller and checked by reading the '
    .'sentence. '
    .$laneOld;
$guard(substr_count($new, $laneOld) === 1, 'the revision row’s closing sentence is present and unique');
$new = str_replace($laneOld, $laneNew, $new);

$guard(strlen($new) > strlen($xml), 'the edited bytes grew by '.(strlen($new) - strlen($xml)));

if ($fails > 0) {
    printf("\n%d guard(s) failed — NOTHING WRITTEN.\n", $fails);
    exit(1);
}
if (! $apply) {
    printf("\nDRY RUN. G-24 closed, seventeen rules, 3 pointers re-taken, suite %d/%d. Re-run with --apply.\n",
        $tests, $assertions);
    exit(0);
}

$backup = $root.'/storage/app/scratch/ems-docx-pre-pass161.docx';
copy($src, $backup);
$out = $root.'/Documentation/.ems-docx-pass161.tmp';
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

printf("\nPass 161 written; backup at %s\n", $backup);
exit(0);
