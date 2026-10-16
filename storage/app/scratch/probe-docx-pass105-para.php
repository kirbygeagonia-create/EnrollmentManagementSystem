<?php

/** Print the whole paragraph that pass 105 has to extend, as text and as raw XML, with offsets. */
$root = dirname(__DIR__, 3);
$zip = new ZipArchive();
$zip->open($root.'/Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$at = strpos($xml, 'ems:preflight turns DEPLOYMENT.md into a command');
if ($at === false) {
    exit("anchor gone\n");
}

$before = substr($xml, 0, $at);
// strrpos() has no "search backwards from an offset" form, so bound the haystack to the text
// before the anchor instead.
$a = strrpos($before, '<w:p ');
$b = strrpos($before, '<w:p>');
$pStart = max($a === false ? -1 : $a, $b === false ? -1 : $b);
$pEnd = strpos($xml, '</w:p>', $at) + strlen('</w:p>');

echo 'paragraph bytes: '.$pStart.' .. '.$pEnd.'  ('.($pEnd - $pStart)." bytes)\n\n";
echo "---- AS TEXT ----\n";
echo preg_replace('/\s+/', ' ', strip_tags(substr($xml, $pStart, $pEnd - $pStart)))."\n\n";
echo "---- AS XML (whole paragraph) ----\n";
echo substr($xml, $pStart, min($pEnd - $pStart, 4000))."\n";
echo "\n---- how many <w:t> runs carry it ----\n";
echo substr_count(substr($xml, $pStart, $pEnd - $pStart), '<w:t')."\n";
