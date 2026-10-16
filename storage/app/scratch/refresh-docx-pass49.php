<?php
/**
 * Pass 49 — §25.1's print-reachability table, re-pointed at the methods that answer paper today.
 *
 * The table was written when the three Registrar print pages sat in the file's first half and the
 * class-card/subject-load/fee-sheet services were numbered lower. Every print act now sits at the
 * end of its controller (the certificate page renders at RegistrarController.php:372, class cards
 * :405, subject load :428, the form and slip downloads beside them), the PrintService methods moved
 * with the numbering helper, and app/Jobs was deleted rather than wired — so the paragraph that
 * proved reachability "by listing every caller, the two jobs among them" is now describing files
 * that do not exist. Line numbers measured from the current files, one by one.
 */
require __DIR__.'/docx-edit-engine.php';

$src = 'Documentation/EMS Complete Documentation.docx';

$textEdits = [
    // §13 — the transition the desk performs, not the blocking reason beside it.
    ['RegistrarController.php:181', 'RegistrarController.php:134-175'],

    // §26.2 — the two multi-table writes that habit produced.
    ['RegistrarController.php:174', 'RegistrarController.php:256'],
    ['ClearanceController.php:267', 'ClearanceController.php:228', 'The atomicity habit is real but unnumbered'],

    // §26.3 BR33 — the replacement payment’s null enrollment is written by the replacement act.
    ['ClearanceController.php:271', 'ClearanceController.php:357'],

    // §25.1 row 5 — the download and the service. (The reachability paragraph itself is
    // rewritten whole below, so its two cite strings are not edited here as well.)
    ['downloadBlockSchedule() serves the PDF (:585)', 'downloadBlockSchedule() serves the PDF ( :588 )'],
    ['PrintService::printBlockSchedule() (PrintService.php:219)', 'PrintService::printBlockSchedule() ( PrintService.php:290 )'],
];

$cellEdits = [
    // ── Enrollment certificate
    ['the download route answers the same screen with the PDF',
     'Registrar/PrintCertificate page',
     'Registrar/PrintCertificate page ( RegistrarController.php:372 ) — the download route answers the same screen with the PDF ( :437 )', 1],
    ['the download route answers the same screen with the PDF',
     'PrintService::printEnrollmentCertificate()',
     'PrintService::printEnrollmentCertificate() ( PrintService.php:186 ) — called directly by both the Registrar page route and its PDF sibling, synchronously', 1],
    ['the download route answers the same screen with the PDF',
     'yes — recordIssue() on both paths',
     'yes — one issue row per print act, minted inside the service ( PrintService.php:193 through recordIssue() at :105 )', 1],

    // ── Class card
    ['one page, every subject',
     'Registrar/PrintClassCards — one page',
     'Registrar/PrintClassCards — one page, every subject ( RegistrarController.php:405 ); downloadClassCard() serves one card ( :464 )', 1],
    ['one page, every subject',
     'PrintService::printClassCards()',
     'PrintService::printClassCards() ( PrintService.php:208 ) — returns one PDF per subject to the Registrar class-card route, each card rendered by printClassCard() at :244', 1],
    ['one page, every subject',
     'yes — one issue row for the print',
     'yes — one issue row for the print act, written inside the service ( PrintService.php:258 )', 1],

    // ── Subject load
    ['downloadSubjectLoad() serves the PDF',
     'Registrar/PrintSubjectLoad',
     'Registrar/PrintSubjectLoad ( RegistrarController.php:428 ); downloadSubjectLoad() serves the PDF ( :490 )', 1],
    ['downloadSubjectLoad() serves the PDF',
     'PrintService::printSubjectLoad()',
     'PrintService::printSubjectLoad() ( PrintService.php:266 ) — renders prints.subject-load, its own template, not the certificate’s', 1],
    ['downloadSubjectLoad() serves the PDF',
     'yes — recordIssue()',
     'yes — recordIssue() ( PrintService.php:282 )', 1],

    // ── Clearance slip
    ['downloadSlip() serves the PDF',
     'Clearance/PrintSlip',
     'Clearance/PrintSlip ( ClearanceController.php:394 ); downloadSlip() serves the PDF ( :404 )', 1],
    ['downloadSlip() serves the PDF',
     'PrintService::printClearanceSlip()',
     'PrintService::printClearanceSlip() ( PrintService.php:58 ) — called by both the screen and the download', 1],
    ['downloadSlip() serves the PDF',
     'yes — recordIssue() (ClearanceController.php:312)',
     'yes — recordIssue() inside the service ( PrintService.php:66 ), though the row carries a NULL enrollment whenever the student has no enrollment for the period’s term (§25.10)', 1],

    // ── Block and schedule
    ['attributed to the first seated student',
     'yes since 2026-10-01',
     'yes since 2026-10-01 — recordIssue() writes the row the desk route used to skip ( PrintService.php:303 ), attributed to the first seated student, or NULL when the block seats no one', 1],

    // ── §26.3 BR33's policy citation
    ['A lost slip is replaced only after a payment is recorded',
     'ClearancePolicy.php:112-135',
     'ClearancePolicy.php:120-142 , ClearanceController.php:320-372', 1],
];

$paraEdits = [
    // §25.1 — the reachability proof, rewritten now that app/Jobs no longer exists.
    ['Reachability was established by listing every',
     'Reachability was established by listing every Inertia::render whose component contains “Print” — the Registrar’s three print pages ( RegistrarController.php:372 , :405 , :428 ), the clearance slip ( ClearanceController.php:394 ) and the block schedule ( BlockingController.php:580 ) — and every caller of PrintService, which is only those desk routes and their PDF siblings. The two queued jobs this paragraph used to list are gone: app/Jobs was deleted rather than wired, since nothing ever dispatched either job, and PDF rendering has never been queued work (DEPLOYMENT.md §5, §6). php artisan ems:print-fidelity renders the Blade templates itself (PrintFidelitySamples.php:84-116) for visual comparison and writes no audit rows.'],
];

$apply = in_array('--apply', $argv, true);
docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-06-pass49.docx', $textEdits, $cellEdits, $apply, $paraEdits);
