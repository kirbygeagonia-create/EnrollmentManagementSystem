<?php
/**
 * Pass 59 — the three examination rulings the owner answered on 2026-10-07 (C-4, C-5, C-8),
 * written everywhere the book talks about them, not only in the register.
 *
 * Why this pass is bigger than three cells: the register rows sit at the back of a 5.7M-character
 * document, and §6.1, §9.4, §10.6, §11.1, §11.2, §11.6 and §20.9 each describe the same rule in
 * the present tense. Answering C-4 in §28.2 alone would have left §6.1's R-1 still calling the
 * general-pass dependency a rule that does not bend, §11.2 still stating "there is no waiver path
 * in the code", and §11.1's candidate-list citation still pointing at lines that no longer hold
 * the roster query after the controller changed.
 *
 * Two facts measured rather than assumed, both found while writing this pass:
 *   - ExamPolicy::verifyGeneralExam is declared and called by nothing, so the book's "enforced
 *     twice" was wrong before the ruling as well as after it; the only permission it asks for,
 *     exam.verify.general, therefore gates nothing either.
 *   - the line ranges the book cites moved when the controller and the policy changed, so every
 *     cite in the sentences rewritten here was re-measured against the files (see
 *     probe-docx-c458-needles.php for the anchor counts this pass was written from).
 */
require __DIR__.'/docx-edit-engine.php';

$src = 'Documentation/EMS Complete Documentation.docx';

$textEdits = [
    // §11.6 — both cites live in separate runs, so each is replaced where it stands rather than
    // by rewriting a paragraph whose "Enforced:" label is bold. Anchored positionally: the same
    // controller range is cited by §11.2 as well, and an unanchored needle would land there.
    ['ExamController.php:236-245', 'ExamController.php:294-320',
     'the general-pass prerequisite (R-1) at'],
    ['ExamPolicy.php:56-64', 'ExamPolicy.php:57-63, which is declared and called by nothing — the '
     .'controller check is the enforcement, and ruling C-4 on 2026-10-07 gave it its one '
     .'program-set exception (§11.2)',
     'the general-pass prerequisite (R-1) at'],
];

$cellEdits = [
    // ── §6.1 R-1, "The Three Rules That Do Not Bend". C-4 is the first exception this rule has
    // ever had, so the row states it rather than pretending the sentence still holds unbroken.
    ['A student may take the Course-Specific Entrance Examination if and only if',
     'A student may take the Course-Specific Entrance Examination',
     'A student may take the Course-Specific Entrance Examination if and only if the School General '
     .'Entrance Examination administered by the Guidance Office has been passed. A failed or missing '
     .'general result means the course-specific examination is not administered. Exception, ruled by '
     .'C-4 on 2026-10-07: a program may waive that dependency for the transferees it admits on credit '
     .'(courses.entranceExamExemptsTransferee, default off) — and having waived it, it must still '
     .'administer its own examination, which is why the same flag is read by the scoring check and '
     .'not only by the approval blockers.'],

    ['A student may take the Course-Specific Entrance Examination if and only if',
     'Enforced',
     'Enforced, with one program-set exception'],

    ['A student may take the Course-Specific Entrance Examination if and only if',
     'ExamController.php:243-245 refuses',
     'ExamController.php:294-320 refuses the record with "General entrance exam must be passed '
     .'first." unless the program grants the waiver and the admission’s applicantType is transferee. '
     .'ExamPolicy::verifyGeneralExam (ExamPolicy.php:57-63) states the same precondition against a '
     .'model and is called by nothing on this install, so the controller check is the enforcement; '
     .'the permission only it asks for, exam.verify.general, is then in the same position as the '
     .'retired sign rights §28 already keeps listed.'],

    // ── §9.4 applicant-type row: the transferee line, which stated the pre-ruling rule outright.
    ['must pass the general examination like a freshman',
     'Conditional — currently the same rule',
     'Conditional — the program decides'],

    ['must pass the general examination like a freshman',
     'The policy check does not distinguish applicant types',
     'C-4 was ruled on 2026-10-07: the exemption is a per-program setting rather than a policy the '
     .'code assumes. courses.entranceExamExemptsTransferee (default false) lets a program waive the '
     .'school-wide General Entrance Examination for the transferees it admits on credit, and for '
     .'nobody else — a first-year still sits it, and a granted shifter is exempt from both entrance '
     .'papers on the shift rule, which is a different decision about a different applicant. The '
     .'waiver never reaches the department’s own Course-Specific Examination: a waived transferee '
     .'still sits it (§11.2). With the flag off, which is how all sixteen programs are seeded on this '
     .'install, the rule reads exactly as this row stated it before the ruling.'],

    // ── §28.2 register: the three rows the owner closed.
    ['Are transferees who arrive with credit exempt',
     'The examination policy still does not distinguish applicant types',
     'Answered 2026-10-07: per-program, default off. courses.entranceExamExemptsTransferee — added '
     .'by 2026_10_07_1000000001 — waives the General Entrance Examination for applicants whose '
     .'applicantType is transferee, and for no other type. Courses::waivesGeneralEntranceExam() is '
     .'the single reading, shared by the approval blockers (AdmissionPolicy.php:108-141), the '
     .'department’s own exam roster (ExamController.php:153-217) and the Stage 1 prerequisite '
     .'(ExamController.php:294-320): a waiver honoured by fewer than all three would strand the '
     .'applicant instead of exempting them. It never waives the departmental examination, and no '
     .'program ships with it set.'],

    ['Must a board program’s Course-Specific Examination be passed',
     'A failed result blocks approval, and an absent one blocks only where',
     'Answered 2026-10-07: the board programs set it. BSCrim, BSCE, BSEE, BSA and BSSW carry '
     .'requiresCourseSpecificExam, named in DevReferenceDataSeeder on the owner’s instruction to '
     .'prioritise the courses that have board examinations. The set is provisional and expected to '
     .'be replaced by the owning departments from Reference Data → Courses, which is why the flag is '
     .'desk-side data and not a code constant. A failed departmental result blocks approval; an '
     .'absent one blocks only where the program asks (AdmissionPolicy.php:132-140). Before the ruling '
     .'the gate could not fire at all: 0 of 16 programs set the flag.'],

    ['How long must enrollment evidence be retained?',
     'The audit log records who did what, and no retention period is configured',
     'Answered 2026-10-07 as indefinite, measured: the system purges nothing. No retention period is '
     .'configured anywhere in config/, no scheduled command deletes an audit row, a receipt or an '
     .'enrollment, and live ems held 2,868 auditlogs rows on that date, every one of them still '
     .'reachable. The sentence is a measurement rather than a promise — the day SEAIT’s records '
     .'officer names a period, a purge command has to be built to honour it, and it does not exist. '
     .'Retention therefore stays policy the school states, not behaviour the code guesses.'],
];

