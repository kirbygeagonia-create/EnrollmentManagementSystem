<?php

/**
 * Third pass on the docx: the dead-code batch.
 *
 * Ten app/Http/Requests classes and both app/Jobs classes are referenced nowhere but
 * their own files — the controllers validate inline — and the document's own §26 offered
 * "remove the unreachable methods" as one of two options for the queued printer. The
 * citations that named those classes are re-pointed at the code that actually enforces
 * the rule, with the line numbers the repository holds today, and the sentences whose
 * claims rested on them are corrected in the same write.
 *
 * Every needle is a whole <w:t> element, so a match is always a complete run and never a
 * fragment of one; the count check makes a wrong assumption fail instead of edit.
 */

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$tmp = sys_get_temp_dir().'/ems-docx3-'.uniqid().'.docx';
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
    [t('StoreApplicantRequest.php:26'), t('AdmissionController.php:106'), 1, 'religionId required at intake'],
    [t('CaptureProfileRequest.php:25'), t('EvaluationController.php:298'), 1, 'religionId required at capture'],
    [t('CaptureProfileRequest.php:51'), t('EvaluationController.php:324'), 1, 'academicStanding captured'],
    [t('CaptureProfileRequest.php:47-51'), t('EvaluationController.php:289-330'), 1, 'profile field rules'],
    [t('Evaluation/CaptureProfileRequest.php:44-52'), t('EvaluationController.php:289-330'), 1, 'profile rules in a files list'],
    [t('StoreApplicantRequest.php:35'), t('app/Enums/ApplicantType.php'), 1, 'applicant type citation'],
    [t('AdmissionController.php:107'), t('AdmissionController.php:115'), 1, 'applicantType rule line'],
    [t('required|in:firstYear,transferee,continuing,shifter'), t('required + Rule::enum(ApplicantType::class)'), 1, 'applicantType rule form'],
    [t('). On a fresh install an applicant of any other religion cannot be registered (§28, D-2)'),
        t('). Eight religions ship from StarterReferenceDataSeeder, so neither intake nor profile capture is blocked on one permitted value (§28, D-2 closed 2026-10-01); the list itself stays school policy.'),
        1, 'D-2 religion clause'],
    [t('Reach the PDF path or delete it'), t('Reach the PDF path or delete it (resolved 2026-10-01)'), 1, 'print gap row title'],
];

$failures = 0;
foreach ($pairs as $i => [$needle, $to, $want, $label]) {
    $n = substr_count($xml, $needle);
    if ($n !== $want) {
        echo sprintf("raw  FAIL #%d %s: found %dx, want %dx\n", $i + 1, $label, $n, $want);
        $failures++;
        continue;
    }
    $xml = str_replace($needle, $to, $xml);
    echo sprintf("raw  ok   #%d %s (%dx)\n", $i + 1, $label, $n);
}

if ($failures > 0) {
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
