<?php
/**
 * Pass 64 — the examination tables re-read against the code, after the citation sweep found
 * that five of them still describe the pre-G-8 build and every ExamController range in the
 * document predates today's two edits.
 *
 * Two different mistakes were in these cells, and only one of them is a number:
 *   CLAIM — R-2's evidence, §9's and §10's "Effect of a fail", §10's B-1 evidence and §11.3's
 *     "Effect of the result" all state that recording a result sets admissions.admissionStatus
 *     (approved on a pass, rejected on a fail). That is the bypass finding G-8 closed: both
 *     recording methods now write an examresults row and stop, because an examination module that
 *     approves an applicant leaves the application reading approved with no enrollment row, and
 *     AdmissionPolicy::approve accepts only a pending one — so the desk could no longer fix it.
 *     ApprovalReadinessTest and ExamControllerTest pin the refusal; the document had not followed.
 *   NUMBER — 138-157, 158-167, 189-191, 195-201, 209-213, 221-245, 247-250, 252-260, 263-274 and
 *     176-278 were measured before students() grew the waived-transferee query and
 *     recordCourseSpecific grew the C-5 branch. Re-measured against the current file:
 *     recordGeneral's refusal 263-266, its firstOrNew 270-281, its boundary 283-290;
 *     students() 139-231 with the passer transfer at 156-222, the board-only roster call at 225
 *     and entranceApplicants() at 237-248; recordCourseSpecific's neither-paper refusal 309-312,
 *     the prerequisite 314-341, its create 343-351 and its boundary 353-360. AdmissionPolicy's
 *     blocker list is 67-142, its examination region 108-141.
 */
require __DIR__.'/docx-edit-engine.php';

$src = 'Documentation/EMS Complete Documentation.docx';

$textEdits = [
    // §11.6 — "any description of a single combined Entrance Examination step is wrong and
    // contradicts …": the two examination paths are separated across this whole span.
    ['ExamController.php:176-278', 'ExamController.php:139-360'],
];

$cellEdits = [
    // ── §6.1 R-2. The rule is real; the second sentence credited the examination module with
    // a decision it no longer makes.
    ['For a program that requires an entrance examination', 'For a program that requires an entrance examination',
     'For a program that requires an entrance examination, an applicant with no general result, or '
     .'a failing general result, cannot be admitted. A recorded failure blocks the application exactly '
     .'as a missing one does and does not decide it: the examination module writes an examresults row '
     .'and stops (finding G-8), and ending an application is the Admission office’s own act.'],

    ['For a program that requires an entrance examination', 'AdmissionPolicy.php:69-91 blocks approval',
     'AdmissionPolicy.php:67-142 blocks approval unless an entrance/general pass exists '
     .'(AdmissionPolicy.php:111-123) and, where the program requires it, a course-specific pass too '
     .'(AdmissionPolicy.php:127-140). Nothing in the examination module sets an admission status any '
     .'more: ExamController.php:283-290 ends the general recording at the examresults row, and that '
     .'comment says so on purpose.'],

    // ── §9 action table.
    ['Corrected in place', 'Corrected in place — firstOrNew',
     'Corrected in place — firstOrNew on the natural key (ExamController.php:270-281); no duplicate '
     .'row.'],

    ['The student is finished for this term', 'Blocks admission approval and sets admissionStatus',
     'Blocks admission approval: the blockers name the failed result (AdmissionPolicy.php:119-121), '
     .'and the department cannot examine the applicant either, because the stage-4 prerequisite '
     .'refuses a non-pass general row (ExamController.php:314-341). It does not set the status — the '
     .'application stays pending until the Admission office decides it (G-8). The student is finished '
     .'for this term in practice, not by a write the examination module makes.'],

    ['the &quot;passer transfer&quot;', 'A passer list the Guidance Office transfers',
     'A passer list the Guidance Office transfers to the department — the "passer transfer", '
     .'ExamController.php:156-222.'],

    ['Course requires an entrance examination', 'Enforced — Courses.requiresEntranceExam, checked',
     'Enforced — Courses.requiresEntranceExam, checked when the result is recorded '
     .'(ExamController.php:263-266) and again when the desk tries to approve (AdmissionPolicy.php:111).'],

    // ── §10 table: the general stage's own actions.
    ['no enrollment in the term', 'Admitted-track students with admissionStatus',
     'Admitted-track students with admissionStatus in pending|approved and no enrollment in the term '
     .'(ExamController.php:237-248).'],

    ['Correcting a result overwrites it instead of creating a second row',
     'Examresults::firstOrNew on the natural key',
     'Examresults::firstOrNew on the natural key (studentId, courseId, termId, entrance, general) at '
     .'ExamController.php:270-281. Correcting a result overwrites it instead of creating a second row.'],

    ['and stage 4 is thereafter unreachable', 'Every admissions row for that student/course/term',
     'Nothing outside examresults is written (ExamController.php:283-290): the admissions row stays '
     .'pending and the Admission office decides — which is the G-8 closure, and an earlier draft of '
     .'this table said every admissions row for that student/course/term was set to rejected. What '
     .'the fail does still do is R-1: stage 4 becomes unreachable, because the prerequisite refuses a '
     .'non-pass general row (ExamController.php:314-341).'],

    ['names and results only', 'The passer list is transferred to the department',
     'The passer list is transferred to the department as the candidate source for stage 4 '
     .'(ExamController.php:156-222) — names and results only; passers at that point are not treated '
     .'as applicants.'],

    // ── §11.3: the departmental stage's own actions.
    ['General passers for the program and term', 'General passers for the program and term',
     'General passers for the program and term, plus the transferees whose program waives that paper '
     .'(named generalExamWaived), and — for a program that runs no general paper at all — its own '
     .'unenrolled applicants (ExamController.php:156-222 , :225 , :237-248). The response carries '
     .'studentId, schoolIdNumber, the name parts, the general examResult and the waiver flag.'],

    ['adds a second row for the same student', '`Examresults::create`, not `firstOrNew`',
     '`Examresults::create`, not `firstOrNew` (ExamController.php:343-351). Unlike stage 2, correcting '
     .'a course-specific result adds a second row for the same student, program, term, and examination '
     .'instead of updating the first. The two examinations therefore have different correction '
     .'semantics; any count of course-specific results must be de-duplicated by the natural key.'],

    ['ExamController.php:263-274', 'Passing sets admissionStatus = approved',
     'Neither: the record ends at the examresults row and redirects with "The Admission office decides '
     .'the application." (ExamController.php:353-360). This used to set admissionStatus directly — '
     .'approved on a pass, rejected on a fail — which is the bypass §11.5 describes the closure of: an '
     .'application left reading approved had no enrollment row, and because AdmissionPolicy::approve '
     .'accepts only a pending application the desk could no longer correct it. Finding G-8.'],

    // ── §21 reference data: which decision reads which table.
    ['Does this program require an entrance examination at all', 'ExamController.php:189-191,',
     'ExamController.php:263-266 (general) and :309-312 (departmental, where the program runs the '
     .'general paper at all); AdmissionPolicy.php:108-141'],

    // ── §27 rule matrix.
    ['non-board programs are never examined', 'ExamPolicy.php:32-55',
     'ExamPolicy.php:32-55 , ExamController.php:156-222 and :309-341'],
];

$apply = in_array('--apply', $argv, true);
docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-07-pass64.docx',
    $textEdits, $cellEdits, $apply);
