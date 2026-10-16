<?php
// Run-level text of the two cells pass 72 must rewrite, plus the queue-column line numbers the
// corrected sentence will cite.
$z = new ZipArchive;
$z->open('Documentation/EMS Complete Documentation.docx');
$xml = $z->getFromName('word/document.xml');
$z->close();

foreach ([['B-3 region', 47000, 2000], ['table9 region', 184000, 1400]] as [$name, $from, $span]) {
    echo "===== $name =====\n";
    $chunk = substr($xml, $from, $span);
    if (preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $chunk, $m)) {
        foreach ($m[1] as $i => $t) {
            printf("  run %-3d @%d  «%s»\n", $i + 1, $from + strpos($chunk, $m[0][$i]), $t);
        }
    }
    echo "\n";
}

echo "== Evaluation/Index.jsx lines 74, 84-90 ==\n";
foreach (file('resources/js/Pages/Evaluation/Index.jsx') as $no => $line) {
    if (in_array($no + 1, [74, 84, 85, 86, 87, 88, 89, 90, 91], true)) {
        printf("  %-4d %s\n", $no + 1, rtrim($line));
    }
}
