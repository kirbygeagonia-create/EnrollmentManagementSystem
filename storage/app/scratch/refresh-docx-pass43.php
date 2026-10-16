<?php
/**
 * Pass 43 — the chapters BEFORE §26 that still describe a superseded build.
 *
 * §28's registers close findings in code; §25.11/§26.7/§27.7/§28.5 now say so (pass 42).
 * What was left behind is prose that states the old behaviour as a present fact: an office 8
 * that is no longer seeded, a requirement table with no text column, an elective screen that
 * does not exist, 87 permissions, three print rights. Every figure here was measured against
 * the code or live ems first (probe-docx-21-3-facts.php, probe-seeder-rbac-counts.php).
 */
require __DIR__.'/docx-edit-engine.php';

$src = 'Documentation/EMS Complete Documentation.docx';

$textEdits = [
    // §23.2 — the live reconciliation quoted the old permission count.
    ['(87 permissions, 17 roles)', '(95 permissions, 17 roles)'],

    // §28.3 D-5 — the same figure inside the gap register's closing sentence.
    ['The live roles table holds 17 rows and the permission set 87.',
     'The live roles table holds 17 rows and the permission set 95, which the catalogue now matches exactly.'],

    // §5.2 / §22.9 / §28.3 X-4 — OfficeHead keeps four print rights since the form gained one.
    ['the three print rights', 'the four print rights', null, 3],
];

$cellEdits = [
    // §23.6.1 — the seat rule: the database still holds no key, but the application refuses.
    ['Nothing. No controller checks it either',
     'Nothing. No controller checks it either',
     'No key, and the application refuses anyway: EnrollmentIssuer::seatHolder() is consulted at every creation point, so a second active record for one seat is rejected by name (G-4, ruling 3). What the schema still cannot reject is a row written straight to the table.',
     1],

    // §23.6.2 — the same invariant, stated a second time in the second list.
    ['and no controller guard',
     'Nothing. There is no unique key on',
     'No unique key on ( studentId , termId ), and no guard in the schema — but the refusal is in code: every creation point asks EnrollmentIssuer::seatHolder() before it writes (G-4). A row inserted directly into the table is still the one way to give a student two active enrollments in a term.',
     1],

    // §23.6.3 — year level, standing and type: G-2 and C-2 moved.
    ['type and standing are whatever intake recorded',
     'Nothing. yearLevel is written as 1 at creation',
     'yearLevel is derived from the student’s own record at creation ( Enrollments::derivedYearLevel() , app/Models/Enrollments.php:86 ) rather than asserted as 1, and a shifter or a transferee arrives Irregular because the type itself decides (C-2). Every other standing is what the desks recorded from the grades on file.',
     1],

    // §5.2 — the returning student now has a desk that enrolls them (G-1 closed).
    ['No role is required to enroll a returning student',
     'Same. No role is required to enroll a returning student',
     'Same, and the path exists: Department Evaluation issues the returning student’s enrollment through evaluation.store (G-1, closed 2026-10-03 by ruling 2), and no permission is layered on top of the evaluation right — so the gap this row recorded was functional and is now closed rather than a permissions question.',
     1],
];

