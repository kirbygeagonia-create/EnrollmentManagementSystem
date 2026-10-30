<?php

/**
 * Pass 293 — two sentences pass 291 and 292 wrote state a total on a surface CI cannot read.
 *
 * `lane58-suite-claims.php` binds a STEP figure to one folder's own step and a SUITE figure to the runner's
 * junit, and refuses what it cannot bind. My §1.1 row and §28.3's G-43 row both quoted the scratch-schema
 * rehearsal's walkthrough totals (8 tests / 207 assertions) — that reading exists only on this box, on a
 * schema the runner has never seen, so the right repair is the rule §1.1 already carries: name one surface,
 * or state no total at all. The rehearsal keeps its verdict ("no finding", "green") and loses the number.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass293.php [--apply]
 */

$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', array_slice($argv, 1), true);

$edits = [
    [
        'Run in CI’s order with the connection pinned, the same schema answers 0 on all seven `Ledger —` lines and OK on 8 tests over 207 assertions.',
        'Run in CI’s order with the connection pinned, that rehearsal printed no finding in the generator’s own desk report and the walkthrough folder came back green beside it; its totals are deliberately not quoted, because a scratch-schema rehearsal is a surface the runner cannot read — §1.1’s one-surface-per-figure rule, met against a figure this batch wrote.',
        1,
    ],
    [
        'answers 0 on all seven `Ledger —` lines and 8 tests over 207 assertions on the dataset the repaired generator produced',
        'printed no finding on the generator’s own desk report and a green walkthrough folder on the dataset the repaired generator produced, with no totals quoted because that rehearsal is a surface the runner cannot read',
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
    printf("%s %d  want %d  got %d  [%s]\n", $n === $want ? 'ok  ' : 'MISS', $i, $want, $n, mb_substr($from, 0, 56));
    if ($n !== $want) {
        $failures++;
    }
}
// The old totals must be gone from the document, not merely re-worded beside themselves.
foreach (['8 tests over 207 assertions', 'OK on 8 tests over 207 assertions'] as $gone) {
    printf("after-check %-42s occurs %d (want 0)\n", $gone, substr_count($xml, $gone));
}
if ($failures > 0) {
    fwrite(STDERR, "\nREFUSED: {$failures} needle(s) did not land. NOTHING WRITTEN.\n");

    exit(1);
}

$textEdits = array_map(fn (array $e): array => [$e[0], $e[1], null, $e[2]], $edits);
docxApply($docx, $root.'/storage/app/scratch/pass293-backup-'.date('Ymd-His').'.docx', $textEdits, [], $apply);
