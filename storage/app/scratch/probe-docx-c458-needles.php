<?php
/**
 * Count every needle pass 59 intends to use, in the raw XML.
 *
 * The engine refuses a write on an anchor it cannot resolve uniquely, so this probe is run
 * FIRST and the pass is written only from the needles it reports as count 1 (or as the count
 * the edit itself declares). Guessing an anchor against a 5.7M-character document is how a
 * correction lands in the wrong register row.
 */

$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive();
$z->open($docx);
$xml = $z->getFromName('word/document.xml');
$z->close();

$needles = [
    // §9.4 transferee row
    'A1 row anchor 9.4' => 'must pass the general examination like a freshman',
    'A2 cell prefix 9.4' => 'The policy check does not distinguish applicant types',
    'A3 condition cell' => 'Conditional — currently the same rule',
    // §10.6
    'B1 para anchor 10.6' => 'Where the flag is off the earlier reading still holds',
    // §11.1
    'C1 cite 138-157' => '(ExamController.php:138-157)',
    'C2 para anchor 11.1' => 'candidate list is not the applicant list',
    // §11.2
    'D1 para anchor prereq' => 'One rule, enforced twice',
    'D2 para anchor consequence' => 'There is no waiver path in the code',
    'D3 cite 236-245' => 'ExamController.php:236-245',
    'D4 cite 247-250' => 'ExamController.php:247-250',
    'D5 cite policy' => 'ExamPolicy.php:56-64',
    // §11.4 continuing row
    'E1 cite policy verify block' => 'AdmissionPolicy.php:96-130',
    // register rows
    'F1 C-4 anchor' => 'Are transferees who arrive with credit exempt',
    'F2 C-4 cell' => 'The examination policy still does not distinguish applicant types',
    'F3 C-5 anchor' => 'Must a board program’s Course-Specific Examination be passed',
    'F4 C-5 cell' => 'A failed result blocks approval, and an absent one blocks only where',
    'F5 C-8 anchor' => 'How long must enrollment evidence be retained?',
    'F6 C-8 cell' => 'The audit log records who did what, and no retention period is configured',
    'F7 C-8 cite' => '§20.9',
    // §20.9 body
    'G1 para anchor 20.9' => 'the document cannot state one because none is configured',
    // §24 dictionary
    'H1 courses heading' => 'courses — 7 columns',
    'H2 courseSpecific row anchor' => 'requiresCourseSpecificExam',
    // revision record
    'I1 revision anchor' => '1.1 Revision Record',
];

foreach ($needles as $label => $needle) {
    printf("%-28s %d  %s\n", $label, substr_count($xml, $needle), mb_substr($needle, 0, 58));
}
