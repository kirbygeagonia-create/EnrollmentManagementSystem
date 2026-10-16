<?php

/**
 * Pass 10 — two cells that overclaimed, corrected with what the live database shows.
 *
 * §16's prerequisites gate and §28's D-4 row describe mechanisms that are real and now
 * reachable, but on this install they pass by absence: no curriculumsubjects row is
 * flagged elective, and the demo term (2026-10-02 query) offers no subject at the level
 * and semester 17 active enrollments sit. The pipeline counts both from this batch on.
 */

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$tmp = sys_get_temp_dir().'/ems-docx10-'.uniqid().'.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$pairs = [
    [
        'No — but a load decided before the curriculum moved, a retake, or a direct proposal can clear the Evaluation screen’s lock and still miss the rule, so this desk reads the record rather than the button (EnrollmentReadiness::prerequisitesMet).',
        'No — but a load decided before the curriculum moved, a retake, or a direct proposal can clear the Evaluation screen’s lock and still miss the rule, so this desk reads the record rather than the button (EnrollmentReadiness::prerequisitesMet). It also reads true when there is nothing to read: on the installed demo term the curriculum offers no subject at the year level and semester 17 active enrollments sit (§28, G-9), so the gate passes by absence there until the Registrar states those offerings or the demo sits a term the curriculum covers.',
        1, '16 gate 7: true by absence',
    ],
    [
        'Closed 2026-10-01. curriculumsubjects carries is_elective, elective_group, elective_min_choices and elective_max_choices; the reference-data screen edits all four, and ReferenceDataController::normalizeElective clears the band when a subject is not an elective — so elective bounds can be configured and can actually fire at Evaluation.',
        'Closed 2026-10-01, and made visible on 2026-10-02. curriculumsubjects carries is_elective, elective_group, elective_min_choices and elective_max_choices; the reference-data screen edits all four, ReferenceDataController::normalizeElective clears the band when a subject is not an elective, EvaluationController refuses a proposal outside min–max (:470-476), and the Evaluation picker now prints each group’s band with a live chosen count instead of letting the evaluator discover the range from the refusal. What still keeps the rule undemonstrable on this install is data, not code: no curriculumsubjects row is flagged elective, and the demo term offers nothing at the levels 17 active enrollments sit (§28, G-9). The pipeline counts both from this batch on.',
        1, '28 D-4: configured, visible, not yet exercised',
    ],
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
