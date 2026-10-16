<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
foreach ([
  'R1 stmt anchor' => 'A student may take the Course-Specific Entrance Examination if and only if',
  'R1 evid prefix' => 'ExamController.php:243-245 refuses',
  '11.6 para anchor' => 'Enforced: the general-pass prerequisite (R-1)',
  '11.1 para anchor' => 'candidate list is not the applicant list',
  '9.4 cond prefix' => 'Conditional — currently the same rule',
] as $l => $n) { printf("%-18s %d  %s\n", $l, substr_count($xml, $n), mb_substr($n, 0, 50)); }

$plain = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1));
$p = strpos($plain, '11.4 Student-Type Handling');
echo "\n§11.4 BODY:\n".mb_substr($plain, $p, 900)."\n";
