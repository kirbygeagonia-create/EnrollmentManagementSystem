<?php
/**
 * Pass 48 — the clearance gate, re-read where it actually lives.
 *
 * Rulings 4 and 5 moved this rule out of RegistrarController::show() into one method,
 * EnrollmentReadiness::clearanceVerdict() ( app/Support/EnrollmentReadiness.php:53-98 ), and changed
 * its answer: a missing window is now a refusal naming the reason, and Department Evaluation asks the
 * same verdict before it costs the load. Eight rows still said the opposite — "deliberately permissive",
 * "EvaluationController never queries the clearance tables", "the gate is written twice" — and §28.3 G-3
 * had already closed them. Measured spans: exemption :55-57, no-window refusal :59-62, Evaluation
 * confirmation clause :86; the Registrar reads it at RegistrarController.php:155 and RegistrarPolicy.php:63-69;
 * Evaluation's own look-up is approvedPassSlip :921-943 and confirmClearance :945-993.
 */
require __DIR__.'/docx-edit-engine.php';

$src = 'Documentation/EMS Complete Documentation.docx';

$textEdits = [
    // The type exemption and the seven gates were never in show().
    ['RegistrarController.php:112-114', 'app/Support/EnrollmentReadiness.php:55-57', null, 2],
    ['RegistrarController.php:213-224', 'RegistrarController.php:288-310', null, 2],
    ['RegistrarController.php:160-163', 'RegistrarController.php:244-249'],
    ['RegistrarController.php:146', 'RegistrarController.php:151-166'],
    ['RegistrarController.php:140-152', 'RegistrarController.php:145-157'],
    ['RegistrarController.php:212-215', 'RegistrarController.php:220-235'],
    ['RegistrarController.php:145-159', 'RegistrarController.php:145-156', null, 3],
    ['the 5-gate checklist', 'the seven-gate checklist'],
];

