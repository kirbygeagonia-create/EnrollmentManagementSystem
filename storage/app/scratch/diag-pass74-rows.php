<?php
// Rebuild each candidate row alone and parse it inside a minimal wrapper, to find which one is
// malformed. Reported as (module/role name, first 80 chars of the rebuilt row).
$root = dirname(__DIR__, 3);
$zip = new ZipArchive(); $zip->open($root.'/Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml'); $zip->close();
$esc = fn (string $s) => str_replace(['&','<','>'], ['&amp;','&lt;','&gt;'], $s);
$decode = function (string $c): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $c, $m);
    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode(' ', $m[1] ?? []), ENT_QUOTES | ENT_XML1)));
};
$h = strpos($xml, '22.2 Permission catalogue');
$tblStart = strpos($xml, '<w:tr', $h);
$tblEnd = strpos($xml, '</w:tbl>', $tblStart);
preg_match_all('/<w:tr\b.*?<\/w:tr>/s', substr($xml, $tblStart, $tblEnd - $tblStart + 8), $rows, PREG_OFFSET_CAPTURE);
foreach ($rows[0] as $r) {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $r[0], $cells);
    $texts = array_map($decode, $cells[0]);
    if (count($texts) !== 3 || in_array($texts[0], ['Module','Total'], true)) continue;
    $out = $r[0];
    foreach ($cells[0] as $i => $cell) {
        if ($i === 0) continue;
        preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
        preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
        $body = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
        $new = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'.$esc('X'.$i).'</w:t></w:r></w:p>', $body);
        $out = str_replace($cell, $new, $out);
    }
    $ok = simplexml_load_string('<w:root xmlns:w="x">'.$out.'</w:root>');
    printf("%-14s parse=%s  cells=%d  len %d->%d\n", $texts[0], $ok === false ? 'BAD' : 'ok', count($texts), strlen($r[0]), strlen($out));
    if ($ok === false) { echo "   rebuilt: ".substr($out, 0, 240)."\n"; }
}
