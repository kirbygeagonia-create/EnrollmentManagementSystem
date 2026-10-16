<?php
/**
 * Pass 63 — the departmental paper is now satisfiable on its own, and four places that
 * described it as Stage 2 of a two-stage sequence had to be re-read.
 *
 * This pass exists because of a defect found while writing pass 59's UAT rows: the five programs
 * C-5 flags are not five programs that run the general examination. BSA, BSCE and BSEE set
 * requiresCourseSpecificExam with requiresEntranceExam off, so Guidance could not record them a
 * general result, the controller refused the department its own record, the roster was built from
 * a passer transfer that can never exist, and every applicant to those three programs met a
 * blocker nobody in the system was allowed to clear. Same shape as the C-4 trap found an hour
 * earlier: a requirement the approval blockers raise and the recording path refuses to let
 * anyone produce. Both are now closed, and the citations re-measured after the controller moved
 * (students() branch 156-222 and 225/237-248; recordCourseSpecific 309-312 and 314-341).
 */
require __DIR__.'/docx-edit-engine.php';

$src = 'Documentation/EMS Complete Documentation.docx';

$textEdits = [
    // §11.6 — the only remaining copy of that controller range outside the regions rewritten
    // below; anchored positionally because §11.2 cites the same range and is a whole-paragraph
    // rewrite, so a global needle replacement would collide with it.
    ['ExamController.php:294-320', 'ExamController.php:309-341',
     'the general-pass prerequisite (R-1) at'],
];

$cellEdits = [
    // ── §6.1 R-1, the rule that used not to bend.
    ['Exception, ruled by C-4 on 2026-10-07',
     'A student may take the Course-Specific',
     'A student may take the Course-Specific Entrance Examination if and only if the School General '
     .'Entrance Examination administered by the Guidance Office has been passed. A failed or missing '
     .'general result means the course-specific examination is not administered. Two rulings narrowed '
     .'that on 2026-10-07, both in the same direction — a requirement the approval blockers raise has '
     .'to be one some desk is allowed to produce. C-4: a program may waive the dependency for the '
     .'transferees it admits on credit (courses.entranceExamExemptsTransferee, default off). C-5: a '
     .'board program may require its own examination while administering no general paper at all, and '
     .'for such a program the departmental result stands alone — there is no Stage 1 to wait for.'],

    ['Exception, ruled by C-4 on 2026-10-07', 'Enforced, with one program-set exception',
     'Enforced, with two program-set exceptions'],

    ['Exception, ruled by C-4 on 2026-10-07', 'ExamController.php:294-320 refuses the record',
     'ExamController.php:309-341 refuses the record twice over: "This course administers no entrance '
     .'examination." where the program runs neither paper, and "General entrance exam must be passed '
     .'first." where it runs the general one and the applicant has no pass to show — unless the '
     .'program grants the C-4 waiver and the admission’s applicantType is transferee. '
     .'ExamPolicy::verifyGeneralExam (ExamPolicy.php:57-63) states the same precondition against a '
     .'model and is called by nothing on this install, so the controller check is the enforcement; the '
     .'permission only it asks for, exam.verify.general, is then in the same position as the retired '
     .'sign rights §28 already keeps listed.'],

    // ── §28.2 C-4: the cites moved with the controller.
    ['Are transferees who arrive with credit exempt',
     'Answered 2026-10-07: per-program, default off',
     'Answered 2026-10-07: per-program, default off. courses.entranceExamExemptsTransferee — added by '
     .'2026_10_07_1000000001 — waives the General Entrance Examination for applicants whose '
     .'applicantType is transferee, and for no other type. Courses::waivesGeneralEntranceExam() is the '
     .'single reading, shared by the approval blockers (AdmissionPolicy.php:108-141), the department’s '
     .'own exam roster (ExamController.php:156-222) and the Stage 1 prerequisite '
     .'(ExamController.php:314-341): a waiver honoured by fewer than all three would strand the '
     .'applicant instead of exempting them. It never waives the departmental examination, and no '
     .'program ships with it set.'],

    // ── §28.2 C-5: the set is named AND satisfiable, which the first answer did not establish.
    ['Must a board program’s Course-Specific Examination be passed',
     'Answered 2026-10-07: the board programs set it',
     'Answered 2026-10-07: the board programs set it. BSCrim, BSCE, BSEE, BSA and BSSW carry '
     .'requiresCourseSpecificExam, named in DevReferenceDataSeeder on the owner’s instruction to '
     .'prioritise the courses that have board examinations. The set is provisional and expected to be '
     .'replaced by the owning departments from Reference Data → Courses, which is why the flag is '
     .'desk-side data and not a code constant. Three of the five — BSA, BSCE, BSEE — administer no '
     .'general paper, so their departmental result is their entrance examination and stands alone; '
     .'that had to be built, not assumed, and until it was the flag made those three programs’ '
     .'applicants unapprovable by construction (ExamController.php:225 , 309-341). A failed '
     .'departmental result blocks approval; an absent one blocks only where the program asks '
     .'(AdmissionPolicy.php:132-140). Before the ruling the gate could not fire at all: 0 of 16 '
     .'programs set the flag.'],
];

