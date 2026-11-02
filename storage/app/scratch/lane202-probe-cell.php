<?php

/**
 * Where does a given paragraph sit: inside a table cell (the answer box), and where does that cell end?
 * The answered copy must write its text INSIDE the cell, or the answer lands outside the box the school drew.
 *
 * Usage: php storage/app/scratch/lane202-probe-cell.php [--para=<index>]
 */
$src = 'C:/Users/ADMIN/Downloads/ADS_Final_Project_Guide_EMS_updated.docx';
$want = 49;
$args = array_slice($GLOBALS['argv'], 1);
foreach ($args as $a) {
    if (preg_match('/^--para=(\d+)$/', $a, $m)) {
        $want = (int) $m[1];
    } else {
        fwrite(STDERR, 'REFUSED: unreadable argument '.$a."\n");
        exit(1);
    }
}
$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    fwrite(STDERR, "REFUSED: the guide did not open.\n");
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

// Walk paragraphs in document order with their byte offsets.
$paras = [];
if (preg_match_all('/<w:p[ >]/', $xml, $opens, PREG_OFFSET_CAPTURE)) {
    foreach ($opens[0] as $o) {
        $end = strpos($xml, '</w:p>', $o[1]);
        if ($end === false) {
            continue;
        }
        $paras[] = ['start' => $o[1], 'end' => $end + 6];
    }
}
if (! isset($paras[$want])) {
    fwrite(STDERR, "REFUSED: paragraph {$want} does not exist (there are ".count($paras).").\n");
    exit(1);
}
$p = $paras[$want];
$body = substr($xml, $p['start'], $p['end'] - $p['start']);
preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $body, $tm);
$text = html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1);
printf("paragraph %d of %d; bytes %d..%d (%d)\n", $want, count($paras), $p['start'], $p['end'], $p['end'] - $p['start']);
printf("text: %s\n", mb_substr($text, 0, 100));
printf("runs (<w:r>) in it: %d; <w:t> elements: %d\n", substr_count($body, '<w:r>'), count($tm[1]));

// Enclosing cell: the nearest <w:tc> whose </w:tc> comes after this paragraph.
$tcOpen = strrpos($xml, '<w:tc>', $p['start'] - strlen($xml));
$tcClose = strpos($xml, '</w:tc>', $p['end']);
$tblOpen = strrpos($xml, '<w:tbl>', $p['start'] - strlen($xml));
$tblClose = strpos($xml, '</w:tbl>', $p['end']);
printf("enclosing <w:tc>: %s; that cell's </w:tc> at %s (paragraph ends %d, so %d bytes of room after it)\n",
    $tcOpen === false ? 'NONE' : (string) $tcOpen, $tcClose === false ? 'NONE' : (string) $tcClose, $p['end'],
    $tcClose === false ? -1 : $tcClose - $p['end']);
printf("enclosing <w:tbl>: %s..%s\n", $tblOpen === false ? 'NONE' : (string) $tblOpen, $tblClose === false ? 'NONE' : (string) $tblClose);
printf("paragraphs inside that cell: %d\n", $tcClose === false || $tcOpen === false ? -1 :
    count(array_filter($paras, static fn (array $q): bool => $q['start'] > $tcOpen && $q['end'] <= $tcClose)));
echo "\n--- the cell's paragraphs, in order ---\n";
if ($tcOpen !== false && $tcClose !== false) {
    $n = 0;
    foreach ($paras as $i => $q) {
        if ($q['start'] > $tcOpen && $q['end'] <= $tcClose) {
            $b = substr($xml, $q['start'], $q['end'] - $q['start']);
            preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $b, $t2);
            printf("  [%d] +%d..+%d  %s\n", $i, $q['start'] - $tcOpen, $q['end'] - $tcOpen,
                mb_substr(html_entity_decode(implode('', $t2[1]), ENT_QUOTES | ENT_XML1), 0, 70) ?: '(empty)');
            if (++$n > 12) {
                echo "  ...\n";
                break;
            }
        }
    }
}
