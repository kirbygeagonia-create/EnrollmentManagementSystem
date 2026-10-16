<?php

/**
 * Pass 6 — citations the 2026-10-01/02 work moved, and the two sentences that still
 * describe print behaviour the code has since changed.
 *
 * Each number below was re-read from the file it names, not from the document:
 * printCertificate :292 (recordIssue :302), printClassCards :314, printSubjectLoad :344,
 * printSlip :303, PrintService::printClearanceSlip :59, the Evaluation queue filter
 * :57-64, the only Enrollments::create :364-374, the notification JSON routes :266-268,
 * and the block-schedule print which now writes its own issue row (:559-575).
 */

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$tmp = sys_get_temp_dir().'/ems-docx6-'.uniqid().'.docx';
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
    [t('RegistrarController.php:244'), t('RegistrarController.php:292'), 2, 'printCertificate line'],
    [t(':277'), t(':314'), 2, 'printClassCards line'],
    [t(':307'), t(':344'), 2, 'printSubjectLoad line'],
    [t('ClearanceController.php:298'), t('ClearanceController.php:303'), 3, 'printSlip line (as :298)'],
    [t('ClearanceController.php:297'), t('ClearanceController.php:303'), 2, 'printSlip line (as :297)'],
    [t('PrintService.php:54'), t('PrintService.php:59'), 1, 'printClearanceSlip line'],
    [t('PrintService.php:55,60'), t('PrintService.php:59'), 1, 'printClearanceSlip lines'],
    [t('EvaluationController.php:50-55'), t('EvaluationController.php:57-64'), 2, 'Evaluation queue filter'],
    ['AdmissionController.php:322-333', 'AdmissionController.php:364-374', 5, 'the only Enrollments::create, in any run form'],
    [t('routes/web.php:245-247'), t('routes/web.php:266-268'), 1, 'notification JSON routes'],
    [t('BlockingController.php:531-542'), t('BlockingController.php:559-575'), 3, 'block-schedule print'],
    [t(' (authorize, load, render — no create); compare '),
        t(' — which now writes its own issue row through recordIssue() (:571-575), like the desks beside it: '),
        1, '20 audit prose: block print is logged'],
    [t('RegistrarController.php:244-252'), t('RegistrarController.php:302'), 1, 'certificate log creation line'],
    [t('RegistrarController.php:244,277,307'), t('RegistrarController.php:292,314,344'), 1, 'print actions cited in one breath'],
    [t(' and not passed to the page'),
        t(' and passed to the page; the certificate PDF mints its own issue row and prints the number beside it (PrintService.php:122-126, enrollment-certificate.blade.php:44-47)'),
        1, 'certificate: the number reaches the paper'],
];

$fails = 0;
foreach ($pairs as $i => [$needle, $to, $want, $label]) {
    $n = substr_count($xml, $needle);
    if ($n !== $want) {
        echo sprintf("FAIL #%d %s: found %dx, want %dx\n", $i + 1, $label, $n, $want);
        $fails++;
        continue;
    }
    $xml = str_replace($needle, $to, $xml);
    echo sprintf("ok   #%d %s (%dx)\n", $i + 1, $label, $n);
}

echo "\n".($fails === 0 ? 'all targets resolved' : "{$fails} failure(s)")."\n";
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
