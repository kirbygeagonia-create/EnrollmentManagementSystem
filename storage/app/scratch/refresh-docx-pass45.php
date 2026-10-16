<?php
/**
 * Pass 45 — the Department Evaluation citations, re-read against the file they point at.
 *
 * The range probe proved no citation runs past the end of its file, but the controller has
 * grown by hundreds of lines as rulings were built, so dozens of in-range cites now land in
 * the wrong method. Each replacement below was resolved by finding the code that actually
 * implements the sentence’s rule and reading its line number off the current file:
 *   queue filter 68-103 (whereIn at 75) · missingProfileFields 294-356 · captureProfile 358-464
 *   decideStanding 465-508 · proposeSubjects 510-642 (duplicates 520-526, electives 542-558,
 *   mandatory+credited 561-581, flexible exemption 571-581, prerequisite gate 585-604,
 *   transition to evaluated 630-640) · processCredits 643-721 (passResult written 692) ·
 *   sign 722-762 (Guidance box 750) · retentionBlocker 786-840 · recordRetention 994-1021
 *   satisfiedPrerequisites 258-292 · resolveCurriculum 235-256
 * The four copies of :335-357 are left to pass 46, because three of them mean the flexible
 * load and one means the curriculum lookup.
 */
require __DIR__.'/docx-edit-engine.php';

$src = 'Documentation/EMS Complete Documentation.docx';

$textEdits = [
    // Elective bounds — enforced in proposeSubjects, not where the profile guard lives.
    ['EvaluationController.php:320-333', 'EvaluationController.php:542-558'],
    [':470-476', ':542-558', null, 2],

    // Duplicate-subject rejection.
    ['EvaluationController.php:295-299', 'EvaluationController.php:520-526'],
    ['EvaluationController.php:294', 'EvaluationController.php:520-526'],

    // Credited subjects leave the must-take set; the flexible-load exemption.
    ['EvaluationController.php:336-342', 'EvaluationController.php:563-568'],
    ['EvaluationController.php:336-343', 'EvaluationController.php:563-568'],
    ['EvaluationController.php:336-357', 'EvaluationController.php:571-581'],
    ['EvaluationController.php:344-356', 'EvaluationController.php:571-581', null, 2],

    // The prerequisite auto-gate, and the curriculum it is evaluated across.
    ['EvaluationController.php:359-370', 'EvaluationController.php:585-604'],
    ['EvaluationController.php:360-385', 'EvaluationController.php:585-604', null, 2],
    ['EvaluationController.php:388-389', 'EvaluationController.php:235-256'],

    // Profile capture writes the student fields; the guard reads them.
    ['EvaluationController.php:192-282', 'EvaluationController.php:358-464'],
    ['EvaluationController.php:289-330', 'EvaluationController.php:294-356', null, 2],

    // The grade ceiling and its 3.00 fallback live with the satisfied-prerequisite lookup.
    ['EvaluationController.php:163-164', 'EvaluationController.php:258-292', null, 2],

    // The desk’s queue.
    ['EvaluationController.php:57-64', 'EvaluationController.php:68-103', null, 2],

    // pending → evaluated, written when the load is proposed.
    ['EvaluationController.php:408', 'EvaluationController.php:630-640', null, 3],

    // Credit processing, and where the workflow box is signed.
    ['EvaluationController.php:417-460', 'EvaluationController.php:643-721'],
    ['EvaluationController.php:440-456', 'EvaluationController.php:643-721'],
    ['EvaluationController.php:462-482', 'EvaluationController.php:722-762'],

    // The retention examination: its blocker and its recorder.
    ['EvaluationController.php:491-518', 'EvaluationController.php:786-840'],
    ['EvaluationController.php:690-740', 'EvaluationController.php:994-1021'],
    ['EvaluationController.php:111,490', 'EvaluationController.php:786-840'],

    // Standing is set at the standing desk, and religion is required by the capture form.
    ['EvaluationController.php:324', 'EvaluationController.php:465-508'],
    ['EvaluationController.php:298', 'EvaluationController.php:371'],

    // The two progress columns are maintained by hand in the capture form.
    ['EvaluationController.php:229-252', 'EvaluationController.php:395-418'],
];

$cellEdits = [
    // §27.5 — transferacademicrecords.passResult is no longer vocabulary nothing writes.
    ['Failed subjects from a previous school are never recorded',
     'no code — the credit path writes passed',
     'DeptEvaluator through evaluation.credits.process — processCredits stores the officer’s own judgement per subject, passed or failed ( EvaluationController.php:692 )',
     1],
    ['Failed subjects from a previous school are never recorded',
     'no rule',
     'the credit path itself, which stores a failed transfer record, counts it as retained and writes no credit for it ( EvaluationController.php:696-699 , ruling 10)',
     1],
    ['Failed subjects from a previous school are never recorded',
     'Failed subjects from a previous school are never recorded',
     'Closed by ruling 10: a failed transfer row is recorded and earns no credit. The value no longer documents an intention — it carries a decision, and the credit is what depends on it.',
     1],
];

$paraEdits = [
    // §13 — the office-coding quirk, after the bare integer became the enum.
    ['via a bare integer at',
     'Quirk C-3 (office coding). Signing the evaluation writes box 1, whose officeId is 4 (Guidance), through App\Enums\OfficeId::Guidance at EvaluationController.php:750. The bare integer this paragraph used to record is gone — the enum is what the seeder, the policies and this write all compare now (§22) — but the coding quirk itself stands and is not a defect of the code: box 1 is Department Evaluation’s work announced under Guidance’s office, so a reader of the workflow table should expect the notice to name Guidance (§19.2, §28.2 C-3).'],
];

$apply = in_array('--apply', $argv, true);
docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-06-pass45.docx', $textEdits, $cellEdits, $apply, $paraEdits);
