<?php
$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive(); $z->open($docx); $xml = $z->getFromName('word/document.xml'); $z->close();
$plain = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1));
$files = [
 'ExamController.php' => 'app/Http/Controllers/Exam/ExamController.php',
 'ExamPolicy.php' => 'app/Policies/ExamPolicy.php',
 'AdmissionPolicy.php' => 'app/Policies/AdmissionPolicy.php',
];
$owner = function (string $path, int $line): string {
    $src = file($path); $name = '(none)'; $last = 0;
    foreach ($src as $i => $l) { if (preg_match('/function\s+(\w+)/', $l, $m)) { $name = $m[1]; $last = $i + 1; } if ($i+1 >= $line) break; }
    return $name.' @'.$last.'  →  '.trim($src[$line-1] ?? '');
};
$total = [];
foreach ($files as $n => $p) { $total[$n] = count(file($p)); }
$seen = [];
foreach ($files as $needle => $path) {
  preg_match_all('/'.preg_quote($needle,'/').':(\d+)(?:-(\d+))?/', $plain, $mm, PREG_SET_ORDER);
  foreach ($mm as $m) {
    $key = $needle.':'.$m[1].($m[2] ?? '');
    if (isset($seen[$key])) { $seen[$key]['n']++; continue; }
    $seen[$key] = ['n'=>1,'path'=>$path,'line'=>(int)$m[1],'end'=>isset($m[2])?(int)$m[2]:(int)$m[1],'needle'=>$needle];
  }
}
foreach ($seen as $key => $s) {
  $beyond = $s['end'] > $total[$s['needle']] ? '  PAST-END-OF-FILE' : '';
  printf("%-34s x%d %s\n     %s%s\n", $key, $s['n'], '', str_replace("\n",' ', mb_substr($owner($s['path'], $s['line']),0,120)), $beyond);
}
