<?php
/**
 * Pass 42 — reconcile the consolidated decision list with the registers that closed it.
 *
 * §28.5 is the union of the questions each earlier register ends with, so every one of them
 * exists twice: in its own section (§25.11, §26.7 or §27.7) and in §28.5. Rulings 3, 8, 9, 12,
 * 13, 15 and the 2026-10-06 decisions closed most of them, and both copies still read as an
 * open ask. Rewriting one copy would leave the other asserting a superseded build, so each
 * edit below states how many rows carry the anchor and rewrites all of them.
 *
 * Dry-run first: the engine prints what every target currently holds and refuses the whole
 * write if any count is not what this file claims.
 */
require __DIR__.'/docx-edit-engine.php';

$src = 'Documentation/EMS Complete Documentation.docx';

// ---------------------------------------------------------------- text edits
$textEdits = [
    // The column header of the consolidated table, now that most rows answer themselves.
    ['Why the repository cannot settle it',
     'What the repository could not settle — and what settled it'],

    // §28.5's lead-in: say what the table is now.
    ['one meeting can work through the list',
     'one meeting can work through the list. Re-read 2026-10-06 against the rulings the owner has since given: a row that once asked the school now says so in the past tense, names the ruling that answered it and the code that carries the answer, and keeps only the half the software cannot decide. Nothing in this table asks for code.'],

    // §28.5's closing sentence, twice over: the five duplicated questions were all answered.
    ['Five decisions are asked in more than one register and should be answered once',
     'Five decisions were asked in more than one register and have since been answered once each'],
    ['Answering the version in this table settles all of its copies',
     'All five were answered between 2026-10-02 and 2026-10-04: office 8 retired (D-1, ruling 12), the one-seat guard (G-4, ruling 3), the derived year level (G-2), the replacement fee (P-12, ruling 8) and the zero-balance settlement (E-1, E-4, ruling 9). This pass rewrote every copy of each question in one write, because §25.11, §26.7 and §27.7 carry the same sentences as §28.5 and a copy left describing the superseded build is a defect rather than a restatement'],

    // returnToEvaluation() moved when the drop action was built beside it. Five copies.
    ['RegistrarController.php:205-229', 'RegistrarController.php:288-310', null, 5],
];

