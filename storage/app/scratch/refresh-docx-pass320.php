<?php

/**
 * Pass 320: the matrix grows a machine-read verdict column, and the demo dataset ages at the school's midnight.
 *
 * Two appends to today's §1.1 row. No totals are typed: the suite figure this batch could quote is a reading of one
 * surface the runner cannot share (lane 58's rule), and the matrix's own counts are printed by `lane94-matrix-claims.php`
 * rather than restated here.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass320.php [--apply]
 */

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

require __DIR__.'/docx-edit-engine.php';

$from = 'a mapped number that is not a unique token rewrites a true pointer into a different file.';
$to = 'a mapped number that is not a unique token rewrites a true pointer into a different file.'
    .' The requirement matrix gained a Status column the same day, because a verdict written in the middle of a'
    .' sentence cannot be counted: its auditor read AMBIGUOUS for six of twenty-eight gap rows and the register could'
    .' not answer "how many gaps are open" without a reader. Each new cell was written from its own row and checked'
    .' against §28.3’s verdict cell, and the auditor was taught the column — a table whose header names one is judged'
    .' by that cell alone — which its harness proves three ways: blunting a cell raises the ambiguous count and names'
    .' the row, renaming a header raises it for that whole table, and planting a Built claim in the prose of a row'
    .' whose cell says Not built changes nothing. Two rows then stood out as naming no file at all and were made'
    .' resolvable by naming the files a grep confirmed. The same exercise surfaced a hazard the document should state'
    .' rather than rediscover: this matrix’s gap numbers and §28.3’s are different registers that collide in the first'
    .' nine — §28.3 G-6 and G-8 are closed findings where the matrix’s G6 and G8 are open — so a gap id is written'
    .' with its register named and a count is taken from one table, never from a grep that reads both.'
    .' Finally, what the suite caught on this box and what it did not: the run went red on one case, and the failure'
    .' was the dataset rather than the code — the school’s civil day advanced past midnight, so the demo receipt the'
    .' generator dates with SchoolTime::today() carried the day before and the Daily Collections Report would have'
    .' shown nothing. The probe that asks which school-day the dataset carries said so before the case was read as a'
    .' defect; `ems` was dumped and verified, the pipeline re-run (its own report answered zero on every ledger line,'
    .' re-dating the slip it minted rather than adding a second one), and the suite then measured the build. Nothing'
    .' was softened to forgive a stale date.';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answers an error CODE for a bad file, which is truthy).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

foreach ([[$from, 1], ['The requirement matrix gained a Status column', 0]] as [$needle, $want]) {
    $h = substr_count($xml, $needle);
    if ($h !== $want) {
        fwrite(STDERR, "REFUSED: [{$needle}] occurs {$h}, want {$want}.\n");

        exit(1);
    }
}

printf("%s: the matrix's verdict column and the dated dataset are recorded\n", $apply ? 'APPLY' : 'DRY RUN');

docxApply($docx, $apply ? $root.'/storage/app/scratch/pass320-backup-'.date('Ymd-His').'.docx'
    : $root.'/storage/app/scratch/pass320-dry.docx', [[$from, $to]], [], $apply);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$again = new ZipArchive;
if ($again->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot re-open the written archive.\n");

    exit(1);
}
$after = (string) $again->getFromName('word/document.xml');
$again->close();

foreach ([
    'The requirement matrix gained a Status column' => 1,
    'the pipeline re-run (its own report answered zero on every ledger line' => 1,
] as $needle => $want) {
    $h = substr_count($after, $needle);
    if ($h !== $want) {
        fwrite(STDERR, "REFUSED after the write: [{$needle}] occurs {$h}, want {$want}.\n");

        exit(1);
    }
}
$h = substr_count($after, $from);
if ($h !== 1) {
    fwrite(STDERR, "REFUSED after the write: the anchor occurs {$h}, want 1.\n");

    exit(1);
}

echo "APPLIED.\n";
