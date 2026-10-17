<?php

/**
 * Pass 141c — Pint moved the controller a row, and the suite total is now read rather than predicted.
 *
 * Pass 141b re-pointed five IDController.php ranges onto line numbers measured off the file as written. Pint
 * then added one `use Illuminate\Database\Eloquent\Builder;` line — the only fix it wanted in that file — and
 * every method below the imports moved by a row, so all five numbers went stale one pass after being measured.
 * The line sweep could not see it (557 citations, 0 problems: the ranges still land on live lines) and the
 * meaning sweep mostly could not either (the cited span still encloses the named method). Only re-running the
 * measurement after the formatting gate finds it, which is why AGENTS.md now says to run Pint first.
 *
 * Re-read from the file after Pint, by construct rather than by arithmetic:
 *   create()        :160-186   opens at 160, its `}` at 186; attachPhoto's docblock opens at 188.
 *   attachPhoto()   :191-221
 *   validate()      :244-263
 *   recordRemark()  :275-286   287 is the class brace.
 *   bloodType       :168 the `required|in:A+,…` rule, :180 the write that copies it.
 *
 * The suite figure is the whole-run result, not a prediction: `php artisan test` ended
 * "Tests: 730 passed (4599 assertions)", duration 1925.67s, exit 0.
 *
 * DRY RUN unless --apply.
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

docxApply(
    $src,
    $root.'/storage/app/scratch/docx-backup-2026-10-17-pass141c.docx',
    [
        ['IDController.php:159-185', 'IDController.php:160-186', null, 2],
        [':190-220', ':191-221'],
        [':243-262', ':244-263'],
        [':274-285', ':275-286'],
        ['IDController.php:167, :179', 'IDController.php:168, :180'],
        ['The suite stands at 725 tests and 4570 assertions',
            'The suite stands at 730 tests and 4599 assertions'],
    ],
    [],
    $apply
);
