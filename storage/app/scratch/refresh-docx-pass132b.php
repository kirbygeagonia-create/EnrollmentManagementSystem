<?php

/**
 * Pass 132b — one citation pass 132 missed because of how it is written.
 *
 * The workflow-dictionary row reads "WorkflowService.php:64-66 ( unset($steps[2]) ), :63-66 ( … ),
 * signStepByOffice() :289-305": the file is named once and the ranges after it are continuations, the first
 * two bare and the third prefixed by the method it names. Hunting `WorkflowService.php:NN-NN` therefore found
 * none of the last one, and the line sweep agreed the document was clean — the range lands on live lines
 * either way. It was the meaning sweep that named it: it asks which construct the prose cites and answers
 * `signStepByOffice() :307`, i.e. the range still points inside signStep() after the predicate insert moved
 * the method down 18 lines.
 *
 * 307-323 is the method's own span, measured with measure-method-span.php after it learned to match
 * `public static function` (the predicate was invisible to the first version of that script, which is how
 * maySignAcrossOffices() reported NOT FOUND on a method that exists).
 *
 * The first version of this file used `signStepByOffice() :289-305` as the needle and the engine reported zero
 * occurrences: Word put the method name and its range in SEPARATE `<w:t>` runs (the name is monospace-styled),
 * and the flat dump the sweeps read joins them. So the needle is the range alone, which is unique in the
 * document and lives inside one run — measured, not assumed: `substr_count($xml, ':289-305')` is 1.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';

$textEdits = [
    [':289-305', ':307-323'],
];

require __DIR__.'/docx-edit-engine.php';
docxApply($src, $root.'/storage/app/scratch/docx-backup-2026-10-16-pass132b.docx',
    $textEdits, [], in_array('--apply', $argv, true));
