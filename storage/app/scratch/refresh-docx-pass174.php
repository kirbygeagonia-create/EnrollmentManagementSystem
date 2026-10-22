<?php

/**
 * Pass 174 — CI answered STALE on the sentence pass 173d wrote, and CI is right.
 *
 * "The suite on this box answers 780 tests and 5,220 assertions, and CI answers 5,013 assertions on its SQLite
 * job…" is ONE claim to lane58-suite-claims.php: it carries two totals and names two surfaces, and the checker
 * judges a multi-total sentence on its FIRST figure. On this box that figure matched and the run reported the
 * second sentence as a blind spot; on the runner the same figure was compared with the runner's own junit
 * (780 tests / 5,013 assertions) and answered STALE — which is the correct verdict for a claim that cannot say
 * which run it means.
 *
 * The row keeps only the figure both surfaces can check — the case count `phpunit --list-tests` discovers — and
 * leaves the per-run assertion totals to the gate note beside the tool that prints them. That is the lane's own
 * rule: an assertion total is a property of a run, so a living sentence must name the run it belongs to.
 *
 * DRY RUN unless --apply.
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$needle = 'The suite on this box answers 780 tests and 5,220 assertions, and CI answers 5,013 assertions on its '
    .'SQLite job and 5,015 on its MySQL job for the same 771 tests, which is why an assertion total now cites a '
    .'run rather than the code.';
$replacement = 'The suite is 780 cases, which is what `phpunit --list-tests` discovers on both machines; the '
    .'assertion totals this build produced are stated in the gate note beside the runs that printed them, because '
    .'an assertion total is a property of a run and a living sentence may not name two runs at once.';

$flat = (string) file_get_contents($root.'/storage/app/scratch/docx-flat.txt');
if (substr_count($flat, $needle) !== 1) {
    printf("REFUSED: the sentence is present %d time(s), not once.\n", substr_count($flat, $needle));
    exit(1);
}

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass174.docx', [
    [$needle, $replacement],
], [], $apply);
