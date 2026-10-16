<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
foreach (['it too must be pass','result has been recorded at all','For a program with requiresEntranceExam',
          'a entrance/general result exists','and is pass'] as $n) { printf("%-42s %d\n", mb_substr($n,0,40), substr_count($xml,$n)); }
$p = strpos($xml, 'AdmissionPolicy.php:69-91', 500000);
$s = strrpos(substr($xml,0,$p), '<w:tc>'); $e = strpos($xml, '</w:tc>', $p) + 7;
preg_match_all('/<w:t(?: [^>]*)?>([^<]*)<\/w:t>/s', substr($xml,$s,$e-$s), $tm);
echo "cell runs: ".count($tm[1])." → ".var_export(implode('', $tm[1]), true)."\n";
$rs = strrpos(substr($xml,0,$p), '<w:tr');
preg_match_all('/<w:tc>.*?<\/w:tc>/s', substr($xml,$rs,strpos($xml,'</w:tr>',$p)-$rs), $cm);
echo "row cells=".count($cm[0])."\n";
foreach ($cm[0] as $k=>$c){ preg_match_all('/<w:t(?: [^>]*)?>([^<]*)<\/w:t>/s',$c,$t2); echo " [$k] runs=".count($t2[1])." ".mb_substr(implode('',$t2[1]),0,90)."\n"; }
