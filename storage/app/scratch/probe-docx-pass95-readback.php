<?php

/** Read back pass 95's three replacements from the written file, joined with their neighbours. */

$root = dirname(__DIR__, 3);
$zip = new ZipArchive();
if ($zip->open($root.'/Documentation/EMS Complete Documentation.docx') !== true) {
    exit("cannot open\n");
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$needles = [
    'nine questions left for the Registrar to answer',
    'and that claim was wrong, withdrawn on 2026-10-11',
    'The nine were put to the office on 2026-10-11 as',
];

foreach ($needles as $needle) {
    $at = strpos($xml, $needle);
    if ($at === false) {
        echo "MISSING: {$needle}\n\n";

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
    $rel = strpos($text, $needle);
    $from = max(0, $rel - 420);
    $to = min(strlen($text), $rel + 1500);

    echo "=== {$needle}\n";
    echo "...".substr($text, $from, $to - $from)."...\n\n";
}
