<?php

/** Which row holds the P-19 text, and what does its first cell actually contain? */
$root = dirname(__DIR__, 3);
$zip = new ZipArchive();
$zip->open($root.'/Documentation/EMS Complete Documentation.docx');
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$flat = static function (string $chunk): string {
    preg_match_all('/<w:t(?:\s[^>]*)?>(.*?)<\/w:t>/s', $chunk, $t);

    return html_entity_decode(implode('', $t[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
};

preg_match_all('/<w:tr\b.*?<\/w:tr>/s', $xml, $rows);
printf("rows found: %d\n", count($rows[0]));

foreach ($rows[0] as $i => $r) {
    $text = $flat($r);
    if (! str_contains($text, "Student's Copy") && ! str_contains($text, 'P-19')) {
        continue;
    }
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $r, $cm);
    $cells = array_map(static fn (string $c): string => trim($flat($c)), $cm[0]);
    printf("\nrow %d: %d cells | first=[%s] | second starts=[%s]\n", $i, count($cells), $cells[0] ?? '', mb_substr($cells[1] ?? '', 0, 60));
    if (in_array($i, [1419, 1443, 1685], true)) {
        foreach ($cells as $j => $c) {
            echo "===== cell {$j} =====\n{$c}\n";
        }
    }
}
