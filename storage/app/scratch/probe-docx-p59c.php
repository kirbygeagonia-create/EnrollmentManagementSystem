<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
foreach ([
  '11.6 anchor' => 'the general-pass prerequisite (R-1) at',
  'C-5 register prefix ok' => 'A failed result blocks approval, and an absent one blocks only where',
  'C-8 register prefix ok' => 'The audit log records who did what, and no retention period is configured',
  'C-4 register prefix ok' => 'The examination policy still does not distinguish applicant types',
  '9.4 cell2 prefix ok' => 'The policy check does not distinguish applicant types',
] as $l => $n) { printf("%-22s %d\n", $l, substr_count($xml, $n)); }
// R-1 row: how many paragraphs does the evidence cell hold?
$rs = 200455; $re = strpos($xml, '</w:tr>', $rs) + 7;
preg_match_all('/<w:tc>.*?<\/w:tc>/s', substr($xml, $rs, $re - $rs), $rm);
foreach ($rm[0] as $k => $cc) { echo "R1 cell $k paras=".substr_count($cc, '<w:p>')."\n"; }
$pos = strpos($xml, 'must pass the general examination like a freshman');
$rs2 = strrpos(substr($xml, 0, $pos), '<w:tr'); $re2 = strpos($xml, '</w:tr>', $pos) + 7;
preg_match_all('/<w:tc>.*?<\/w:tc>/s', substr($xml, $rs2, $re2 - $rs2), $rm2);
foreach ($rm2[0] as $k => $cc) { echo "9.4 cell $k paras=".substr_count($cc, '<w:p>')."\n"; }
foreach ([['Are transferees who arrive with credit exempt'],['Must a board program’s Course-Specific Examination be passed'],['How long must enrollment evidence be retained?']] as $a) {
  $p = strpos($xml, $a[0]); $r = strrpos(substr($xml, 0, $p), '<w:tr'); $e = strpos($xml, '</w:tr>', $p) + 7;
  preg_match_all('/<w:tc>.*?<\/w:tc>/s', substr($xml, $r, $e - $r), $rm3);
  echo "register row ".mb_substr($a[0],0,24)." cells=".count($rm3[0])." paras per cell=";
  foreach ($rm3[0] as $cc) { echo substr_count($cc, '<w:p>').' '; }
  echo "\n";
}
