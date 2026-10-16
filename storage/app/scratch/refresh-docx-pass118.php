<?php

/**
 * Pass 118 — §18.6 "Verification Against the Current Build" reports four things the build no longer does.
 *
 * This is the section whose entire purpose is to say how the document matches the code, and it had drifted on
 * every claim but one. Each replacement below was measured in the file or on live `ems` first, with the date
 * and commit of the change that made the sentence false:
 *
 *   P 641  "signed with the bare integer 11 (ClinicController.php:103)" — line 104 signs with
 *          OfficeId::Clinic->value; the 11 went in commit 29b5532 on 2026-10-01, and the only 11 left in the
 *          file is a comment naming the office (measured: ClinicController.php:78).
 *   P 642  "id.view and clinic.view are used as ability names that collide with permission names" — the
 *          abilities are `clinic.viewAtDesk` (AuthServiceProvider.php:180) and `id.viewAtDesk` (:198), named
 *          that way deliberately in the same 2026-10-01 batch; the dotted names survive only as permissions
 *          the policies read, and AbilityNameCollisionTest pins the rule.
 *   P 643  "The cancelled status in IdRequestStatus is legacy" — the enum declares only Pending and Validated
 *          (IdRequestStatus.php:7-8) and live `ems` reads `enum('pending','validated')` with 1 validated and
 *          2 pending rows. `2026_10_03_1000000005_narrow_...` (commit cefbcdf, 2026-10-04) retired released
 *          and cancelled, and it REFUSES to narrow while any row still holds them — it does not rewrite a
 *          status behind the record's back, which is why the sentence cannot say "the rows were moved".
 *   P 644  "Nothing links clinicrecords.bloodType-equivalent data to idrequests.bloodType; two sources for
 *          one medical fact" — `clinicrecords` has no blood-type column at all (fillable list: heightCm,
 *          weightKg, bloodPressure, philhealthNumber, philhealthRegistered, assessmentNotes, findings,
 *          clinicStaffId, assessmentDate, status), while `idrequests.bloodType` is typed in by the student and
 *          checked only against a fixed list of strings (IDController.php:112, :124). One source, and it is
 *          self-declared — a different weakness from the one recorded.
 *   ROW 2247  "PrintService.php:23 and :37-38 (ChromiumLocator::locate, then setChromePath)" — locate is :30
 *          and setChromePath is :49; :37-38 is a blank line and the storage_path() call.
 *
 * Styling is preserved wherever it can be: P 642, P 644 and the register row are edited inside their own runs,
 * so the monospace code spans around them stay monospace. P 641 and P 643 need whole-paragraph rewrites (the
 * words that are wrong sit in runs whose text — "11", "cancelled", a bare "." — repeats hundreds of times in
 * the document), and the engine's paragraph kind collapses those two to a single run carrying the paragraph's
 * own first formatting. Their first run was already plain prose, so what is lost is only the monospace on the
 * two inline citations inside them — disclosed here rather than discovered later.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';

$textEdits = [
    // P 642 — one prose run, unique; the leading `id.view` / `clinic.view` monospace runs stay untouched.
    [
        ' are used as ability names that collide with permission names, so the office scoping in those policies can be bypassed (§22.4, X-1).',
        ' are the names of PERMISSIONS, and the desk abilities that used to share them were renamed clinic.viewAtDesk and id.viewAtDesk on 2026-10-01, so Spatie’s Gate::before can no longer grant an ability by permission and skip the office scoping the policy writes (§22.4, X-1 closed; AuthServiceProvider.php:180 and :198, pinned by AbilityNameCollisionTest).',
    ],
    // P 644 — four unique runs, rewritten in place so each code span keeps its font.
    ['Nothing links ', 'One medical fact, one source — and it is not a clinical one: '],
    ['clinicrecords.bloodType', 'clinicrecords carries height, weight, blood pressure, PhilHealth and findings, but no blood-type column at all ('],
    ['-equivalent data to ', 'clinicrecords.php:17), while '],
    [
        '; the latter is re-declared by the student. Two sources for one medical fact is a known modelling weakness (§30).',
        '; typed in by the student at the desk and checked only against a fixed list of strings (IDController.php:112, :124). Nothing in the system can compare it to a measurement, so the weakness is a self-declared medical fact with one source, not two sources for one (§30).',
    ],
    // ROW 2247 — the citation, measured.
    [
        'PrintService.php:23 and :37-38 (ChromiumLocator::locate, then setChromePath).',
        'PrintService.php:30 and :49 (ChromiumLocator::locate, then setChromePath).',
    ],
];

$paraEdits = [
    // P 641
    [
        'The Clinic box is signed with the bare integer',
        'The Clinic box is signed with the clinic office’s own enum — OfficeId::Clinic->value (ClinicController.php:104). The bare integer 11 this section used to report was removed on 2026-10-01 by commit 29b5532; the only 11 left in the file is a comment naming the office.',
    ],
    // P 643
    [
        'is legacy: nothing sets it, and there is no cancel action on',
        'idrequests.status is an enum of exactly (pending, validated) on live ems, and IdRequestStatus declares only Pending and Validated (IdRequestStatus.php:7-8). The released and cancelled values this section documented as legacy were retired by 2026_10_03_1000000005_narrow_idrequests_status_to_the_reachable_states.php (commit cefbcdf, 2026-10-04), which refuses the narrowing while any row still holds them rather than rewriting a status behind the record’s back. Live ems reads one validated and two pending rows; there is no cancel action on the desk, and now no vocabulary for one.',
    ],
];

require __DIR__.'/docx-edit-engine.php';
docxApply($src, $root.'/storage/app/scratch/docx-backup-2026-10-15-pass118.docx',
    $textEdits, [], in_array('--apply', $argv, true), $paraEdits);