$paraEdits = [
    // §11.1 — the roster has three sources now, not one.
    ['candidate list is not the applicant list',
     'For board-oriented programs, the department runs a second, program-specific examination after '
     .'Guidance has run the school general one. Where the program administers that general paper, the '
     .'department’s candidate list is not the applicant list but the transfer of its passers for the '
     .'same program and term (ExamController.php:156-222), which is the passer transfer the code '
     .'labels BR9. Two classes join it. A transferee whose program sets entranceExamExemptsTransferee '
     .'has no general row to transfer, so under ruling C-4 (2026-10-07) the applicant is listed and '
     .'named "General Entrance waived (transferee)". And where a program runs no general paper at all '
     .'— the C-5 shape, BSA, BSCE and BSEE — nothing can be transferred by construction, so the '
     .'roster is that program’s own unenrolled applicants (ExamController.php:225 , entranceApplicants '
     .'at :237-248). Both additions were found the same way: a blocker the approval policy raises that '
     .'no desk was permitted to produce. The department records pass or fail per candidate.'],

    // §11.2 — the prerequisite applies where a Stage 1 exists, and the second refusal is the
    // program that runs no entrance examination of either kind.
    ['One rule, enforced once',
     'One rule, with two narrowings, enforced once: where a program administers the School Entrance '
     .'paper, an entrance/general row for the same student, course, and term must exist and read pass '
     .'before the department may score its own examination, and ExamController.php:314-341 refuses the '
     .'record with "General entrance exam must be passed first." A program that runs no general paper '
     .'is not held to it (C-5, 2026-10-07), and a program that waives it for its transferees is not '
     .'held to it for them either (C-4). ExamController.php:309-312 refuses only a program that '
     .'administers neither paper. ExamPolicy::verifyGeneralExam (ExamPolicy.php:57-63) restates that '
     .'precondition against a model and is called by nothing on this install, so it enforces nothing — '
     .'and the permission only it asks for, exam.verify.general, is unenforced with it.'],

    ['with the one exception ruling C-4 granted',
     'Consequence: a student who failed, or was never recorded in, the general examination cannot be '
     .'given a course-specific examination — except in the two cases the owner ruled on 2026-10-07. '
     .'Where the program sets entranceExamExemptsTransferee and the admission’s applicantType is '
     .'transferee, Courses::waivesGeneralEntranceExam() answers true and the prerequisite is skipped. '
     .'Where the program administers no general paper and requires its own, there is no Stage 1 to '
     .'wait for and the departmental result is that program’s entrance examination. Both narrowings '
     .'exist for one reason: a waiver honoured only by the approval blockers strands exactly the '
     .'applicant it was written for, still blocked by a paper nobody was allowed to record. Every '
     .'other applicant — a first-year, or a transferee in a program that sets nothing — still needs '
     .'the general pass first.'],
];

$apply = in_array('--apply', $argv, true);
docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-07-pass63.docx',
    $textEdits, $cellEdits, $apply, $paraEdits);
