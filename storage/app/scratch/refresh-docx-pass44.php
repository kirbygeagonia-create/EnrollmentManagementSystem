<?php
/**
 * Pass 44 — the business-rule, vocabulary and edge-case registers (§26.4–§27.5, §28.6).
 *
 * These are the rows that state what the code does, line by line. Rulings 3, 8, 9, 12, 13, 15,
 * 16 and the 2026-10-06 decisions moved most of them, and the citation numbers moved with the
 * files. Every line range below was re-read from the current source, not from the previous
 * edition of this document.
 */
require __DIR__.'/docx-edit-engine.php';

$src = 'Documentation/EMS Complete Documentation.docx';

$cellEdits = [
    // §26.4 BR7 — the storyboard's database invariant.
    ['Nothing stops a second, simultaneously active enrollment',
     'enrollments has no unique index over',
     'The application enforces it; the database does not. Every creation point asks EnrollmentIssuer::seatHolder() ( app/Services/EnrollmentIssuer.php:36-42 ) before it writes, so a second active row for one student in one term is refused by name and dropped releases the seat. idx_enrollments_term_status is still a plain index, and no unique key over ( studentId , termId ) exists.',
     1],
    ['Nothing stops a second, simultaneously active enrollment',
     'Nothing stops a second',
     'Closed in code 2026-10-03 by ruling 3 (G-4): no act a desk can perform produces the second row. The rule stays in this register because the storyboard states a database invariant and the database does not hold it — a row written straight into the table still gives one student two active enrollments in a term, which is the half §28.5 records as settled by the Registrar’s choice of guard over index.',
     1],

    // §26.4 BR8 — the audit row each print writes.
    ['Four of the five desk prints write the row',
     'Four of the five desk prints write the row',
     'All five desk prints now write their own documentprintlog row (P-3, closed 2026-10-04), the block schedule included, and no route in routes/web.php touches the table, so nothing deletes one either.',
     1],
    ['Four of the five desk prints write the row',
     'Append-only here means',
     'Append-only still means “nobody wrote a delete”, not “the system forbids deletion” — the guarantee is the absence of a route rather than a trigger or a constraint. What the schema cannot reject is a repeated documentNo among the 21 legacy rows whose key is NULL, because MySQL treats NULLs as distinct (§25 P-14).',
     1],

    // §26.4 — the room-capacity rule, after the assignment gate.
    ['Over-capacity room assignments persist',
     'BlockingController.php:227-228 and',
     'Filing or editing a schedule that names a room too small for the block warns and proceeds ( BlockingController.php:250-257 , :311-317 ): “Schedule was still added — assignments to this room will be capped.” Assigning students into that room is a separate act and is refused: the room total is weighed before the write ( :467-478 ), next to the block total ( :461 ).',
     1],
    ['Over-capacity room assignments persist',
     'Over-capacity room assignments persist',
     'An over-capacity schedule can be filed as a plan; an over-capacity assignment cannot happen. The warning on the schedule is deliberate — a too-small room must not silently veto a legal schedule — and the consequence falls where the seats are taken.',
     1],

    // §26.4 BR33 — the free slip and the paid replacement.
    ['Two spellings of the same rule',
     'ClearancePolicy.php:43,118',
     'ClearancePolicy.php:44-62,120-142 , ClearanceController.php:224,320-372',
     1],
    ['Two spellings of the same rule',
     'The generate policy permits',
     'Two distinct acts rather than two spellings: a second free slip requires the row to be incomplete ( ClearancePolicy.php:61 ), while a lost slip is replaced through the paid route, which accepts incomplete or pending, is scoped to the Accounting and Registrar offices, and refuses outright when Reference Data holds no replacement fee ( ClearancePolicy.php:130-141 , ClearanceController.php:324-351 ).',
     1],
    ['Two spellings of the same rule',
     'Two spellings of the same rule',
     'The two differ on pending by design — one issue is free and the other is bought. Whether every replacement must be paid for, and which statuses qualify, remains the registrar’s (§28.5).',
     1],

    // §26.5 — the payment vocabulary, re-read from the live column.
    ['BR5, BR11, payment reversal',
     'paid, partial, pending',
     'paid, partial, pending, refunded',
     1],
    ['BR5, BR11, payment reversal',
     'BR5, BR11, payment reversal',
     'BR5, BR11, payment reversal, the refund that reopens a closed account',
     1],

    // §26.6 — the yearLevel conflict, closed in code.
    ['nothing updates it on promotion',
     'yearLevel does not follow',
     'yearLevel was asserted, not derived',
     1],
    ['nothing updates it on promotion',
     'enrollments.yearLevel defaults to 1',
     'enrollments.yearLevel defaulted to 1 and nothing updated it on promotion; the slip compounded the error by reading the level off a different enrollment',
     1],
    ['nothing updates it on promotion',
     'Printed documents and panels show every returning student',
     'Closed 2026-10-04 (G-2): Enrollments::derivedYearLevel() ( app/Models/Enrollments.php:86 ) answers the level from the student’s own years that closed at enrolled, Admission approval stamps it, the issue form opens on it, and the slip reads the enrollment of its clearance period’s own term (P-10). The desk may still place a student elsewhere, because a retake’s or a transferee’s year is an academic judgement the record does not hold.',
     1],
    ['nothing updates it on promotion',
     'Recorded as gap G-2',
     'Recorded as gap G-2 and closed there; §5 and §25 state what the field can and cannot support now.',
     1],

    // §27.2 E-8 — closed or extended.
    ['is never read',
     'No code distinguishes them',
     'No code used to distinguish them, and no longer does not: every clearance check asks Clearanceperiods::accepting() — a window that is open OR extended ( app/Models/Clearanceperiods.php:42 ) — and the extension is written only by the period’s own extend action ( ClearanceController.php:169-202 ).',
     1],
    ['is never read',
     'An extension the database stores',
     'An extension is now honoured by the screens and the gates. The remainder is per-student rather than per-window: studentclearances.extendedDeadline is written and read by no code, so extending moves the season and not an individual’s date.',
     1],
    ['is never read',
     'grep extendedDeadline',
     'grep extendedDeadline app/ resources/js finds the model only; ClearancePeriodStatus::Extended has exactly one writer, extendPeriod, and is read through the accepting() scope rather than a literal comparison.',
     1],

    // §27.4 T-2 — the type that met the deadlock most often.
    ['the deadlock of E-1',
     'The assessment settles to a zero balance and the enrollment then cannot',
     'The assessment settles to a zero balance, and the enrollment now reaches paid through Accounting’s settlement of an account that owes nothing rather than through a receipt it cannot have — the deadlock this row recorded (E-1) is closed. A recorded payment still cannot be ₱0, so the explicit settlement is the only path with no money in it.',
     1],
    ['the deadlock of E-1',
     'AssessmentController.php:200-207',
     'AssessmentController.php:217-226 (coverage and the duplicate full grant), PaymentPolicy::settle() , AccountingController.php:243-280 — and the settlement note printed on the fee sheet.',
     1],

    // §27.4 T-7 — the count of approval rows is nine, and the cite moved.
    ['whoever the student is',
     'No branch on student type exists anywhere',
     'No branch on student type exists anywhere in the clearance code: one slip per student per period and one pending approval row per requirement line — nine today — whoever the student is. The per-type clearance policy the brief requires is therefore institutional policy only (§28.5).',
     1],
    ['whoever the student is',
     'ClearanceController.php:133-172',
     'ClearanceController.php:229-248 (no type test), ClearancePolicy.php:44-62',
     1],

    // §27.5 — the two vocabulary rows whose values now have writers.
    ['The Registrar’s payment gate deliberately stays paid-only',
     'column enum paid',
     'column enum paid, partial, pending, refunded ; PaymentStatus::Partial',
     1],
    ['The Registrar’s payment gate deliberately stays paid-only',
     'no code — record() always writes paid',
     'AccountingStaff through payment.record : a receipt below the balance is stored as paid and then re-stored as partial when the account still owes ( AccountingController.php:200-212 ).',
     1],
    ['is now the only way to ask whether clearance season is live',
     'nobody',
     'The Registrar, through the window’s own extend action ( ClearanceController.php:169-202 ), gated on clearance.periods.manage — the module has no extend permission, and the status radio on the period form still refuses to type the value.',
     1],

    ['The Registrar’s payment gate deliberately stays paid-only',
     'no rule',
     'the outstanding balance, the scholarship application, the charge adjustment, the withdrawal recompute, the dashboard, the daily report, the refund gate and the hold that stops a part-paid account',
     1],

    // §27.5 — the flash-key row contradicted its own next column.
    ['Partly closed. HandleInertiaRequests now shares the info key',
     'HandleInertiaRequests.php:58-61 shares',
     'HandleInertiaRequests.php:86-91 shares success , warning , error and — since 2026-10-01 — info .',
     1],

    // §27.5 — office 8 is no longer vocabulary the schema carries.
    ['userId 9',
     'a row in offices and a matching clearancerequirements row',
     'retired 2026-10-04 by ruling 12: no offices row, no clearancerequirements line, no staff account in office 8 ( DevReferenceDataSeeder.php:62-104 deletes them in foreign-key order); App\Enums\OfficeId has never named the id',
     1],
    ['userId 9',
     'the seeder',
     'nothing',
     1],
    ['userId 9',
     'ClearancePolicy.php:101-115 lets',
     'nothing — approveRequirement ( ClearancePolicy.php:100-113 ) compares the signer’s officeId with the requirement’s, and no requirement carries 8 any more. The account that sat there (userId 9, “Clearance Head”) is under the Registrar now, paired RegistrarApprover with OfficeHead.',
     1],
    ['userId 9',
     '§12.1 is right that the application has no Clearance office',
     'The reader’s trap is gone rather than documented: §12.1 was always right that the application has no Clearance office, and the data now agrees with it. A school that wants one creates it in Reference Data; no code path names the retired id.',
     1],

    // §28.6 — the row that listed fresh-install gaps that have been built.
    ['None of it blocks the demo database',
     'None of it blocks the demo database',
     'None of it blocks the demo database or a fresh install today: eight religions and two provisional scholarship types are seeded, the elective band is edited on the reference-data screen, and the office that existed only as data is retired (D-1 … D-4 closed). What a real install still has to supply is the school’s own wording and its own aid categories.',
     1],
    ['None of it blocks the demo database',
     'Seeders or screens that let an admin supply',
     'Seeders and screens that let an admin supply the values — which is what closed these four. Replacing the provisional religion list, scholarship types and requirement wording remains SysAdmin and Registrar work.',
     1],
];

$apply = in_array('--apply', $argv, true);
docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-06-pass44.docx', [], $cellEdits, $apply);
