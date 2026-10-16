<?php
/**
 * Pass 53 — WorkflowService and EnrollmentStateMachine citations.
 *
 * The workflow service grew a docblock on stepLabels() that pushed every later method down, so a
 * family of citations landed in the label table while their sentences describe the box list. The
 * real map is: stepLabels :27-48 (its docblock carries the BR4 scholarship rationale at :44-45),
 * stepsFor :49-80 (the omission at :63-66, renumbered by array_values at :64-66),
 * createWorkflow :81-108, signStep :109-185 (the office test at :134). In the state machine the
 * transition body — the history row and the event, both inside one transaction — is :59-96.
 *
 * One family is deliberately left: four citations of WorkflowService.php:27-46 whose sentences
 * mean stepsFor. The span they name is the label table, whose own docblock says why the boxes are
 * keyed by office rather than by order — related, but not the cited rule. Fixing them needs a
 * per-site anchor, because one of the four really does mean the order check in signStep.
 */
require __DIR__.'/docx-edit-engine.php';

$src = 'Documentation/EMS Complete Documentation.docx';

$textEdits = [
    ['WorkflowService.php:21-24', 'WorkflowService.php:44-45', null, 2],
    ['WorkflowService.php:29', 'WorkflowService.php:63-66', null, 4],
    ['WorkflowService.php:93-111', 'WorkflowService.php:109-185 , the office test at :134', null, 4],
    ['WorkflowService.php:93-107', 'WorkflowService.php:109-185'],
    ['WorkflowService.php:59', 'WorkflowService.php:81-108'],
    ['WorkflowService.php:41-45', 'WorkflowService.php:63-66', null, 2],
    ['WorkflowService.php:41-46', 'WorkflowService.php:64-66'],
    ['EnrollmentStateMachine.php:56-77', 'EnrollmentStateMachine.php:59-96'],
];

$apply = in_array('--apply', $argv, true);
docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-06-pass53.docx', $textEdits, [], $apply);
