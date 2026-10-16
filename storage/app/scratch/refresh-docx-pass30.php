<?php

/**
 * Pass 30 — the enrollment form becomes a printable document in the authority, and the RBAC
 * figures that §22 quotes are brought to the reseed this edition ran.
 */

require __DIR__.'/docx-edit-engine.php';

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$textEdits = [
    // §25's two-path statement: the form is reached through the second one, on purpose.
    ['Only the first path is reachable from the application today, so the audit reports both and marks which one a student actually receives.',
        'Both paths are reachable now, but not for the same papers: the four Registrar documents and the clearance slip and block schedule reach the staff member as a React page, while the enrollment form reaches the desk only as a PDF from the blade — deliberately, because the blade is that paper and a screen copy could only drift from it. The audit reports both and marks which one a student actually receives.'],

    // §25.1's Enrollment Form row, cell by cell.
    ['— none —', 'GET /registrar/{enrollment}/print/enrollment-form/pdf (web.php:159)'],
    ['— no page exists —',
        'a PDF download. There is no React page for this paper and none is planned: the blade is the form'],
    ['PrintService::printEnrollmentForm() (PrintService.php:239) — still no caller: no route and no desk action prints the enrollment form itself',
        'PrintService::printEnrollmentForm() (PrintService.php:311) — called by RegistrarController::downloadEnrollmentForm() behind the registrar.printEnrollmentForm gate, which asks print.enrollmentForm and then requires the record to be enrolled'],
    ['only inside that unreachable method — the form has no issue row because nothing issues it',
        'yes — one issue row per download, documentType enrollmentForm, sequenced in the enrollment’s own series and separate from the certificate’s'],
    ['No — template exists, nothing renders it',
        'yes — reached from the Registrar record page; ems:print-fidelity renders the same template'],

    // §28 P-2: the reachability half closes, the content half stays open.
    ['and no React print page exists for it',
        '; the desk reaches it as a PDF only, which is a decision rather than a gap, because the blade is the paper'],
    ['Staff cannot print it at all today.',
        'A desk can print it since 2026-10-06 (print.enrollmentForm, RegistrarApprover and OfficeHead, mirroring print.certificate); what the paper still cannot do is say which program or term the enrollment belongs to.'],

    // §22's quoted totals, after the 2026-10-06 reseed.
    ['87 permissions across 14 modules', '95 permissions across 15 modules'],
    ['Every one of the 87 permissions', 'Every one of the 95 permissions'],
    ['Unique pair. 298 rows', 'Unique pair. 317 rows'],
    ['94 permissions across 15 modules', '95 permissions across 15 modules'],
    ['Rows in the demo database: 94', 'Rows in the demo database: 95'],
    ['Rows in the demo database: 314', 'Rows in the demo database: 317'],

    // §1.1's revision cell grows the closing clause.
    ['2026-10-04–05 (this issue)', '2026-10-04–06 (this issue)'],
    ['and the two unreachable signWorkflow() policy methods that held them are deleted.',
        'and the two unreachable signWorkflow() policy methods that held them are deleted. The enrollment form gained the desk action it had never had (print.enrollmentForm, held by exactly the roles that hold print.certificate), §22’s catalogue and role matrix were re-read from the live tables — which found eleven role counts that had drifted — and the demo’s dean account was repaired: it had been seeded with the officeHead enum, so the role pairing had quietly replaced its Dean grant and left the shift endorsement undemonstrable on every install.'],
];

$cellEdits = [
    // §22.5's mapping row for the default arm: office 8 is gone, and the arm no longer catches
    // a seeded account.
    ['therefore has no desk role', '8 (seeded',
        'any office id with no desk pairing — id 8 is no longer seeded, ruling 12 retired it in data'],
    ['therefore has no desk role', 'OfficeHead alone',
        'OfficeHead alone. Since ruling 12 no seeded account reaches this arm: office8_head sits in office 1 and is therefore paired RegistrarApprover + OfficeHead, so it approves enrollments and is not a negative test of ruling 7. A bare OfficeHead exists only when someone creates one — which is why the acceptance script’s §2.8 makes two, one in office 11 and one in office 22, and proves the signature refusals from inside the owning office.'],
];

docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-06-pass30.docx', $textEdits, $cellEdits, $apply);
