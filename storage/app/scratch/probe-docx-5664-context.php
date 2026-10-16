<?php
/**
 * The two remaining places that claim ExamPolicy.php:56-64 enforces the general-pass
 * prerequisite, with enough surrounding text to rewrite each faithfully.
 *
 * Nothing is written.
 */

$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive();
$z->open($docx);
$xml = $z->getFromName('word/document.xml');
$z->close();

foreach ([203644, 615623] as $pos) {
    echo str_repeat('=', 100)."\nOCCURRENCE @{$pos}\n".str_repeat('=', 100)."\n";

    $s = max(strrpos(substr($xml, 0, $pos), '<w:p>'), strrpos(substr($xml, 0, $pos), '<w:p '));
    $e = strpos($xml, '</w:p>', $pos) + 6;
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', substr($xml, $s, $e - $s), $tm);
    echo "paragraph @{$s}..{$e} runs=".count($tm[1]).":\n  ".preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1))."\n";
    echo 'para anchor candidate: '.var_export(mb_substr(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)), 0, 60), true)."\n";

    $rs = strrpos(substr($xml, 0, $pos), '<w:tr');
    $re = strpos($xml, '</w:tr>', $pos) + 7;
    if ($rs !== false && strpos(substr($xml, $rs, $pos - $rs), '</w:tr>') === false) {
        preg_match_all('/<w:tc>.*?<\/w:tc>/s', substr($xml, $rs, $re - $rs), $rm);
        echo 'ROW @'.$rs.' cells='.count($rm[0]).":\n";
        foreach ($rm[0] as $k => $cc) {
            preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cc, $t2);
            echo '  ['.$k.'] '.mb_substr(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $t2[1]), ENT_QUOTES | ENT_XML1)), 0, 200)."\n";
        }
    }

    // What section owns this offset: the last "N. Title" or "N.M Title" heading before it.
    $before = preg_replace('/\s+/', ' ', strip_tags(substr($xml, 0, $pos)));
    if (preg_match_all('/\b(\d{1,2})(?:\.(\d{1,2}))?\s+([A-Z][A-Za-z, —·-]{4,60})/', $before, $hm, PREG_SET_ORDER)) {
        $last = array_slice($hm, -3);
        echo 'nearby headings: ';
        foreach ($last as $h) {
            echo '['.trim($h[1].($h[2] !== '' ? '.'.$h[2] : '').' '.$h[3]).'] ';
        }
        echo "\n";
    }
    echo "\n";
}
