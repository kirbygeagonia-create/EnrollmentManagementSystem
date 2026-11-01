<?php
// Reads rows naming a token out of any docx's word/document.xml, flattened the way the engine
// flattens them (cells joined with a separator). Used to recover the wording an earlier edition
// carried for a register row that a later pass rewrote.
$path = $argv[1] ?? '';
$token = $argv[2] ?? 'seatKey';
if ($path === '' || ! is_file($path)) {
    fwrite(STDERR, "REFUSED: pass an existing docx as the first argument.\n");
    exit(1);
}
$zip = new ZipArchive;
if ($zip->open($path) !== true) {
    fwrite(STDERR, "REFUSED: ZipArchive::open answered ".$zip->open($path)." for {$path}; nothing was read.\n");
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
if ($xml === '') {
    fwrite(STDERR, "REFUSED: word/document.xml read empty from {$path}.\n");
    exit(1);
}
preg_match_all('/<w:tr.*?<\/w:tr>/s', $xml, $rm);
$hits = 0;
foreach ($rm[0] as $r) {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $r, $tm);
    $t = trim(preg_replace('/\s+/', ' ', html_entity_decode(implode(' ', $tm[1]), ENT_QUOTES | ENT_XML1)));
    if (! str_contains($t, $token)) {
        continue;
    }
    $hits++;
    echo "--- ROW {$hits}: ".$t."\n\n";
}
// Paragraphs too, because a §24 column row can be a real row while a heading is a paragraph.
preg_match_all('/<w:p>.*?<\/w:p>/s', $xml, $pm);
foreach ($pm[0] as $p) {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $p, $tm2);
    $t = trim(preg_replace('/\s+/', ' ', html_entity_decode(implode(' ', $tm2[1]), ENT_QUOTES | ENT_XML1)));
    if ($t !== '' && str_contains($t, $token) && ! str_contains($t, ' | ')) {
        echo "--- PARA: ".substr($t, 0, 500)."\n";
    }
}
echo 'rows naming '.$token.': '.$hits."\n";
