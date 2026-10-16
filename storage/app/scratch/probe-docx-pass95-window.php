<?php

/** Print a window of joined paragraph text around each needle — the sentence it lands in, not the whole paragraph. */

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
    $rel = strpos($text, $needle);
    $from = max(0, $rel - 700);
    $to = min(strlen($text), $rel + strlen($needle) + 900);

    echo "=== {$needle}\n";
    echo "   offset in paragraph: {$rel} of ".strlen($text)."\n";
    echo "   BEFORE: ...".substr($text, $from, $rel - $from)."\n";
    echo "   AFTER : ".substr($text, $rel, $to - $rel)."...\n\n";
}
