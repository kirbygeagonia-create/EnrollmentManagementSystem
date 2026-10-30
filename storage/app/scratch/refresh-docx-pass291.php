<?php

/**
 * Pass 291 — §28.3 G-43 closes, and the row's own claims about its instrument are corrected first.
 *
 * Three kinds of change travel together here, because the row was written while the gap was open:
 *   1. the verdict word (Open to Closed), which §28.4's group row, §29.4's settled tally and lane 61 all read;
 *   2. the instrument's positive control, which this batch REPLACED — it used to expect the five columns the
 *      tree carried the day it was written, so it refused against its own success once a write was repaired;
 *      it now plants its own defect;
 *   3. the family's size, measured again after the writes: 8 names over 48 sites before, of which 38 fed a
 *      column the migrations declare DATE (8 on desk paths, 30 in the demo generator) and 10 are instants.
 *
 * Every needle is counted in document.xml before anything is written and the pass REFUSED with the counts,
 * because a register cell is a run sandwich: the prose, the code spans, and the punctuation are separate runs.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass291.php [--apply]
 */

$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', array_slice($argv, 1), true);

// [needle, replacement, expected count]
$edits = [
    // 1. the verdict word
    [
        'Open 2026-10-29, and the family is measured not recalled:',
        'Closed 2026-10-29, and the family was measured, not recalled, before anything was typed:',
        1,
    ],
    // 2. what actually moved, and where the rest of the family lives
    [
        'five sites on the desk paths.',
        '38 sites that feed them: eight on the desk paths and thirty inside the demo generator, which `phpstan` does not analyse and `pint.json` excludes.',
        1,
    ],
    // 3. the control, re-described as it now works
    [
        'The instrument carries its own positive control: it refuses the run unless all five named columns come back as candidates, because a sweep that cannot say',
        'Its positive control plants its own defect rather than expecting the tree to keep one: a throwaway file carrying one write per DATE column the migration reader found is scanned through the same pattern, and the run refuses unless every planted column name comes back. A control keyed to the five columns live `ems` was holding on the day it was written would have had to be deleted the moment the gap closed — it reported REFUSED against the batch that fixed it, which is the expectation-that-ages defect in a new place. The guard is that a sweep that cannot say',
        1,
    ],
    // 4. the tail: what the batch built, what pins it, what proves it
    [
        'The two admission stamps moved to ',
        'All 38 moved to ',
        1,
    ],
    [
        ' and the day case freezes the clock so it cannot pass while both clocks agree. Three writers remain. ',
        ', four of them with a behaviour case that freezes the clock at 18:00Z and asserts first that the two clocks disagree, so no case can pass by their agreeing: `AssessmentController::compute`, `IDController::create`, `ClearanceController::approveRequirement` and `EvaluationController::sign`. A scratch schema built in CI’s own order — migrate, the seeders, the pipeline, then the walkthrough legs — answers 0 on all seven `Ledger —` lines and 8 tests over 207 assertions on the dataset the repaired generator produced. ',
        1,
    ],
    [
        'Lane 50’s PHP half answers 0 over them: it matches ',
        'Lane 50’s PHP half had answered 0 over every one of them, and kept answering 0 after the writes moved, because it matches ',
        1,
    ],
    [
        ' shapes a ',
        ' shapes a ',
        1,
    ],
    [
        ' — so the instrument for this class is not built yet.',
        ' — the shape this row closed with. `mutate-lane87-school-day-writes.php` proves the whole batch: it plants the retired write at each pinned site and requires both its case to report one failure and `lane87 --ci` to exit 1 naming that column, asks the same question of a site no test pins (`RegistrarController`’s `enrolledDate`, which only the instrument can see), then blinds the instrument in two copies — a pattern that cannot match its own planted file, and a control that keys recovery by line instead of by column name — each of which must refuse rather than print CLEAN.',
        1,
    ],
];

$zip = new ZipArchive;
if (! $zip->open($docx)) {
    fwrite(STDERR, "REFUSED: cannot open {$docx}.\n");

    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$failures = 0;
foreach ($edits as $i => [$from, $to, $want]) {
    $n = substr_count($xml, $from);
    $mark = $n === $want ? 'ok  ' : 'MISS';
    printf("%s %d  want %d  got %d  [%s]\n", $mark, $i, $want, $n, mb_substr($from, 0, 62));
    if ($n !== $want) {
        $failures++;
    }
    if ($from === $to) {
        echo "     note: edit {$i} is a no-op needle kept only to prove the bytes between two runs are what the row says.\n";
    }
}

if ($failures > 0) {
    fwrite(STDERR, "\nREFUSED: {$failures} needle(s) did not land. NOTHING WRITTEN.\n");

    exit(1);
}

$applyEdits = array_values(array_filter($edits, fn (array $e): bool => $e[0] !== $e[1]));
$textEdits = array_map(fn (array $e): array => [$e[0], $e[1], null, $e[2]], $applyEdits);

printf("\n%d edit(s) to apply (%d no-op probe(s))\n", count($textEdits), count($edits) - count($applyEdits));

docxApply($docx, $root.'/storage/app/scratch/pass291-backup-'.date('Ymd-His').'.docx', $textEdits, [], $apply);
