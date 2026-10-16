<?php
// Heuristic for splice damage: a run whose text contains a period followed by a
// lowercase word, or the same closure phrase twice. My edits wrote whole sentences, so
// either pattern means a needle ate part of a sentence and left the rest.
$z = new ZipArchive(); $z->open(dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx');
$x = $z->getFromName('word/document.xml'); $z->close();
preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $x, $m);
$flagged = 0;
foreach ($m[1] as $run) {
    $t = html_entity_decode($run, ENT_QUOTES, 'UTF-8');
    if (preg_match('/(Partly done 2026-10-01|Done 2026-10-01|Closed 2026-10-01|Fixed 2026-10-01|Contained 2026-10-01|Still open\.)/', $t)
        && substr_count($t, '2026-10-01') > 2) {
        echo "DUPLICATE CLOSURE: ".substr($t, 0, 160)."\n"; $flagged++;
    }
    if (preg_match('/[.!?]\s+(it|the|and but|which is a schema)\b/u', $t)) {
        echo "LOWER AFTER STOP: ".substr($t, 0, 140)."\n"; $flagged++;
    }
}
echo $flagled ?? $flagged." suspect run(s) of ".count($m[1])."\n";
