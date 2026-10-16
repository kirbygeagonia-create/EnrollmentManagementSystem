<?php
// What style would a whole-paragraph rewrite inherit? The engine takes the FIRST run's rPr;
// if that run is an inline code span, the rebuilt paragraph arrives in Consolas.
$src = 'Documentation/EMS Complete Documentation.docx';
$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$anchors = [
    'Data artifact to correct.',
    '`clearancerequirements` cannot describe a requirement.',
    'Elective configuration is unreachable.',
    'Correction established in this edition: There are 10 rows here',
    'The one fee stated on paper — “Lost slip replacement fee: ₱100.00”',
    'Requirements block. One row per clearancerequirements row, printed as an office name, because the table has no requirement text (P-8).',
    'All 13 refdata.*.manage permissions are held by SysAdmin alone',
    'deleting an office row orphans staffusers.officeId',
];

foreach ($anchors as $a) {
    $n = substr_count($xml, $a);
    echo str_repeat('-', 78).PHP_EOL;
    printf("ANCHOR seen %d | %s\n", $n, mb_substr($a, 0, 70));
    if ($n === 0) {
        continue;
    }
    $p = strpos($xml, $a);
    $start = max(strrpos(substr($xml, 0, $p), '<w:p>'), strrpos(substr($xml, 0, $p), '<w:p '));
    $end = strpos($xml, '</w:p>', $p) + 6;
    $body = substr($xml, $start, $end - $start);
    preg_match_all('/<w:r\b.*?<\/w:r>/s', $body, $rm);
    printf("  runs: %d, bytes: %d\n", count($rm[0]), strlen($body));
    foreach ($rm[0] as $k => $run) {
        preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $run, $tm);
        $txt = html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1);
        $font = preg_match('/w:ascii="([^"]+)"/', $run, $f) ? $f[1] : '(inherited)';
        $sz = preg_match('/<w:sz w:val="(\d+)"/', $run, $s) ? $s[1] : '-';
        printf("   run %d  font=%-14s sz=%-3s len=%-4d %s\n", $k, $font, $sz, mb_strlen($txt), mb_substr($txt, 0, 46));
    }
}
