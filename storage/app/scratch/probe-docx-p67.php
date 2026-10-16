<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
$plain = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1));
foreach (['AdmissionPolicy.php:67-142','AdmissionPolicy.php:108-141','AdmissionPolicy.php:111-123',
          'AdmissionPolicy.php:127-140','AdmissionPolicy.php:132-140','AdmissionPolicy.php:119-121',
          'AdmissionPolicy.php:111','AdmissionPolicy.php:67-75',
          'No General Entrance Exam result on record for this applicant'] as $n) {
    printf("%-52s raw=%d plain=%d\n", $n, substr_count($xml,$n), substr_count($plain,$n));
}
$scan=0;
while (($p = strpos($plain,'AdmissionPolicy.php:1', $scan)) !== false) {
    echo "  ctx: ".mb_substr(preg_replace('/\s+/',' ',substr($plain,$p-70,200)),0,190)."\n";
    $scan=$p+1;
}
