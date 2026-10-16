<?php

/**
 * Pass 41 — §28.4's grouping and §28.5's decision list, which sit below the register and were not
 * re-read with it. §28.5 is the page the team takes to the school, so a question there that the
 * build already answers costs the project credibility twice over.
 */

require __DIR__.'/docx-edit-engine.php';

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$textEdits = [
    // §25.4's own preamble: make the date of verification part of the claim.
    ['Every row below was verified against the current source, not against an earlier revision of this document.',
        'Every row below was verified against the current source, not against an earlier revision of this document — the re-read was done on 2026-10-06 and eleven of the fifteen findings had been closed underneath it, which is why the register opens with the date rather than a promise.'],
];

$cellEdits = [
    // §28.4's grouping rows. The middle column is only an id list, so the correction goes in the
    // third column, which is where this table says what closing the group would take.
    ['A one-line change to a template or page', 'The field is already in the data the print receives',
        'P-1, P-5, P-6 and P-11 were closed 2026-10-02: the field was already in the data the print receives, and closing meant rendering it. Only P-7 is left, and only for the header strings — its letterhead image is embedded now.'],
    ['A decision before any code', 'The registrar must say what the document is for',
        'All three are settled. The fee is Reference Data’s alone (ruling 8), office 8 is retired in data (ruling 12), and the subject load has its own template, so the act this row waited for was taken and built.'],
    ['A schema change', 'clearancerequirements has two columns',
        'Closed. clearancerequirements gained requirementName on 2026-09-30 and the checklist is seeded from the named office list; live ems reads nine named lines and none unnamed.'],
    ['A correctness fix in which row is read', 'The template reads student.enrollments[0]',
        'Closed. The slip reads the enrollment of the clearance period’s own term, keyed by (studentId, period → termId), so one term’s paper can no longer describe a different term’s load.'],
    ['An audit or numbering design change', 'One desk print writes no documentprintlog row',
        'Closed for new prints. Every desk print writes its own row, and one path mints every number inside the document’s own scope, stepping over the numbers the key-less legacy rows hold. What the schema still cannot reject is a repeat among those 21 NULL-keyed rows, because MySQL treats NULLs as distinct.'],
    ['A wording or deployment change only', 'A certificate that says',
        'Closed. The certificate prints the student type in its own words and the enrollment kind separately, and generatePdf() resolves an installed browser through the locator the fidelity command uses. Production still needs the Chromium install DEPLOYMENT.md §6 names, or the print fails at runtime rather than at deploy time.'],

    // §28.5's decision list is handled by pass 42, which rewrites the question in every table
    // that repeats it: §25.11 and §26.4 each carry a copy, so a single-anchor edit would leave
    // one of them asserting the old state.
];

docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-06-pass41.docx', $textEdits, $cellEdits, $apply);
