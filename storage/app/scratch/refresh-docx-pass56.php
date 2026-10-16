<?php
/**
 * Pass 56 — the last four WorkflowService box-list citations, and §25's print-log paragraph.
 *
 * The four copies of WorkflowService.php:27-46 were one defect with three different answers: the
 * box list is stepsFor() at :49-80 (the omission itself at :63-66), while one row means the order
 * check inside signStep() (:109-185). Each is anchored to its own sentence so the swap cannot be
 * applied to the wrong copy.
 *
 * §25's print-log paragraph was written before the print keys and before P-3 closed. Measured on
 * live ems today: eight columns, 80 rows, every desk print writing its own row, 21 rows carrying
 * none of the three keys, and the relation declared at Enrollments.php:234-236 / Staffusers.php:182-184
 * — not at the :120-125 the paragraph cites, which is the year-level derivation.
 */
require __DIR__.'/docx-edit-engine.php';

$src = 'Documentation/EMS Complete Documentation.docx';

$textEdits = [
    ['WorkflowService.php:27-46', 'WorkflowService.php:49-80', 'not the same shape for every student type'],
    ['WorkflowService.php:27-46', 'WorkflowService.php:63-66', 'A skipped box is never signed'],
    ['WorkflowService.php:27-46', 'WorkflowService.php:109-185', 'the order check walks the positional'],
    ['WorkflowService.php:27-46', 'WorkflowService.php:49-80', 'produces seven signature boxes'],
];

$paraEdits = [
    ['61 rows in the demo database',
     'documentprintlog carries eight columns — printLogId, the three nullable keys enrollmentId, studentId and blockId, then documentType, printedDate, printedBy and documentNumber — and 80 rows on live ems: 19 clearance slips, 21 class cards, 18 certificates, 18 subject loads and 4 block schedules. Every print through a desk route inserts its own row, the block schedule included (P-3, closed 2026-10-04); the enrollment form has a print route and no row yet, because nobody has drawn that paper on this install, which is a fact about the demo rather than a defect. Twenty-one rows carry none of the three keys — the legacy pile ruling 6 keeps and reserves instead of deleting or repairing — and that is exactly the set the unique index over ( enrollmentId , documentType , documentNumber ) cannot see, because MySQL treats their NULLs as distinct (§25, P-14). The relation is declared from Enrollments ( app/Models/Enrollments.php:234-236 ) and from Staffusers ( app/Models/Staffusers.php:182-184 ), so a print can be traced both from the record it describes and from the hand that printed it.'],
];

$apply = in_array('--apply', $argv, true);
docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-07-pass56.docx', $textEdits, [], $apply, $paraEdits);
