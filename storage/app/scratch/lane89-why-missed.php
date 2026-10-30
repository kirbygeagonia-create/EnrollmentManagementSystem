<?php

// Read-only: why does the archive lookup miss the §24 resultStatus row?
$root = dirname(__DIR__, 3);

$dump = (string) file_get_contents($root.'/storage/app/scratch/docx-flat.txt');
preg_match('/ROW (\d+): (.*resultStatus.*)/u', $dump, $m);
$dumpKey = preg_replace('/[\s|]+/u', '', $m[2]);
printf("dump row %s\n  raw : %s\n  key : %s\n\n", $m[1], $m[2], $dumpKey);

$zip = new ZipArchive;
$zip->open($root.'/Documentation/EMS Complete Documentation.docx');
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$flat = static function (string $frag): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $frag, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
};
$at = 0;
$found = 0;
while (($start = strpos($xml, '<w:tr', $at)) !== false) {
    $end = strpos($xml, '</w:tr>', $start) + 7;
    preg_match_all('/<w:tc[ >].*?<\/w:tc>/s', substr($xml, $start, $end - $start), $cm);
    $cells = array_map($flat, $cm[0]);
    if (($cells[0] ?? '') === 'resultStatus') {
        $archKey = preg_replace('/[\s|]+/u', '', implode('', $cells));
        printf("archive row\n  cells: %s\n  key  : %s\n  equal: %s\n", json_encode($cells), $archKey,
            $archKey === $dumpKey ? 'YES' : 'NO');
        $found++;
    }
    $at = $end;
}
printf("\n%d archive rows whose first cell is exactly resultStatus.\n", $found);