$paraEdits = [
    // §10.6 — the flag exists, and the answer C-5 gave names programs rather than changing a rule.
    ['Where the flag is off the earlier reading still holds',
     'An absent course-specific result blocks approval only where the program asks for that '
     .'examination. The switch is requiresCourseSpecificExam on the course, added 2026-10-01 by the '
     .'additive migration 2026_10_01_1000000001 and default false, so no program is obliged to hold a '
     .'paper it never administered. Where the flag is off the earlier reading still holds: a failed '
     .'result blocks approval, an absent one does not (AdmissionPolicy.php:108-141). C-5 was answered '
     .'on 2026-10-07, and the answer is a set of programs rather than a change of rule — BSCrim, BSCE, '
     .'BSEE, BSA and BSSW carry the flag on this install, seeded by name and replaceable desk-side '
     .'from Reference Data → Courses by whichever department owns each one. Until that answer the '
     .'mechanism was built and tested while the gate stayed invisible: 0 of 16 programs set the flag, '
     .'so no live applicant could ever have been blocked by it.'],

    // §11.1 — the roster query moved, and the waiver adds a second class of candidate.
    ['candidate list is not the applicant list',
     'For board-oriented programs, the department runs a second, program-specific examination after '
     .'Guidance has run the school general one. The department’s candidate list is not the applicant '
     .'list: it is built from general-examination passers for that program and term '
     .'(ExamController.php:153-217), which is the passer transfer the code labels BR9. Added to it by '
     .'ruling C-4 on 2026-10-07 is the transferee whose program sets entranceExamExemptsTransferee — '
     .'an applicant with no general row to transfer, listed on the same screen named "General '
     .'Entrance waived (transferee)" so the department can tell the two classes apart instead of '
     .'scoring a candidate it cannot explain. The department records pass or fail per candidate.'],

    // §11.2 — "enforced twice" was never true, and "no waiver path" stopped being true on 2026-10-07.
    ['One rule, enforced twice',
     'One rule, enforced once: an entrance/general row for the same student, course, and term must '
     .'exist and read pass before the department may score its own examination. '
     .'ExamController.php:294-320 refuses the record with "General entrance exam must be passed '
     .'first." ExamPolicy::verifyGeneralExam (ExamPolicy.php:57-63) restates that precondition '
     .'against a model and is called by nothing on this install, so it enforces nothing — and the '
     .'permission only it asks for, exam.verify.general, is unenforced with it. '
     .'Courses.requiresEntranceExam must also be true (ExamController.php:295-297).'],

    ['There is no waiver path in the code',
     'Consequence: a student who failed, or was never recorded in, the general examination cannot be '
     .'given a course-specific examination — with the one exception ruling C-4 granted on 2026-10-07. '
     .'Where the program sets entranceExamExemptsTransferee and the admission’s applicantType is '
     .'transferee, Courses::waivesGeneralEntranceExam() answers true and the prerequisite is skipped, '
     .'because a waiver honoured only by the approval blockers would strand exactly the applicant it '
     .'was written for: still blocked by the departmental paper, and unable to sit it. Every other '
     .'applicant, a first-year or a transferee in a program that sets nothing, still needs the general '
     .'pass first.'],

    // §20.9 — the open question becomes a measured state.
    ['the document cannot state one because none is configured',
     'For human confirmation (C-8): retention. An academic records office normally has a legal '
     .'retention period for enrollment evidence. C-8 was answered on 2026-10-07 as indefinite, '
     .'measured: this build purges nothing — no period is configured in config/, no scheduled command '
     .'deletes an audit row, a receipt or an enrollment — and the table shows it, at 2,868 auditlogs '
     .'rows on live ems the same day. What the system does today is accumulate; what it does not have '
     .'is any way to forget. SEAIT’s records officer still has to state the period and the legal '
     .'basis for it, because the day a purge command is written it starts destroying evidence on a '
     .'number the code chose rather than one the school ruled.'],
];

$apply = in_array('--apply', $argv, true);
docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-07-pass59.docx',
    $textEdits, $cellEdits, $apply, $paraEdits);
