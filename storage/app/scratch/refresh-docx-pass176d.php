<?php

/**
 * Pass 176d — two sentences that named a case total, after this batch added two tests.
 *
 * Both rows said the suite "is 780 cases", and `phpunit --list-tests` now discovers 782, so lane58-suite-claims.php
 * reported them stale on the run that made them stale. That is the lane-58 rule pointed back at the document: a
 * revision row that has to outlive the run it was read from either names one surface or states no total at all.
 * These two sentences exist to make the point about *surfaces*, not to publish a number, so the digits go and the
 * claim stands — and the next test anyone writes cannot stale them again.
 *
 * DRY RUN unless --apply.
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$textEdits = [
    [
        'The suite’s own size is what `phpunit --list-tests` discovers: 780 cases today, and 771 plus 9 accounts for '
            .'the same number, so these are four readings of one suite rather than four suites, and the discovered '
            .'count is the one figure here that means the same thing on this box and on the runner.',
        'The suite’s own size is what `phpunit --list-tests` discovers, and the passed-plus-skipped tally each CI job '
            .'prints accounts for that same number, so these are four readings of one suite rather than four suites. '
            .'The discovered count is the only figure of the four that means the same thing on this box and on the '
            .'runner, which is why this row now states none: a case total ages the moment anyone writes a test.',
    ],
    [
        'The suite is 780 cases, which is what `phpunit --list-tests` discovers on both machines;',
        'The suite’s case count is whatever `phpunit --list-tests` discovers, and that is the one suite figure that '
            .'means the same thing on both machines;',
    ],
];

docxApply($src, $root.'/storage/app/scratch/pass176d-backup.docx', $textEdits, [], $apply);
