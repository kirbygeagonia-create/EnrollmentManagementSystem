<?php

/**
 * Docx pass 328 — give yesterday's two class-total sentences a date of their own.
 *
 * `lane58-suite-claims.php` refused the document after this batch's sort work: §1.1's rows state
 * `AssessmentQueueSortTest 9 cases / 35 assertions` and `AccountingQueueSortTest answers 10 cases and 38 assertions`,
 * and the delivered run answers 11/40 and 12/43, because this batch added a sequence case and a payload-leak case to
 * both classes. The rows are dated 2026-10-30, so the figures were TRUE on their own day — re-typing them would make
 * a 2026-10-30 row state a number that no 2026-10-30 run printed, which is the defect lane 60 and lane 72 both name.
 *
 * The recorded repair is the second one: the classifier exempts a sentence that carries its own date, and a §1.1 row
 * is exempt only when its FIRST CELL is a bare date. These rows' first cell is `2026-10-30 <title>` — date and title
 * in one paragraph, which is how §1.1 is written — so the exemption never fired and the sentence was judged as
 * present tense. Both sentences therefore get the day they were read, inside the sentence, and nothing else moves.
 *
 * No figure is re-typed: 9/35 and 10/38 stay exactly as they were written on 2026-10-30.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass328.php [--apply]
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

$edits = [
    [
        'The two class totals in this row are read from the junit of the whole-suite run that produced them',
        'The two class totals in this row are read from the junit of the whole-suite run that produced them on 2026-10-30',
    ],
    [
        'answers 10 cases and 38 assertions in the run whose junit this row read',
        'answers 10 cases and 38 assertions in the run whose junit this row read on 2026-10-30',
    ],
];

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (an error CODE is truthy).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$failures = [];
foreach ($edits as $i => [$from, $to]) {
    // Not idempotent by shape: `$from` is a PREFIX of `$to`, so a second run would append the date again and leave
    // "…produced them on 2026-10-30 on 2026-10-30". Refuse once the dated form exists.
    if (substr_count($xml, $to) > 0) {
        $failures[] = "edit {$i} is already applied — the dated sentence is in the document";
        continue;
    }
    $n = substr_count($xml, $from);
    printf('needle %d occurrences: %d (want 1) — %s%s', $i, $n, substr($from, 0, 60), PHP_EOL);
    if ($n !== 1) {
        $failures[] = "needle {$i} matched {$n} time(s)";
    }
    if ($from === $to) {
        $failures[] = "needle {$i} and its replacement are the same bytes";
    }
}
if ($failures !== []) {
    fwrite(STDERR, "REFUSED — nothing written:\n".implode("\n", $failures)."\n");

    exit(1);
}

$textEdits = [];
foreach ($edits as [$from, $to]) {
    $textEdits[] = [$from, $to];
}

docxApply($docx, $root.'/storage/app/scratch/pass328-dry.docx', $textEdits, [], $apply);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot re-open after the write.\n");

    exit(1);
}
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();

$bad = 0;
foreach ($edits as [$from, $to]) {
    // The replacement CONTAINS the needle — this pass appends a date — so "the old form must be gone" is unsatisfiable
    // by construction and reported a correct write as two failures. The honest check is that each form now occurs
    // exactly the number of times the row holds: one dated sentence, one copy.
    $gones = substr_count($after, $to);
    $olds = substr_count($after, $from);
    printf("  dated form %-46s %d (want 1)   undated form %d (want 1, it is a prefix of the dated one)%s", substr($to, -44), $gones, $olds, PHP_EOL);
    if ($gones !== 1 || $olds !== 1) {
        $bad++;
    }
}
// The retired figures must survive — this pass dates sentences, it does not re-age numbers.
foreach (['9 cases / 35 assertions', '10 cases and 38 assertions'] as $keep) {
    $n = substr_count($after, $keep);
    printf('  retained figure %-32s count %d (want >= 1)%s', $keep, $n, PHP_EOL);
    if ($n < 1) {
        $bad++;
    }
}
if ($bad > 0) {
    fwrite(STDERR, "REFUSED: {$bad} read-back check(s) failed.\n");

    exit(1);
}

echo 'written, re-read: both sentences now name the day they were read, and no figure was re-typed.'."\n";
