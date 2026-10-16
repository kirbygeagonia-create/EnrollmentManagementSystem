<?php

/**
 * Print, for each needle, the whole enclosing paragraph as the reader sees it — every <w:t> run
 * joined in document order. The pieces of an edit can look right while the joined sentence reads
 * wrong, so the joined form is what gets judged before the write.
 */

$root = dirname(__DIR__, 3);
$zip = new ZipArchive();
if ($zip->open($root.'/Documentation/EMS Complete Documentation.docx') !== true) {
    exit("cannot open\n");
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$needles = [
    'ten questions left for the Registrar to answer',
    'Its workflow box is separately recorded as completed with no validator',
    'The comparison that found the divergences resolves every tone',
];

foreach ($needles as $needle) {
    $at = strpos($xml, $needle);
    if ($at === false) {
        echo "NOT PRESENT: {$needle}\n\n";

        continue;
    }
    $pStart = strrpos(substr($xml, 0, $at), '<w:p ');
    if ($pStart === false) {
        $pStart = strrpos(substr($xml, 0, $at), '<w:p>');
    }
    $pEnd = strpos($xml, '</w:p>', $at);
    $para = substr($xml, $pStart, $pEnd - $pStart);

    preg_match_all('/<w:t(?:\s[^>]*)?>(.*?)<\/w:t>/s', $para, $m);
    $text = implode('', array_map(fn (string $run) => html_entity_decode($run, ENT_QUOTES | ENT_XML1), $m[1]));

    echo "=== {$needle}\n";
    echo "    paragraph bytes: ".($pEnd - $pStart)."   runs: ".count($m[1])."\n";
    echo "    TEXT: ".$text."\n\n";
}
