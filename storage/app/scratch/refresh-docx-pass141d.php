<?php

/**
 * Pass 141d — the revision row gains the mutant harness that had gone to sleep.
 *
 * Lane 45's own verification re-ran `mutate-lane44-unique-keys.php` and two of its cases failed: they planted
 * defects by replacing text prefixed with the flat dump's own row ordinal, and pass 141b had inserted one
 * paragraph earlier in §18.3, which renumbers every ordinal after it. Both str_replace calls then matched
 * nothing, so the "mutants" ran the checker against clean text and reported its clean verdict as a result.
 * The harness locates the row by its cell content now and refuses any case whose mutation changes no bytes;
 * the fix is proved by running a variant whose anchor cannot match, which reports MUTANT DID NOT APPLY and
 * exits non-zero.
 *
 * Only text inside an existing paragraph moves, so the dump's element count is unchanged and no citation in the
 * document shifts.
 *
 * DRY RUN unless --apply.
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

docxApply(
    $src,
    $root.'/storage/app/scratch/docx-backup-2026-10-17-pass141d.docx',
    [
        ['including one harness that first reported five missed classes because the --dir= flag it passed was '
            .'never read, and one that slept because an unreadable claim moved the same counter as a checked one.',
            'including one harness that first reported five missed classes because the --dir= flag it passed was '
            .'never read, one that slept because an unreadable claim moved the same counter as a checked one, and '
            .'one whose two row mutants had stopped applying at all because they were anchored on the flat dump’s '
            .'own row numbers, which any inserted paragraph renumbers — a mutant that cannot apply is a mutant that '
            .'cannot fail, and the only thing that found it was running the harness again after an unrelated '
            .'document edit. Rows are located by their own cell content there now, and a case whose mutation '
            .'changes no bytes is refused before it runs.'],
    ],
    [],
    $apply
);
