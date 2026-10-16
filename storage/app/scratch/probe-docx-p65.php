<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
$plain = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1));
foreach (['ExamController.php:158-167','ExamController.php:189-191','ExamController.php:252-260',
          'at :263-274','AdmissionPolicy.php:69-91','AdmissionPolicy.php:83-90'] as $n) {
    printf("%-30s raw=%d\n", $n, substr_count($xml, $n));
}
$scan = 0; $k = 0;
while (($p = strpos($plain, 'AdmissionPolicy.php:69-91', $scan)) !== false) {
    $k++;
    echo "\ncontext $k: ".mb_substr($plain, max(0,$p-150), 300)."\n";
    $scan = $p + 1;
}
