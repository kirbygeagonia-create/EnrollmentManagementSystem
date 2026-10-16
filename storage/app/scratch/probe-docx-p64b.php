<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
foreach ([
 'R2 stmt' => 'For a program that requires an entrance examination',
 'R2 evidence tail' => 'sets admissionStatus = rejected on a fail',
 'F1 no enrollment' => 'no enrollment in the term',
 'J1 examResult only' => 'examResult only',
 'J1 general passers' => 'General passers for the program and term',
 'L1 passing sets' => 'Passing sets admissionStatus = approved',
 'L1 failing sets' => 'failing sets it to rejected',
 'M1 reqEntrance col' => 'Does this program require an entrance examination at all',
 'M1 evidence cell' => 'courses.requiresEntranceExam',
 'Z5 158-167' => 'ExamController.php:158-167',
 'Z6 252-260' => 'ExamController.php:252-260',
 'Z7 263-274' => 'ExamController.php:263-274',
 'Z8 176-278' => 'ExamController.php:176-278',
 'Z9 221-245' => 'ExamController.php:221-245',
 'Z10 AP69-91' => 'AdmissionPolicy.php:69-91',
 'Z11 AP83-90' => 'AdmissionPolicy.php:83-90',
 'Z12 AP72' => 'AdmissionPolicy.php:72',
 'N1 evidence cell' => 'ExamPolicy.php:32-55',
] as $l => $n) { printf("%-22s %2d  %s\n", $l, substr_count($xml, $n), mb_substr($n,0,46)); }
