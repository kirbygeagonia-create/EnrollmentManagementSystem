<?php
/**
 * Pass 47 — the tail of the Evaluation re-read: the last flexible-load copy, the two rows that
 * cite methods by their old line numbers, the §13 gap narrative that G-2 closed, and the print
 * actions §24 lists. The changelog row then records that this reconciliation happened, so the
 * next reader knows which edition the §28 register was checked against.
 */
require __DIR__.'/docx-edit-engine.php';

$src = 'Documentation/EMS Complete Documentation.docx';

$textEdits = [
    // §5.2 — the fourth copy, now the only one left after pass 46.
    ['EvaluationController.php:335-357', 'EvaluationController.php:571-581'],

    // §1.4 changelog — say what this batch did, in the row that already dates the issue.
    ['while production keeps the worker (C-10)',
     'while production keeps the worker (C-10). The consolidated decision list was then reconciled with the registers that closed it: §28.5 asks nineteen questions, fourteen of them answered by rulings 3, 8, 9, 12, 13 and 15 or by the 2026-10-06 decisions, and each of those exists twice — in §25.11, §26.7 or §27.7 and again here — so every copy was rewritten in the same write rather than one at a time. The chapters that state the old behaviour as a present fact were re-read too (§12.1, §21.1, §21.3, §22.3, §23.6, §25.6, §26.4–§27.5, §28.6), the permission counts re-read from the seeder and the live tables (95 in 15 modules, 17 roles, 317 grants), and the Department Evaluation citations were re-read line by line against the controller, which had grown by hundreds of lines and left dozens of in-range but mis-pointed file:line references.'],
];

$cellEdits = [
    // §13 action table — propose subjects: the status edge is a transition, not a method.
    ['honouring elective groups, prerequisites, and credited subjects',
     'enrolledsubjects rows in proposed status',
     'enrolledsubjects rows in proposed status, and the pending → evaluated edge the same write signs ( EvaluationController.php:510-642 , transition at :630-640 ).',
     1],

    // §24 — the print actions, listed at their current lines.
    ['Which document was produced for whom',
     'The print actions',
     'The print actions — RegistrarController.php:360 , :382 , :413 (the three desk pages) and :437 , :452 , :464 , :490 (the generated PDFs), ClearanceController.php:376 and :404 , BlockingController.php:559 , and the one writer behind all of them, PrintService::recordIssue() at PrintService.php:105',
     1],

    // §27.4 T-5 — the satisfied-prerequisite lookup lives with the credit read, not the block.
    ['a shifter whose credits were never keyed in',
     'EvaluationController.php:571-581',
     'EvaluationController.php:571-581 , :258-292 ( satisfiedPrerequisites , which counts a credit only where the transfer record says passed)',
     1],
];

$paraEdits = [
    // §13 — the gap narrative G-2 closed, anchored on the line that used to write the constant.
    ['AdmissionController.php:327',
     'Gap G-2 (year level), closed 2026-10-04. The field used to be written once, as a constant, and no controller, request or service ever updated it. Enrollments::derivedYearLevel() ( app/Models/Enrollments.php:86 ) now answers the level from the student’s own years that closed at enrolled, Admission approval stamps it ( AdmissionController.php:390 ) and the issue form opens on it; the semester half of the question was always the term row’s own, so no screen asked a person for it. Two things the rule deliberately leaves to the desks: a placement decideStanding still accepts over the derivation ( EvaluationController.php:465-508 ), and a block states its own year level on the form it is created with ( BlockingController.php:165 ) and filters its roster by it ( :102 ), so a proposed load is read against both.'],
];

$apply = in_array('--apply', $argv, true);
docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-06-pass47.docx', $textEdits, $cellEdits, $apply, $paraEdits);
