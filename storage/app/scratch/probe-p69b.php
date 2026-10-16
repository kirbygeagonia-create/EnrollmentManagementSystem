<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
$plain = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1));
// (a) the §11 header sentence
$p = strpos($plain, 'exam.verify.general for the precondition check');
echo "HEADER ctx:\n ".mb_substr($plain, $p-330, 420)."\n\n";
foreach (['Owning office: the Academic Department (7) that owns the program',
          'for the precondition check',
          'ExamType::CourseSpecific) + verifyGeneralExam',
          'verifyGeneralExam()',
          'ExamPolicy::record(…, ExamType::CourseSpecific)'] as $n) {
    printf("%-62s raw=%d\n", mb_substr($n,0,60), substr_count($xml,$n));
}
// the row containing verifyGeneralExam()
$q = strpos($xml, 'verifyGeneralExam()');
$rs = strrpos(substr($xml,0,$q), '<w:tr'); $re = strpos($xml,'</w:tr>',$q)+7;
preg_match_all('/<w:tc>.*?<\/w:tc>/s', substr($xml,$rs,$re-$rs), $cm);
echo "\nrow @{$rs} cells=".count($cm[0])."\n";
foreach ($cm[0] as $k=>$c) { preg_match_all('/<w:t(?: [^>]*)?>([^<]*)<\/w:t>/s',$c,$t2);
  echo " [$k] paras=".substr_count($c,'<w:p>')." ".mb_substr(preg_replace('/\s+/',' ',html_entity_decode(implode('',$t2[1]),ENT_QUOTES|ENT_XML1)),0,120)."\n"; }