// ---------------------------------------------------------------- cell edits
// [row anchor, flattened-text prefix of the target cell, new text, how many rows carry it]
$cellEdits = [
    // 1. Which offices sign, and what each clears — §25.11 and §28.5
    ['Which offices must sign a clearance slip, and what each one clears?',
     'The software can only list the ten seeded offices',
     'Settled in mechanism, not in wording. Nine offices carry a requirement line today and each line states its own obligation (§12.1, clearancerequirements.requirementName); ruling 12 retired the tenth row that made office 8 look like a signing department. Which offices SEAIT requires to sign, and what each one clears, is still the school’s to state — the seeder’s provisional wording is a placeholder the Registrar replaces in Reference Data.',
     2],

    // 2. Is there a separate Clearance Office — answered by ruling 12
    ['Does the school recognize a separate Clearance Office?',
     '§12.1 says the function belongs to the Registrar',
     'Answered 2026-10-04 by ruling 12, and the answer is no. The seeder retires office 8 in foreign-key order — its staff, the approvals pointing at its requirement, the requirement, then the office — and the account seated there now sits under the Registrar, which is where §12.1 has always put the counter. Live ems holds nine offices. A school that later wants a separate Clearance office creates one in Reference Data; no code path names office 8.',
     2],

    // 3. What a slip must contain to be accepted at the window
    ['What must a printed slip contain to be accepted at the Registrar’s window?',
     'The field list in §15 of the revision brief',
     'The field list in §15 of the revision brief is the project’s requirement, not a documented school rule, and §25.3 now records field by field what each path prints. Two of the gaps that list assumed are closed: the requirement line carries its own text (P-8) and the slip reads the enrollment of its own term (P-10). So the answer SEAIT gives is the wording of the lines and the order of the signatures, not a column.',
     2],

    // 4. The lost-slip replacement fee — mechanism ruled 8, figure the school's
    ['What is the lost-slip replacement fee, and who sets it?',
     'the template prints a config default',
     'The mechanism is settled and built (ruling 8): the amount comes from the Reference Data fee type named Clearance Slip Replacement, which both seeders create at ₱100.00, and the slip prints that figure. Feetypes::clearanceSlipReplacementFee() returns null when the row is absent and the desk then refuses to replace rather than guessing, so no config default and no hard-coded 100 survive anywhere. What the school charges is now a number its Registrar enters, and the ₱100.00 in the seeders is the developer’s placeholder.',
     2],

    // 5. Certificate wording — half closed by P-13
    ['Should a certificate state student type, enrollment type, or both — and in what words?',
     '“New Student” is what the code prints today',
     'Half answered by the build: the certificate prints both, the student type in its own words and the enrollment kind separately (P-13, closed 2026-10-04), so the two are no longer conflated on paper. The words themselves are still the school’s — the sentences now printed are the ones the developer chose, and §25 records them as the code’s wording rather than SEAIT’s.',
     2],

    // 6. PDF or browser page — §25.1 re-measured after the enrollment form gained its route
    ['Is an official PDF required for any of these documents, or is browser printing the accepted practice?',
     'Two paths exist and only one is reachable',
     'Both paths are reachable now, and where only one exists the reason is recorded rather than assumed: the enrollment form has no React page and is issued as a PDF download by decision (print.enrollmentForm), while every other paper can reach either the desk page or the generated PDF (§25.1). Which of the two SEAIT accepts at a window, and whether the generated PDF is the file of record, is institutional — and php artisan ems:print-fidelity renders either for inspection.',
     2],

    // 7. Serial numbers — P-3, P-6, P-14 closed
    ['Must printed documents carry a serial number that matches the print log?',
     'the “Document No.” line is blank today',
     'The line is no longer blank and the log is no longer behind it: one path mints every number inside the document’s own scope and steps over the key-less legacy rows, and each desk print writes its own documentprintlog row (P-3, P-6, P-14 closed). What remains institutional is the scheme itself — whether SEAIT recognises a per-document sequence or wants numbers issued by the Registrar’s own register — because the numbering the papers now carry is the one the code invented.',
     2],

    // 8. One active enrollment per term — G-4, ruling 3
    ['Is one active enrollment per student per term school policy?',
     'Nothing enforces it (§26.4)',
     'Answered 2026-10-02 by ruling 3 and built: it is policy, and the Registrar chose a guard over a unique index. EnrollmentIssuer::seatHolder() is consulted at every creation point — the desk issue, the shift grant and Admission approval — so a second active record for one seat is refused by name, and dropped releases the seat (G-4). No school answer is outstanding. What is left is whether the schema should also reject a row written straight to the table, which today it does not.',
     2],

    // 9. Over-capacity rooms — the assignment gate now exists
    ['May a block be assigned to a room that cannot hold it?',
     'the system warns and proceeds (§26.4)',
     'Split by the build, and the split is deliberate. Filing or editing a schedule that names a room too small for the block warns and proceeds (BlockingController.php:250-257, :311-317), because the hard gate sits where the seats are actually assigned: an enrollment that would overflow the room is refused by name, alongside the block total (:467-478, :461). So students cannot be assigned into a room that cannot hold them; the question left to the school is whether a desk may still file an over-capacity schedule as a plan.',
     2],

    // 10. Which statuses may be replaced, and the fee first — BR33 rebuilt
    ['Which clearance statuses may be replaced as lost, and must the fee be charged first every time?',
     'the policy and the controller differ on pending',
     'The two spellings are now two acts. A second free slip is allowed only while the row is incomplete ( ClearancePolicy.php:44-62 , ClearanceController.php:224 ); a lost slip is replaced through the paid route, which accepts incomplete or pending, is scoped to the Accounting and Registrar offices, and refuses outright when no replacement fee is on file ( ClearancePolicy.php:120-142 , ClearanceController.php:320-372 ). The school still owns whether every replacement must be paid for, and whether a pending slip may ever be reissued free.',
     2],

    // 11. yearLevel promotion — G-2, derived. Anchored on the why-cell fragment: the question
    // text is split across runs in both copies, so it matches nothing in the raw XML.
    ['the field is written at enrollment creation only',
     'the field is written at enrollment creation only',
     'Answered and built 2026-10-04: the level is derived from the student’s own record — Enrollments::derivedYearLevel() ( app/Models/Enrollments.php:86 ) counts the years that closed at enrolled before the year being entered — so it is no longer asserted as 1 at creation, and Admission approval stamps it while the desk may still place a student elsewhere (G-2). What the derivation cannot answer is institutional: whether a year of the program the student did not complete still counts as a year for the school.',
     2],

    // 12. Zero balance to paid — E-1, E-4, ruling 9
    ['which cannot run without a positive amount',
     'The machine has one edge into paid',
     'Answered 2026-10-04 by ruling 9 and built. Accounting settles an assessed account that owes nothing through settleNoBalance() ( AccountingController.php:243-280 ), gated on the payment.settle right and a balance read through Payments::held() ( PaymentPolicy::settle() ), so the enrollment reaches paid and the Accounting box is signed exactly as a collected payment would sign it, with no receipt and no OR number invented. A recorded payment still cannot be ₱0 ( :187 , :171-231 ), so the only path to paid without money is the explicit one, and the fee sheet prints that fact with the hand and the hour (E-1, E-4).',
     2],

    // 13. Extended clearance period — half answered by ruling 15
    ['Is an “extended” clearance period a real institutional state, and does it accept slips?',
     'The value exists in clearanceperiods.periodStatus',
     'Half answered and built 2026-10-03 (ruling 15): extended is reachable only through the window’s own extend action ( ClearanceController.php:169-202 ), never the status radio, and every slip check asks Clearanceperiods::accepting() , so an extension issues slips and the Registrar’s clearance gate honours it instead of passing the student by silence. What is still not institutional is the per-student deadline: studentclearances.extendedDeadline is written by nobody and read by nothing, so an extension moves the window rather than the individual.',
     2],

    // 14. First-year signatures — behaviour unchanged, cite corrected
    ['Must a first-year collect all nine clearance signatures, or are some obligations not applicable to them?',
     'The previous revision claimed first-years are auto-approved',
     'The behaviour is unchanged and still written as observed rather than intended: generateSlip() creates one pending approval per requirement line for every student and nothing in the clearance path branches on type ( ClearanceController.php:229-248 ), so nine lines means nine signatures for a first-year as for a returning student. Until the institution states otherwise this document records the rule as policy and the behaviour as measured (§27.6).',
     2],

    // 15. Keep, merge or remove office 8 — removed by ruling 12
    ['merged into Registrar (1), or removed',
     'App',
     'Answered 2026-10-04 by ruling 12 and built, and the answer is removed. Live ems holds nine offices and nine requirement lines, no row carries officeId 8, and App\Enums\OfficeId names nine cases, so nothing is left for ClearancePolicy to honour. The account that sat at office 8 now sits under the Registrar with its username kept, since renaming a login is a separate decision from retiring an office (D-1, §27.5).',
     2],

    // 16. Installment tracking — partial has a writer and a reader (ruling 13)
    ['the value should be dropped from the column and the enum',
     'If yes, PaymentStatus::Partial needs a writer',
     'Answered 2026-10-03 by ruling 13 and built: the status has both. A receipt below the balance records partial ( AccountingController.php:206-212 ), Payments::held() is the single definition of money still in the drawer, and a part-paid account is held up rather than quietly certified — the opposite of the behaviour this row described, where every receipt wrote paid and a partially paid student simply stayed assessed. Installment tracking in the sense of a promised schedule of amounts and dates still does not exist, and whether the school wants one is its own question.',
     2],
];

$apply = in_array('--apply', $argv, true);
docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-06-pass42.docx', $textEdits, $cellEdits, $apply);
