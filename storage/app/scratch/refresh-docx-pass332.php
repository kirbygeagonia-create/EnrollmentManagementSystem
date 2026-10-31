<?php

/**
 * Docx pass 332 — ROW 38 stops carrying per-class totals, so the matrix is their only home.
 *
 * The row states `AssessmentQueueSortTest 11 cases / 44 assertions` and `ClearanceQueueSortTest 9 / 32`, and the
 * requirement matrix states the same two figures beside the same two class names. Pass 330b re-took both from the
 * delivered junit; then this batch added a payload case to four queue classes, and Clearance moved again while
 * Assessment did not. That is not two readings of one fact, it is two places that must be edited to say one thing,
 * which is the shape lane 58's one-surface rule was written against and the reason the figures keep going stale
 * between a run and a document.
 *
 * So the numbers come OUT of the row rather than being re-typed into it: the sentence names the instrument that
 * reads them (`lane107-retake-class-figures.php`) and the register that carries them (the matrix, beside each class
 * name). What the row keeps is the claim that actually matters to the office — that the totals belong to a run, not
 * to the code — and a pointer to where the current reading lives.
 *
 * Text edits only, each needle asserted to occur exactly once and the retired wording asserted gone.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass332.php [--apply]
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$backup = $root.'/storage/app/scratch/pass332-backup.docx';

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

require __DIR__.'/docx-edit-engine.php';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answers an error CODE for a bad file, which is truthy).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$textEdits = [
    [
        'The two class totals in this row are read from the junit of the whole-suite run this row re-took last on that same day, 2026-10-30 — AssessmentQueueSortTest 11 cases / 44 assertions, and ClearanceQueueSortTest 9 / 32 after its status case stopped remembering one database’s order — because a figure beside a class name is a property of a run, not of the code, and the payload cases that grew since the first reading belong to the run that must be named.',
        'The class totals that used to sit in this sentence have been taken out of it and left where one instrument can re-take them: beside each class name in the requirement matrix, read from the junit of the whole-suite run that produced them by `lane107-retake-class-figures.php`. A figure written twice is a figure that goes stale in one place while the other is refreshed, which is what had happened twice already — the payload cases this batch added moved one of the two classes and not the other, and the row kept naming both. What the office needs from this row is the claim, not the count: a number beside a class name belongs to a run, and the matrix is where the current reading lives.',
    ],
    [
        'AccountingQueueSortTest answers 12 cases and 47 assertions in the same re-taken reading of that day’s whole-suite junit.',
        'AccountingQueueSortTest’s own totals are carried in the matrix beside its name, from the same instrument.',
    ],
];

$failures = [];
$before = [];
foreach ($textEdits as $i => [$from, $to]) {
    $n = substr_count($xml, $from);
    $before[$i] = $n;
    printf('edit %d: %d occurrence(s) — %s%s', $i, $n, substr(trim($from), 0, 58), PHP_EOL);
    if ($n !== 1) {
        $failures[] = "edit {$i} matched {$n} time(s), wanted exactly 1";
    }
    if (str_contains($to, '|')) {
        $failures[] = "edit {$i}'s replacement carries a literal pipe";
    }
    if (preg_match('/\.php:\d/u', $to) === 1) {
        $failures[] = "edit {$i}'s replacement introduces a line citation the pass has not verified";
    }
    // The point of the pass is that no per-class figure survives in the row, so check its own claim.
    if (preg_match('/\d+ cases[ \/] and? ?\d+ assertions/iu', $to) === 1) {
        $failures[] = "edit {$i} still writes a class figure — this pass exists to remove them from the document";
    }
}
if ($failures !== []) {
    $already = true;
    foreach ($textEdits as [$from, $to]) {
        if (substr_count($xml, $to) !== 1) {
            $already = false;

            break;
        }
    }
    if ($already) {
        echo "ALREADY APPLIED — the row carries no per-class figure.\n";

        exit(0);
    }
    fwrite(STDERR, "REFUSED — nothing written:\n".implode("\n", $failures)."\n");

    exit(1);
}

docxApply($docx, $backup, $textEdits, [], $apply);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot re-open the written archive.\n");

    exit(1);
}
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();

$bad = 0;
foreach ($textEdits as $i => [$from, $to]) {
    $g = substr_count($after, $to);
    $l = substr_count($after, $from);
    printf('  edit %d: new form %d (want 1), old form %d (want %d)%s', $i, $g, $l, $before[$i] - 1, PHP_EOL);
    if ($g !== 1 || $l !== $before[$i] - 1) {
        $bad++;
    }
}
// The claim is about the document, so it is checked against the document rather than trusted from the plan.
$rowXml = substr($after, (int) strpos($after, 'Two censuses, two privacy holes'), 200000);
$left = preg_match_all('/(AssessmentQueueSortTest|AccountingQueueSortTest|ClearanceQueueSortTest)[^<]{0,40}?\d+ cases/iu', html_entity_decode($rowXml), $lm);
printf('  class figures left in ROW 38 after the write: %d (want 0)%s', $left, PHP_EOL);
if ($left !== 0) {
    echo '  still present: '.implode(' | ', $lm[0])."\n";
    $bad++;
}
if ($bad > 0) {
    fwrite(STDERR, "REFUSED: {$bad} read-back check(s) failed.\n");
    if (is_file($backup) && copy($backup, $docx)) {
        fwrite(STDERR, 'restored '.basename($backup).'; nothing of the bad write is left.'."\n");
    } else {
        fwrite(STDERR, "AND no backup was kept, so the write stands — read ROW 38 back before editing anything else.\n");
    }

    exit(1);
}
echo 'written, re-read: the row names the instrument and the matrix, and holds no per-class figure of its own.'."\n";
