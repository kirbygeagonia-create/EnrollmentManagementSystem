<?php
/**
 * Pass 51 — Blocking and Reference Data citations, the last Accounting guard cite, and the row
 * that still said the info flash never reaches a screen.
 *
 * Measured from the current files: store() :158-177, finalize() :367-378 (its already-finalized
 * return at :371-373, which is the repeat-proofing BR and the info-flash example in one),
 * assignStudents() :443-524 (block total :461, room total :467-478, the enrolled-and-box guard at
 * :486), printBlockSchedule() :559-587 with its issue row written by PrintService::recordIssue()
 * at PrintService.php:303; ReferenceDataController index() :46-92, the academicyears dropdown at
 * :474, the one prerequisite rule at :337; the reference-data routes are routes/web.php:178-262.
 */
require __DIR__.'/docx-edit-engine.php';

$src = 'Documentation/EMS Complete Documentation.docx';

$textEdits = [
    // The last copy of the accounting guard, now that its longer forms are repaired.
    ['AccountingController.php:95', 'AccountingController.php:179-182'],

    // Blocking.
    ['BlockingController.php:146-162', 'BlockingController.php:158-177'],
    ['BlockingController.php:415-495', 'BlockingController.php:443-524 , room check at :467-478'],
    ['BlockingController.php:433', 'BlockingController.php:461', null, 2],
    ['BlockingController.php:227-228', 'BlockingController.php:250-257'],
    ['BlockingController.php:336-344', 'BlockingController.php:371-373', null, 2],
    ['BlockingController.php:559-575', 'BlockingController.php:559-587', null, 2],
    ['BlockingController.php:571-575', 'PrintService.php:303'],

    // Reference Data.
    ['ReferenceDataController.php:375', 'ReferenceDataController.php:474'],
    ['ReferenceDataController.php:47-58', 'ReferenceDataController.php:46-92'],
    ['ReferenceDataController.php:272,285', 'ReferenceDataController.php:337'],
    ['routes/web.php:141-220', 'routes/web.php:178-262', null, 2],
];

$cellEdits = [
    // §27.4 E-12 — the info key is shared now; what remains is per-screen.
    ['which is never shared with the front end',
     'A toast — unless the message was sent under the info key',
     'A toast — and the message under the info key reaches the screen today, because HandleInertiaRequests shares info beside success , warning and error since 2026-10-01 ( U-3 ). What still loses a sentence is a page that never reads the key, which is per-screen work and stays listed as such (§28.3).', 1],
    ['which is never shared with the front end',
     'AssessmentController.php:104-107',
     'AssessmentController.php:104-107 , BlockingController.php:371-373 , HandleInertiaRequests.php:86-91 ( success , warning , error and info )', 1],
];

$apply = in_array('--apply', $argv, true);
docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-06-pass51.docx', $textEdits, $cellEdits, $apply);