$paraEdits = [
    // §12.1 — office 8: the paragraph wrote a retirement as a to-do.
    ['Data artifact to correct',
     'Artifact retired 2026-10-04 by ruling 12. The seeder used to create an office row with id 8 named “Clearance” and one clearance requirement for every seeded office including it, and that row is the origin of the “Clearance Office” error in earlier documentation: the slip printed a line labelled “Clearance” that looked like a separate department signing itself off. There was never an OfficeId::Clearance case in app/Enums/OfficeId.php, and no policy, gate, seeder role or permission ever referenced office 8. DevReferenceDataSeeder now retires the row in foreign-key order — its staff, the approvals pointing at its requirement, the requirement, then the office — and live ems reads nine offices with nine named requirement lines, none of them office 8. The account that sat there keeps its username and sits under the Registrar, where RbacSeeder step 3 pairs it RegistrarApprover with OfficeHead (§21.5, D-1).'],

    // §12 stage 5 — the permission list carried a name the seeder never defined.
    ['clearance.requirement.approve',
     'UI badge: Phase 1 · Campus Clearance. Function owner: the Registrar Office, which issues, receives, and records the clearance. Permission set, as the seeder defines the clearance module’s six: clearance.view, clearance.periods.manage, clearance.slip.generate, clearance.receipt.record, clearance.approve, clearance.slip.replace. Tables: clearanceperiods, clearancerequirements, studentclearances, clearanceapprovals.'],

    // §21.3 warning 1 — reference-data ownership, re-counted.
    ['held by SysAdmin alone',
     'Reference data is SysAdmin territory. Fourteen refdata.*.manage permissions exist; thirteen are held by SysAdmin alone (§22.5) and the fourteenth, refdata.gradeScale.manage, is shared with RegistrarApprover because the Registrar owns the scale its certificates print. OfficeHead and Staff hold refdata.view, and the Admin oversight role holds only permissions ending in .view. A desk that spots a wrong fee, a missing religion, or an absent scholarship type cannot fix it — it must raise a request to SysAdmin. This is a design decision worth confirming with the registrar: it keeps one owner for the catalog but makes every catalog correction a hand-off.'],

    // §21.3 warning 2 — the office list existing twice, after the delete guard.
    ['deleting an office row',
     'The office list exists twice — as data and as code. offices is a table with a CRUD screen, but the workflow and every office-scope policy compare against the fixed integers in App\Enums\OfficeId. Consequences: creating an office whose id OfficeId does not name changes nothing (no enum case, no workflow step, no desk); renaming office 1 from “Registrar” changes the printed clearance line but not the authority check; and deleting a row that staffusers, clearancerequirements or workflow steps still point at would orphan them, so the reference-data screen refuses that delete instead of letting the application break in silence (X-3, OfficeAuthorityTest). The document still does not describe offices as the source of truth for workflow authority — OfficeId is, and the two must be kept in step by hand.'],

    // §21.3 warning 3 — clearancerequirements can describe a requirement now.
    ['cannot describe a requirement',
     'clearancerequirements could not describe a requirement, and no longer cannot. The table used to hold clearanceRequirementId and officeId alone, so a “requirement” was an office, the printed slip could only list office names, and adding an obligation meant adding an office — which is exactly how the seeded office 8 came to print a meaningless “Clearance” line on every slip (D-1). Migration 2026_09_30_1000000002 added requirementName, backfilled only where the row was still empty so a school’s own wording survives an upgrade, and the seeder fills the nine lines from ProvisionalClearanceRequirements::NAMES. What remains is wording, not schema: the provisional text is the developer’s, and the Registrar replaces it in Reference Data (D-6).'],

    // §21.3 — the elective warning, after the screen and the validator were built.
    ['Elective configuration is unreachable',
     'Elective configuration was unreachable and is now entered from the screen. curriculumsubjects carries is_elective, elective_group, elective_min_choices and elective_max_choices (2026_08_09_1000000005_extend_curriculumsubjects_table.php), the reference-data screen edits all four, ReferenceDataController::normalizeElective clears the band when a subject is not an elective, and EvaluationController refuses a proposal outside min–max (:470-476), while the picker prints each group’s band with a live chosen count instead of letting the evaluator learn the range from the refusal. What is still true on this install is a data fact and not a code one: no curriculumsubjects row is flagged elective, so the bounds never fire here and the rule stays undemonstrated until the Registrar offers an elective (D-4).'],

    // §22.3 — the seeder's own figures, re-counted from the file.
    ['87 permission definitions',
     'Counts in this section come from the seeder text and were re-read against the live database on 2026-10-06, where the two now agree exactly: 95 permission definitions in 15 modules, 17 Role::updateOrCreate calls (updateOrCreate, not firstOrCreate, so a description an installed database already holds is corrected rather than kept), and 15 explicit syncPermissions([...]) lists — SysAdmin’s and Admin’s are computed from the catalogue, hence the two-role offset and the 317 role_permissions rows §23.2 reconciles.'],

    // §21.1 — the offices correction, re-read from live ems.
    ['There are 10 rows here',
     'Correction re-read 2026-10-06 from live ems: there are nine rows here and App\\Enums\\OfficeId names nine cases (Registrar = 1, Accounting = 2, Scholarship = 3, Guidance = 4, Blocking = 5, Admission = 6, Academic = 7, Clinic = 11, IdOffice = 22) — the first edition of this document in which the two counts agree. Earlier issues recorded a tenth row, seeded office 8 “Clearance”, that no enum case named; ruling 12 retired it in data (§12.1, D-1), so the warning this paragraph existed to give is now a closed item and the table and the enum describe the same nine offices.'],

    // §23.6 closing paragraph — none of the four rows it named is unenforced today.
    ['Four of the rows above are enforced nowhere',
     'Four of the rows above were enforced nowhere, and all four are enforced in code today: the one-enrollment-per-term rule by EnrollmentIssuer::seatHolder() at every creation point (G-4), the year level by Enrollments::derivedYearLevel() (G-2), the clearance prerequisite by EnrollmentReadiness::clearanceVerdict(), read by the Registrar checklist, RegistrarPolicy and the Evaluation desk (G-3), and the text behind a clearance line by clearancerequirements.requirementName (D-6). What still keeps §23 and §26 from merging into one list is the reason those rows were written: each rule now lives in the application rather than in the schema, so a write that never passes the application is still the way to break it.'],

    // §25 — the fee paragraph that said the paper and the money could disagree.
    ['The one fee stated on paper — “Lost slip replacement fee: ₱100.00”',
     'The one fee stated on paper no longer states a figure of its own. clearance-slip.blade.php resolves the amount through Feetypes::clearanceSlipReplacementFee() at render time and prints it only when the Reference Data row exists, and the replacement flow charges that same row ( ClearanceController.php:324 ), so paper and money cannot disagree — and the desk refuses to replace a slip at all while no such fee type is on file, rather than falling back to a constant. What this paragraph used to record, a template default of ₱100.00 beside a lookup with a ?? 100 fallback, is gone (P-12, ruling 8). The figure the school charges is entered in Reference Data → Fee Types; the ₱100.00 in both seeders is the developer’s placeholder (§25.11).'],

    // §25.6 — the slip’s requirements block, after requirementName and the office-8 retirement.
    ['printed as an office name',
     'Requirements block. One row per clearancerequirements row, printing the line’s own requirementName beside the office that signs it, so the paper states what the student owes rather than which room signed (P-8 closed). The PDF adds Status, Approved By and Date columns; the desk page adds a signature rule and Remarks but no approval date. Nine rows print today — the tenth was office 8 “Clearance”, retired with the office (P-9, ruling 12) — which is why §12.1’s statement that no Clearance department exists is now visible on the paper as well as in the code.'],
];

$apply = in_array('--apply', $argv, true);
docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-06-pass43.docx', $textEdits, $cellEdits, $apply, $paraEdits);
