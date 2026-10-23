<?php

/**
 * Pass 179b — what the checkers said after pass 179 wrote.
 *
 * lane60-ratio-claims refused the run on two figures, and both are real:
 *   - §24's cross-check row still adds the dictionary up to 410 columns for the 55 domain tables;
 *     with studentclearances down to 8 the headings now sum to 409. Measured: all 63 headings agree
 *     with live ems, so the aggregate is the only figure left holding the old schema.
 *   - the 2026-10-21 §1.1 revision row states "452 columns" as a living figure, because pass 179
 *     moved "(this issue)" off it and lane 60 does not read that marker. The row is a record of a
 *     day, so the record keeps its number and says which day it was read — the alternative,
 *     rewriting 452 to 451 inside a 2026-10-21 row, would make the dated row state a figure that
 *     was not true on that date.
 *
 * lane32-cite-meaning-sweep gained one flag from my own sentence: `Payments::held()` sat directly
 * before "( AssessmentController.php:123-127 )", a range that belongs to the name before it, not
 * to held(). One name per range now.
 *
 * DRY RUN unless --apply.
 */

require_once __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $fails++;
    }
};

require_once $root.'/vendor/autoload.php';
$app = require_once $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$heads = DB::select("SELECT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME IN (SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_TYPE='BASE TABLE') GROUP BY TABLE_NAME HAVING COUNT(*) > 0");
$guard(count($heads) === 63, 'the server answers the 63 tables §24 documents (read '.count($heads).')');
$guard((int) DB::select('SELECT COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()')[0]->c === 451,
    'and 451 columns across the schema');
// The 409 this pass writes is lane60-ratio-claims.php's own reading of the 55 domain tables — its
// column-aggregate claim, which refuses rather than guesses. It is not re-derived here from a
// remembered list of framework tables: lane 60's own finding was that a hardcoded partition answers
// "54" where the server says 55. After the write, `--run` must report MATCHES for that claim.

$textEdits = [
    [
        'over its rows — 63 tables',
        'over its rows, and those five figures are as of 2026-10-21 — 63 tables',
    ],
];

$cellEdits = [
    ['>Column counts<', '410 columns documented',
        '409 columns documented for 55 domain tables'],
    ['>E-3<', 'Status moves to returnedToEvaluation',
        'Status moves to returnedToEvaluation, the reason is stored, and the payments and the already signed workflow boxes '
            .'stay exactly as they are. Ruled 2026-10-23 that this is the policy and not an omission: a returned enrollment '
            .'keeps its receipts, because a receipt is evidence that money was handed over and a return does not un-hand it. '
            .'A second fee sheet cannot appear behind the account either — AssessmentController::compute() refuses one '
            .'( AssessmentController.php:123-127 ) — and Studentassessments::outstandingBalance() '
            .'( app/Models/Studentassessments.php:81-86 ) subtracts the money held on that enrollment, so the student who '
            .'comes back pays the difference.'],
];

docxApply($src, $root.'/storage/app/scratch/pass179b-backup.docx', $textEdits, $cellEdits, $apply);
