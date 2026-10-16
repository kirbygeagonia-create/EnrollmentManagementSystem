<?php
/**
 * Raw-XML counts for the row anchors pass 64 intends to use for the examination tables.
 *
 * A cell edit needs two things: an anchor that is CONTIGUOUS in the raw XML and unique in the
 * document, and a cell prefix that matches the flattened text of exactly one cell of that row.
 * The prefix is safe either way because the engine joins the runs before comparing — which is
 * why this probe only hunts the anchors.
 */

$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive();
$z->open($docx);
$xml = $z->getFromName('word/document.xml');
$z->close();

$candidates = [
    'A1 R-2 row' => 'R-2 Admission dependency',
    'A2 R-2 evidence' => 'AdmissionPolicy.php:69-91 blocks approval',
    'B1 re-recording' => 'Corrected in place',
    'C1 effect-of-fail 9' => 'The student is finished for this term',
    'D1 hand-off 9' => 'the &quot;passer transfer&quot;',
    'D2 hand-off 9b' => 'passer transfer',
    'E1 B-1 row' => 'Course requires an entrance examination',
    'E2 B-1 evidence' => 'checked at ExamController.php:189-191',
    'F1 list candidates' => 'Admitted-track students with admissionStatus in',
    'G1 record result 10' => 'Correcting a result overwrites it instead of creating a second row',
    'H1 fail 10' => 'and stage 4 is thereafter unreachable',
    'I1 hand-off 10' => 'names and results only',
    'J1 list candidates 11' => 'the general examResult only',
    'K1 record result 11' => 'adds a second row for the same student',
    'L1 effect of result' => 'The department',
    'M1 reference data' => 'ExamController.php:189-191',
    'N1 BR9 matrix' => 'non-board programs are never examined',
    'O1 11.6 combined' => 'Entrance Examination',
    'Z1 cite 138-157' => 'ExamController.php:138-157',
    'Z2 cite 209-213' => 'ExamController.php:209-213',
    'Z3 cite 195-201' => 'ExamController.php:195-201',
    'Z4 cite 189-191' => 'ExamController.php:189-191',
    'Z5 cite 158-167' => 'ExamController.php:158-167',
    'Z6 cite 252-260' => 'ExamController.php:252-260',
    'Z7 cite 263-274' => 'ExamController.php:263-274',
    'Z8 cite 176-278' => 'ExamController.php:176-278',
    'Z9 cite 221-245' => 'ExamController.php:221-245',
    'Z10 AP 69-91' => 'AdmissionPolicy.php:69-91',
    'Z11 AP 83-90' => 'AdmissionPolicy.php:83-90',
    'Z12 AP 72' => 'AdmissionPolicy.php:72',
];

foreach ($candidates as $label => $needle) {
    printf("%-24s %2d  %s\n", $label, substr_count($xml, $needle), mb_substr($needle, 0, 52));
}
