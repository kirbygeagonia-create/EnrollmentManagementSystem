<?php
// Pass 7 — one abbreviated citation: the clearance unique index lives in the dated migration.
$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$tmp = sys_get_temp_dir().'/ems-docx7-'.uniqid().'.docx';
$apply = in_array('--apply', $argv, true);
$zip = new ZipArchive(); $zip->open($src); $xml = $zip->getFromName('word/document.xml'); $zip->close();
$needle = '<w:t xml:space="preserve">create_studentclearances_table.php:19</w:t>';
$to = '<w:t xml:space="preserve">2026_08_02_1000000021_create_studentclearances_table.php:19</w:t>';
if (substr_count($xml, $needle) !== 1) {
    fwrite(STDERR, "target not found exactly once — nothing written\n");
    exit(1);
}
$xml = str_replace($needle, $to, $xml);
echo "ok (1x)\n";
if (! $apply) { echo "dry run — pass --apply to write\n"; exit(0); }
copy($src, $tmp);
$z2 = new ZipArchive(); $z2->open($tmp); $z2->addFromString('word/document.xml', $xml); $z2->close();
rename($tmp, $src);
echo "APPLIED\n";
