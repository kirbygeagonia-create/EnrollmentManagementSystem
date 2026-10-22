<?php

/**
 * Pass 176 target probe (read-only): every needle the P-7 closure wants, judged against the XML rather than
 * against a remembered sentence. For each text needle it prints the hit count and whether the first hit sits
 * inside ONE <w:t> run; for each row it is going to edit or clone, it prints the row's cells with their
 * paragraph counts, because the engine rewrites a cell only when that cell holds exactly one <w:p>.
 *
 * Usage: php storage/app/scratch/lane62-probe-p7-targets.php
 */

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    exit("REFUSED: cannot open the docx.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$texts = [
    's24Keys' => 'Key-value system settings (currentTermId, schoolName, schoolAddress, schoolPhone, clearanceReplacementFee).',
    's255Empty' => 'and the address and phone print empty because those two rows are blank, not because the lookup missed.',
    's255Blank' => 'and schoolAddress and schoolPhone are seeded blank, each with its own description naming the Registrar as the office that owes it.',
    's255Nothing' => 'So the letterhead prints the maintained name and prints nothing where the school has not supplied a value',
    's255Tail' => 'requiring the row on disk to answer again.',
    's288' => 'Two print findings sit outside this table because it counts only 28.2 and 28.3: P-7, whose letterhead still prints no address and no phone, and P-2,',
    's305anchor' => 'the table is populated — 3 rows, one of them the name',
    's305cell3' => 'P-7 and P-12 in §25.4, §25.5',
    's306' => 'it is the two blank letterhead rows (P-7), the program and term the enrollment form cannot print (P-2), and the proxy an operator must name before HSTS means anything (G-13).',
    's11tail' => 'cited a method that spanned 172-233 on HEAD and 172-249 now.',
    'range' => 'P-1 … P-15',
];

echo "text needles\n";
foreach ($texts as $key => $p) {
    $n = substr_count($xml, $p);
    if ($n === 0) {
        printf("  %-14s 0 hits  — NOT IN THE XML AS TYPED [%s]\n", $key, mb_substr($p, 0, 52));
        continue;
    }
    $p0 = strpos($xml, $p);
    $tOpen = strrpos(substr($xml, 0, (int) $p0), '<w:t');
    $inner = $tOpen === false ? '' : substr($xml, $tOpen, $p0 - $tOpen);
    printf("  %-14s %d hit(s)  @%-8d  one run: %s\n", $key, $n, $p0,
        ($tOpen !== false && strpos($inner, '</w:t>') === false) ? 'yes' : 'NO — spans runs, needs a cell or paragraph edit');
}

$rows = [
    'P-7' => '>P-7<',
    'P-15 (clone template)' => '>P-15<',
    '28.4 one-line row' => 'A one-line change to a template or page',
    '28.4 decision row' => 'A decision before any code',
    '28.4 grouping intro' => 'P-1, P-5, P-6, P-7, P-11',
    '29.4 family row' => 'Printed-document findings',
    '30.5 settings row' => 'The settings table, with two rows left blank',
    'G-29' => '>G-29<',
];

foreach ($rows as $label => $anchor) {
    $n = substr_count($xml, $anchor);
    echo "\nrow ".$label.' — anchor ['.$anchor.'] hits '.$n."\n";
    $off = 0;
    while (($at = strpos($xml, $anchor, $off)) !== false) {
        $start = strrpos(substr($xml, 0, (int) $at), '<w:tr');
        $end = (int) strpos($xml, '</w:tr>', (int) $at) + 7;
        if ($start === false || strpos(substr($xml, (int) $start, $at - (int) $start), '</w:tr>') !== false) {
            printf("  @%-8d NOT INSIDE ONE ROW\n", $at);
            $off = $at + 1;

            continue;
        }
        $row = substr($xml, (int) $start, $end - (int) $start);
        preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);
        $cells = [];
        foreach ($cm[0] as $i => $cell) {
            preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell, $tm);
            $flat = preg_replace('/\s+/u', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1));
            $cells[] = sprintf('c%d[%d p]: %s', $i + 1, substr_count($cell, '<w:p>'), mb_substr($flat, 0, 46));
        }
        printf("  @%-8d %d cells: %s\n", $at, count($cm[0]), implode(' | ', $cells));
        $off = $at + 1;
    }
}