$cellEdits = [
    // §7.1 R-3 — the rule is no longer tested at one desk.
    ['gate sits late', 'Partially enforced',
     'Enforced at two desks, through one method', 1],
    ['gate sits late', 'The Clearance desk states the intent',
     'The Clearance desk states the intent ( Clearance/Index.jsx:499 : it “permits the student to proceed to Phase 2 (Curriculum Evaluation)”), and the rule is decided in exactly one place — EnrollmentReadiness::clearanceVerdict() ( app/Support/EnrollmentReadiness.php:53-98 ). Two desks read it. Department Evaluation looks for an approved, received slip inside the window now accepting and makes the officer confirm it ( approvedPassSlip() , EvaluationController.php:921-943 , the act at :945-993 under evaluation.clearance.confirm ); the Registrar checklist and its policy ask the same method ( RegistrarController.php:155 , RegistrarPolicy.php:63-69 ), which refuses rather than passes when no window is accepting at all. So a returning student without a cleared slip is stopped at Evaluation, before the load is costed and paid — the lateness this row recorded is closed (§28.3, G-3).', 1],

    // §7.3 B-11 — the absent period is a refusal now.
    ['the student is not blocked by an absent period', 'The clearance gate evaluates to',
     'There is no accepting window, so the gate refuses and names the reason the desk can act on: open or extend a window in Clearance → Periods. A continuing or shifter student cannot be approved while the season has not opened.', 1],
    ['the student is not blocked by an absent period', 'Enforced, and deliberately permissive',
     'Enforced, and deliberately the opposite of permissive since ruling 4 — Clearanceperiods::accepting() decides the window and app/Support/EnrollmentReadiness.php:59-62 writes the refusal.', 1],

    // §13 — the same case in the Evaluation chapter’s comparison table.
    ['returns pass when no', 'Enforced, deliberately permissive',
     'Enforced, and a refusal since ruling 4', 1],
    ['returns pass when no', 'RegistrarController.php:116-119 returns pass',
     'No accepting window is a refusal, not a pass. Clearanceperiods::accepting() finds nothing and clearanceVerdict() returns the sentence the desk shows ( app/Support/EnrollmentReadiness.php:59-62 , read at RegistrarController.php:155 and RegistrarPolicy.php:63-69 ). The permissive reading this row recorded is what ruling 4 removed: a gate that passes when clearance season has not opened certifies students who never cleared.', 1],

    // §27.2 E-7 — the edge case, whose three cells all described the old pass.
    ['The clearance gate passes for every returning student', 'The clearance gate passes for every returning student',
     'The gate refuses, naming the window the Registrar is reading', 1],
    ['The clearance gate passes for every returning student', 'Nothing — the certification proceeds',
     'The checklist prints the reason on the record page and the policy is refused with it, so the desk is told to open or extend a window instead of approving blind', 1],
    ['The clearance gate passes for every returning student', 'RegistrarController.php:116-119',
     'app/Support/EnrollmentReadiness.php:59-62 , RegistrarPolicy.php:63-69 , RegistrarController.php:155', 1],

    // §26.6 — the conflict this pair of copies used to be.
    ['A closed period disables the clearance gate', 'the gate queries only for a period',
     'the gate asks Clearanceperiods::accepting() , so open OR extended answers it, and a window that is not accepting at all is a refusal rather than a silence', 1],
    ['A closed period disables the clearance gate', 'When no period is open',
     'Closed 2026-10-03 by ruling 4. Before it, an unopened or closed season passed every returning student — including one who never cleared. Now the same verdict refuses and says which of the two is missing, and the extend action is the only way to widen the window.', 1],
    ['A closed period disables the clearance gate', 'Stated as deliberate permissiveness',
     'Recorded as gap G-3 and closed there; §12.6 and §16 state the gate as one method read by three callers.', 1],
    ['implement the same rule independently', 'The clearance gate is written twice',
     'The clearance gate was written twice', 1],
    ['implement the same rule independently', 'RegistrarPolicy.php:66-83',
     'One method decides it now: EnrollmentReadiness::clearanceVerdict() ( app/Support/EnrollmentReadiness.php:53-98 ), read by the Registrar checklist ( RegistrarController.php:155 ), by the policy ( RegistrarPolicy.php:63-69 ) and by Department Evaluation ( EvaluationController.php:921-993 )', 1],
    ['implement the same rule independently', 'The two copies must be edited together',
     'Nothing to keep in step: the two independent copies are gone, so a change to the rule is a change to one method and every desk moves with it. What a reader still has to know is which three callers ask it, which is why each is cited wherever the rule is discussed.', 1],
    ['implement the same rule independently', 'Both citations are printed everywhere',
     '§12.6, §13, §16, §26.3 and §28.3 (G-3) all point at the one method rather than at two.', 1],

    // §13 — the test behind the slip’s "Completed" state.
    ['RegistrarController.php:124-128', 'Yes — the test the Registrar performs',
     'Yes — one test read by two desks: EnrollmentReadiness::clearanceVerdict() ( app/Support/EnrollmentReadiness.php:53-98 ) asks for approved and received inside the accepting window, and Department Evaluation must have confirmed that slip (the verdict’s last clause, :86 )', 1],

    // §13 — the claim that Evaluation never looked at clearance.
    ['The Evaluation screen states the gate lives elsewhere', 'Policy not enforced at that point',
     'Was not enforced there; it is now', 1],
    ['The Evaluation screen states the gate lives elsewhere', 'The Evaluation screen states the gate lives elsewhere',
     'The Evaluation screen used to say exactly that — “the clearance gate is enforced at Phase 5, not here” ( Evaluation/Show.jsx:252 ) — and it no longer does, because the desk asks the clearance itself: approvedPassSlip() looks for an approved, received slip in the accepting window ( EvaluationController.php:921-943 ) and confirmClearance() records the officer’s confirmation as the verdict’s last clause ( :945-993 , permission evaluation.clearance.confirm ). A returning student with an unfinished clearance is now stopped at Evaluation and again at the Registrar, not after the fees have been assessed and paid.', 1],

    // §25/§26.3 — the print-audit row that still exempted the block schedule.
    ['Print audit', 'the block schedule print writes none',
     'Closed 2026-10-04 (P-3): every desk print writes its own row, the block schedule included', 1],
    ['Print audit', 'RegistrarController.php:292,314,344',
     'RegistrarController.php:372 , :405 , :428 , ClearanceController.php:394 , BlockingController.php:559 , and the one writer PrintService::recordIssue() at PrintService.php:105', 1],

    // §5 — enrollmentType written at intake and corrected at approval.
    ['Later corrected by the Registrar under BR31', 'Hard-coded new',
     'Written as new at intake ( AdmissionController.php:395 ), which is right for the applicant the desk is sitting and wrong for nobody else', 1],
    ['Later corrected by the Registrar under BR31', 'Later corrected by the Registrar under BR31',
     'Later corrected by the Registrar under BR31 ( RegistrarController.php:244-249 , written at :266 ) — new for firstYear/transferee, old for continuing/shifter, derived from the student type rather than entered.', 1],

    // §22.7 — the same derivation, cited in the rule register (the question text is split
    // across runs, so the row is anchored on its own "n/a" cell).
    ['n/a — a computed field', 'RegistrarController.php:161-164',
     'RegistrarController.php:244-249 , written at :266', 1],
];

$apply = in_array('--apply', $argv, true);
docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-06-pass48.docx', $textEdits, $cellEdits, $apply);
