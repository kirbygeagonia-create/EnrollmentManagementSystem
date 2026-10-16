<?php
/**
 * The full paragraph or cell behind each C-4 / C-5 / C-8 target, so a rewrite can be
 * written as a whole rather than guessed at from a fragment.
 *
 * The engine's `para` kind replaces the entire <w:p> that owns a unique anchor, and its
 * `cell` kind replaces an entire table cell. Both need the CURRENT full text to produce a
 * faithful replacement — reading only the sentence would silently drop its neighbours.
 *
 * Nothing is written.
 */

$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive();
$z->open($docx);
$xml = $z->getFromName('word/document.xml');
$z->close();

$targets = [
    'C-4 section 9.4' => 'must pass the general examination like a freshman',
    'C-5 section 10.6' => 'Where the flag is off the earlier reading still holds',
    'C-5 register cite' => 'AdmissionPolicy.php:96-130',
    'C-4 section 11.4' => 'A continuing student already entered the program',
    'C-8 section 20.9' => 'the document cannot state one because none is configured',
];

foreach ($targets as $label => $frag) {
    $n = substr_count($xml, $frag);
    echo str_repeat('=', 100)."\n{$label}   [{$frag}]   raw occurrences: {$n}\n".str_repeat('=', 100)."\n";
    if ($n === 0) {
        echo "  -> split across runs; hunt the run boundaries\n";
        continue;
    }
    $pos = strpos($xml, $frag);

    // Enclosing paragraph.
    $pStart = max(strrpos(substr($xml, 0, $pos), '<w:p>'), strrpos(substr($xml, 0, $pos), '<w:p '));
    $pEnd = strpos($xml, '</w:p>', $pos) + 6;
    $para = substr($xml, $pStart, $pEnd - $pStart);
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $para, $tm);
    $text = preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1));
    echo "  paragraph @{$pStart}..{$pEnd} (".strlen($para)." bytes, runs=".count($tm[1]).")\n";
    echo '  TEXT: '.$text."\n";

    // Enclosing table cell, if the paragraph sits in one.
    $cStart = strrpos(substr($xml, 0, $pos), '<w:tc>');
    $rowStart = strrpos(substr($xml, 0, $pos), '<w:tr');
    if ($cStart !== false && $rowStart !== false && $cStart > $rowStart) {
        $cEnd = strpos($xml, '</w:tc>', $pos) + 7;
        $cell = substr($xml, $cStart, $cEnd - $cStart);
        preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell, $cm2);
        echo '  CELL @'.$cStart.': '.preg_replace('/\s+/', ' ', html_entity_decode(implode('', $cm2[1]), ENT_QUOTES | ENT_XML1))."\n";
        $rEnd = strpos($xml, '</w:tr>', $pos) + 7;
        $row = substr($xml, $rowStart, $rEnd - $rowStart);
        preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $rm);
        echo '  ROW @'.$rowStart.' cells='.count($rm[0]).":\n";
        foreach ($rm[0] as $k => $cc) {
            preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cc, $t2);
            echo '    ['.$k.'] '.mb_substr(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $t2[1]), ENT_QUOTES | ENT_XML1)), 0, 220)."\n";
        }
    }
    echo "\n";
}
