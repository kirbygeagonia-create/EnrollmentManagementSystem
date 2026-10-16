<?php
// What shape are the rows of the enrolledsubjects table?

$z = new ZipArchive();
$z->open(dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx');
$xml = $z->getFromName('word/document.xml');
$z->close();

$next = strpos($xml, '24.7 Module 5');
preg_match_all('/<w:tbl>.*?<\/w:tbl>/s', substr($xml, 0, $next), $tbs);
$t = $tbs[0][count($tbs[0]) - 1];
echo 'tables before §24.7 = '.count($tbs)."  last len=".strlen($t)."\n";
echo 'has enrolledSubjectId: '.(str_contains($t, 'enrolledSubjectId') ? 'yes' : 'no')."\n";

preg_match_all('/<w:tr>.*?<\/w:tr>/s', $t, $plain);
preg_match_all('/<w:tr[ >].*?<\/w:tr>/s', $t, $any);
echo 'plain rows='.count($plain[0])." any rows=".count($any[0])."\n";

foreach (array_slice($any[0], 0, 3) as $j => $r) {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $r, $cp);
    preg_match_all('/<w:tc[ >].*?<\/w:tc>/s', $r, $ca);
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $r, $tx);
    echo ' row '.$j.' cells plain='.count($cp[0]).' any='.count($ca[0]).' text=['.mb_substr(implode(' | ', $tx[1]), 0, 70)."]\n";
}
echo "\nfirst 240 chars:\n".substr($t, 0, 240)."\n";
