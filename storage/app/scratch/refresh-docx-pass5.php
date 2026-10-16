<?php

/**
 * Pass 5 — one surviving sentence in §25: it still said a printed block schedule leaves
 * no audit row. BlockingController.php:571-575 has written one since this workstream, and
 * the print template's capacity line now counts students, so both are recorded here.
 */

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$tmp = sys_get_temp_dir().'/ems-docx5-'.uniqid().'.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

function t(string $s): string
{
    return '<w:t xml:space="preserve">'.$s.'</w:t>';
}

$pairs = [
    [t('Block schedules are invisible in the print audit trail; '),
        t('Fixed 2026-10-01: the desk route records the issue, so '), 1, 'audit sentence: lead'],
    [t(' rows can only come from the unreachable '),
        t(' rows now come from the print screen and the PDF download, through '), 1, 'audit sentence: middle'],
    [t(' path.'),
        t('::recordIssue() (BlockingController.php:571-575). A printed roster leaves evidence again.'),
        1, 'audit sentence: tail'],
];

$fails = 0;
foreach ($pairs as [$needle, $to, $want, $label]) {
    $n = substr_count($xml, $needle);
    if ($n !== $want) {
        echo "FAIL {$label}: found {$n}x, want {$want}x\n";
        $fails++;
        continue;
    }
    $xml = str_replace($needle, $to, $xml);
    echo "ok   {$label} ({$n}x)\n";
}

if ($fails > 0) {
    fwrite(STDERR, "not writing\n");
    exit(1);
}
if (! $apply) {
    echo "dry run — pass --apply to write\n";
    exit(0);
}
copy($src, $tmp);
$z2 = new ZipArchive();
$z2->open($tmp);
$z2->addFromString('word/document.xml', $xml);
$z2->close();
rename($tmp, $src);
echo "APPLIED\n";
