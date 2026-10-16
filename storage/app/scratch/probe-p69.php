<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
$plain = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1));
foreach ([
 '95 permissions across 15 modules' => '95 permissions across 15 modules',
 '95 permissions, 17 roles' => '95 permissions, 17 roles',
 'of the 95 permissions' => 'Every one of the 95 permissions',
 'ninety-five' => 'ninety-five permissions sit in the catalogue',
 '317 role_permissions rows' => 'the 317 role_permissions rows',
 '317 rows' => '317 rows',
 'exam.verify list needle' => 'and exam.verify.general',
 'verifyGeneralExam plus' => '+ verifyGeneralExam()',
 'R1 evidence' => 'ExamPolicy::verifyGeneralExam (ExamPolicy.php:57-63) states the same precondition',
 '11.2 prereq' => 'ExamPolicy::verifyGeneralExam (ExamPolicy.php:57-63) restates that precondition',
 '11.6' => 'ExamPolicy.php:57-63, which is declared and called by nothing',
 'five permissions' => 'five permissions',
] as $l => $n) { printf("%-28s raw=%2d plain=%2d\n", $l, substr_count($xml,$n), substr_count($plain,$n)); }
// GuidanceStaff matrix row: find the row and dump its cells
$p = strpos($xml, 'dashboard.view, exam.record.general, exam.verify.general');
if ($p === false) { $p = strpos($xml, 'exam.verify.general'); }
$rs = strrpos(substr($xml,0,$p), '<w:tr'); $re = strpos($xml,'</w:tr>',$p)+7;
preg_match_all('/<w:tc>.*?<\/w:tc>/s', substr($xml,$rs,$re-$rs), $cm);
echo "\nGuidanceStaff matrix row @{$rs} cells=".count($cm[0])."\n";
foreach ($cm[0] as $k=>$c) { preg_match_all('/<w:t(?: [^>]*)?>([^<]*)<\/w:t>/s',$c,$t2);
  echo " [$k] paras=".substr_count($c,'<w:p>')." ".mb_substr(preg_replace('/\s+/',' ',html_entity_decode(implode('',$t2[1]),ENT_QUOTES|ENT_XML1)),0,150)."\n"; }
