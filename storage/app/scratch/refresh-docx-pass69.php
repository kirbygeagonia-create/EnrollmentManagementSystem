<?php
/**
 * Pass 69 — exam.verify.general and ExamPolicy::verifyGeneralExam are retired, and every figure
 * and sentence that named them is re-measured.
 *
 * The method was declared and called by nothing, so the permission it asked for granted a check no
 * route performed. Two sentences written on 2026-10-07 had already admitted this ("declared and
 * called by nothing"), which was honest but left a dead right seeded, listed in the role matrix,
 * and named in §11's permission line. Owner's ruling 2026-10-08: delete both, and re-measure.
 *
 * Measured from the live server after the reseed, not computed: 94 permissions across 15 modules,
 * 315 role_permissions rows, 0 orphan grants, 17 roles. The seeder's own prune path reported
 * "Retired 1 obsolete permission(s): exam.verify.general" — the same mechanism that removed
 * id.release when card production was dropped, which is why no migration was needed.
 *
 * The 2026-10-04–06 revision row is left stating 95/317: that is a dated record of what was
 * measured then. A reader who wants today's number reads the exam module rows, not the history.
 */
require __DIR__.'/docx-edit-engine.php';

$src = 'Documentation/EMS Complete Documentation.docx';

$textEdits = [
    // ── Counts that describe the install TODAY.
    ['95 permissions across 15 modules', '94 permissions across 15 modules', null, 2],
    ['95 permissions, 17 roles', '94 permissions, 17 roles'],
    ['Every one of the 95 permissions', 'Every one of the 94 permissions'],
    ['ninety-five permissions sit in the catalogue', 'ninety-four permissions sit in the catalogue'],
    ['the 317 role_permissions rows', 'the 315 role_permissions rows'],
    ['317 rows', '315 rows'],

    // ── The three sentences that described the method as merely uncalled.
    ['ExamPolicy::verifyGeneralExam (ExamPolicy.php:57-63) states the same precondition against a '
     .'model and is called by nothing on this install, so the controller check is the enforcement; '
     .'the permission only it asks for, exam.verify.general, is then in the same position as the '
     .'retired sign rights §28 already keeps listed.',
     'The policy method that used to restate it, ExamPolicy::verifyGeneralExam, was deleted on '
     .'2026-10-08 and the permission only it read — exam.verify.general — was retired with it, on the '
     .'same ground as the retired sign rights: a right no code asks for is a line on the Roles screen '
     .'that a panel can be told means nothing. The live catalogue is 94 permissions and 315 grants '
     .'after the reseed, with no orphan grant left behind.'],

    ['ExamPolicy::verifyGeneralExam (ExamPolicy.php:57-63) restates that precondition against a '
     .'model and is called by nothing on this install, so it enforces nothing — and the permission '
     .'only it asks for, exam.verify.general, is unenforced with it.',
     'The policy method that used to restate it, ExamPolicy::verifyGeneralExam, was deleted on '
     .'2026-10-08 together with the permission only it read (exam.verify.general): declared and never '
     .'called is not enforcement, whatever a permissions table says, and leaving it seeded kept '
     .'implying a second check that no route performed.'],

    ['ExamPolicy.php:57-63, which is declared and called by nothing — the controller check is the '
     .'enforcement',
     'the ExamPolicy method retired on 2026-10-08 for being declared and never called — the '
     .'controller check alone is the enforcement'],
];

$cellEdits = [
    // §11's policy table credited the dead method with the course-specific stage.
    ['ExamPolicy::record(…, ExamType::CourseSpecific)',
     'ExamPolicy::record(…, ExamType::CourseSpecific)',
     'ExamPolicy::record(…, ExamType::CourseSpecific)'],

    // §22.3 role matrix: GuidanceStaff now holds six rights, not seven.
    ['Guidance counselor — School Entrance Examination scoring', '7', '6'],

    ['Guidance counselor — School Entrance Examination scoring',
     'dashboard.view, exam.record.general, exam.verify.general',
     'dashboard.view, exam.record.general, exam.view, shift.grant, students.view, user.view'],
];

$paraEdits = [
    // §11's permission line.
    ['for the precondition check',
     'UI badge: Phase 0.5 · BR9. Owning office: the Academic Department (7) that owns the program. '
     .'Permission: exam.record.courseSpecific. The stage-1 precondition is checked once, in the '
     .'controller (§11.2) — the separate exam.verify.general right listed here until 2026-10-08 was '
     .'retired with the policy method that was its only reader. Stored as examresults with '
     .'examStage = entrance, examType = courseSpecific.'],
];

$apply = in_array('--apply', $argv, true);
docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-08-pass69.docx',
    $textEdits, $cellEdits, $apply, $paraEdits);
