<?php

/**
 * Docx pass 330b — ROW 38's two class figures are re-taken from the newest whole-suite run of its own day.
 *
 * ROW 38 (2026-10-30, "Two censuses, two privacy holes, and a fix that made its own defect") states
 * AssessmentQueueSortTest 9 cases / 35 assertions and AccountingQueueSortTest 10 cases and 38 assertions. Both were
 * read from the run that existed when the row was written. The batch that closed G-51 and narrowed the student loads
 * then added an `assertOk()` and a nested-key assertion to each of those classes, and the delivered whole-suite run
 * answers 11 / 44 and 12 / 47.
 *
 * This is not re-aging a dated row to today's figure: the row's day is this day, and the run being cited ran on it.
 * The clause is re-worded so the sentence names WHICH run it read rather than implying the number is permanent —
 * lane 58's one-surface rule, applied to a class claim.
 *
 * Both figures also appear in the requirement matrix, where they were re-taken by
 * `lane107-retake-class-figures.php` from the same junit; this pass touches only the authority document, and its
 * needles are the two sentences above, each asserted to occur exactly once.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass330b.php [--apply]
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

$backup = $root.'/storage/app/scratch/pass330b-backup.docx';

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
        'The two class totals in this row are read from the junit of the whole-suite run that produced them on 2026-10-30 — AssessmentQueueSortTest 9 cases / 35 assertions, and ClearanceQueueSortTest re-taken at 9 / 32 after its status case stopped remembering one database’s order — because a figure beside a class name is a property of a run, not of the code.',
        'The two class totals in this row are read from the junit of the whole-suite run this row re-took last on that same day, 2026-10-30 — AssessmentQueueSortTest 11 cases / 44 assertions, and ClearanceQueueSortTest 9 / 32 after its status case stopped remembering one database’s order — because a figure beside a class name is a property of a run, not of the code, and the payload cases that grew since the first reading belong to the run that must be named.',
    ],
    [
        'AccountingQueueSortTest answers 10 cases and 38 assertions in the run whose junit this row read on 2026-10-30.',
        'AccountingQueueSortTest answers 12 cases and 47 assertions in the same re-taken reading of that day’s whole-suite junit.',
    ],
];

$failures = [];
$before = [];
foreach ($textEdits as $i => [$from, $to]) {
    $n = substr_count($xml, $from);
    $before[$i] = $n;
    printf('edit %d: %d occurrence(s) — %s%s', $i, $n, substr(trim($from), 0, 60), PHP_EOL);
    if ($n !== 1) {
        $failures[] = "edit {$i} matched {$n} time(s), wanted exactly 1";
    }
    if (str_contains($to, '|')) {
        $failures[] = "edit {$i}'s replacement carries a literal pipe, which the flat dump cannot tell from a cell boundary";
    }
    if (preg_match('/\.php:\d/u', $to) === 1) {
        $failures[] = "edit {$i}'s replacement introduces a line citation the pass has not verified";
    }
    // The figures written here must be what the delivered junit answers — typed from the tool, never from memory.
    if (preg_match('/(\d+) cases[ \/] and? ?(\d+) assertions/u', $to, $fm) === 1) {
        printf('       → first figure pair in this replacement: %s cases / %s assertions%s', $fm[1], $fm[2], PHP_EOL);
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
        echo "ALREADY APPLIED — both figures already carry the re-taken reading.\n";

        exit(0);
    }
    fwrite(STDERR, "REFUSED — nothing written:\n".implode("\n", $failures)."\n");

    exit(1);
}

// Cross-check the numbers being written against the junit, so a hand-edited pair cannot slip through as prose.
$junit = $root.'/storage/app/scratch/lane108-suite-junit.xml';
if (! is_file($junit)) {
    fwrite(STDERR, "REFUSED: no delivered junit at lane108-suite-junit.xml — this pass may only write figures it"
        ." has read off a run.\n");

    exit(1);
}
// Read by walking the document, not by one regex over it: PHPUnit writes a class suite as
// `Tests\Feature\Sorting\AssessmentQueueSortTest` nested inside the file's own suite, and the first cut of this
// guard tried to match that path with escaped backslashes in a single-quoted pattern, matched nothing, and refused
// a pass whose figures were right. The same lesson lane 107 paid for in `lane107-retake-class-figures.php`.
$jjx = simplexml_load_file($junit);
if ($jjx === false) {
    fwrite(STDERR, "REFUSED: the delivered junit did not parse.\n");

    exit(1);
}
$expect = [];
$walk = static function ($node) use (&$walk, &$expect): void {
    foreach ($node->children() as $key => $c) {
        if ($key !== 'testsuite') {
            if ($key === 'testsuites') {
                $walk($c);
            }

            continue;
        }
        $leaf = basename(str_replace('\\', '/', (string) $c['name']));
        if (str_contains($leaf, 'QueueSortTest')) {
            $expect[$leaf] = [(int) $c['tests'], (int) $c['assertions']];
        }
        $walk($c);
    }
};
$walk($jjx);
foreach (['AssessmentQueueSortTest', 'AccountingQueueSortTest', 'ClearanceQueueSortTest'] as $cls) {
    if (! isset($expect[$cls])) {
        fwrite(STDERR, "REFUSED: the delivered junit answers no figure for {$cls}; it is not a whole-suite junit.\n");

        exit(1);
    }
    printf("junit  %-26s %d cases / %d assertions\n", $cls, $expect[$cls][0], $expect[$cls][1]);
}
$newText = implode(' ', array_map(static fn ($e) => $e[1], $textEdits));
foreach ([['Assessment', '11 cases / 44 assertions'], ['Accounting', '12 cases and 47 assertions']] as [$part, $claim]) {
    if (! str_contains($newText, $claim)) {
        fwrite(STDERR, "REFUSED: the pass would write a figure it did not read — `{$claim}` is absent from its own replacements.\n");

        exit(1);
    }
}
if ($expect['AssessmentQueueSortTest'] !== [11, 44] || $expect['AccountingQueueSortTest'] !== [12, 47]
    || $expect['ClearanceQueueSortTest'] !== [9, 32]) {
    fwrite(STDERR, "REFUSED: the delivered junit answers Assessment ".$expect['AssessmentQueueSortTest'][0].'/'
        .$expect['AssessmentQueueSortTest'][1].' and Accounting '.$expect['AccountingQueueSortTest'][0].'/'
        .$expect['AccountingQueueSortTest'][1].', which are not the pairs this pass was cut to write — re-read the'
        ." junit, do not re-type the numbers.\n");

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
if ($bad > 0) {
    fwrite(STDERR, "REFUSED: {$bad} read-back check(s) failed after the write.\n");
    if (is_file($backup) && copy($backup, $docx)) {
        fwrite(STDERR, 'restored '.basename($backup).'; nothing of the bad write is left.'."\n");
    } else {
        fwrite(STDERR, "AND no backup was kept, so the write stands — read ROW 38 back before editing anything else.\n");
    }

    exit(1);
}
echo 'written, re-read: ROW 38 states the class figures the delivered run answers.'."\n";
