<?php

/**
 * Integrity and consistency check on the refreshed docx: the zip must open, the body
 * must parse as XML, the element counts must be balanced, and the strings the 2026-10-01
 * build made false must be gone while the replacements are present.
 */

$path = $argv[1] ?? dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$zip = new ZipArchive();
if ($zip->open($path) !== true) {
    fwrite(STDERR, "ZIP: cannot open\n");
    exit(1);
}
echo 'ZIP: '.$zip->numFiles." entries, ".round(filesize($path) / 1024).' KB'."\n";
$xml = $zip->getFromName('word/document.xml');
$names = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $names[] = $zip->getNameIndex($i);
}
$zip->close();
echo 'entries: '.implode(', ', $names)."\n";

libxml_use_internal_errors(true);
$doc = simplexml_load_string($xml);
if ($doc === false) {
    echo "XML: PARSE FAILED\n";
    foreach (libxml_get_errors() as $err) {
        echo '  '.trim($err->message)." at offset {$err->column}\n";
    }
    exit(1);
}
echo "XML: parses, root ".$doc->getName().' with '.$doc->children()->count().' children'."\n";

$pairs = [
    ['<w:body>', '</w:body>'], ['<w:tbl>', '</w:tbl>'], ['<w:tr>', '</w:tr>'],
    ['<w:tc>', '</w:tc>'], ['<w:p>', '</w:p>'], ['<w:r>', '</w:r>'], ['<w:t ', '</w:t>'],
];
foreach ($pairs as [$open, $close]) {
    $o = substr_count($xml, $open);
    $c = substr_count($xml, $close);
    printf("%-12s open %6d close %6d %s\n", $open, $o, $c, $o === $c ? 'balanced' : '*** UNBALANCED ***');
}

$flat = preg_replace('/<[^>]+>/', '', $xml);

$stale = [
    // One id.release survives on purpose: the §18.3 note names the retired permission
    // to say it no longer exists. Everything else must be gone.
    'id.release' => 1,
    'id.releaseRequest' => 0,
    '4 Release' => 0,
    'status = released.' => 0,
    'A completed workflow does not mean a released card.' => 0,
    ' or released.' => 0,
    'releasedDate' => 0,
    '1st Year' => 0,
    'five gates' => 0,
    'five-gate' => 0,
    '5 registrar_ready' => 0,
    'RegistrarController.php:110-128' => 0,
    'RegistrarController.php:143-158' => 0,
    'AssessmentPolicy.php:40-43' => 0,
    'AssessmentPolicy.php:45-46' => 0,
    'AssessmentPolicy.php:32-57' => 0,
    'EvaluationController.php:493-510' => 0,
    'Revised 2026-09-28' => 0,
    'For human confirmation (C-9)' => 0,
];
$fresh = [
    'id.remarkRequest' => 'the ID desk action that replaced release',
    'mismatchRemark' => 'the column the remark writes',
    '4 Remark' => 'the step row, reworded',
    'documents_verified' => 'Registrar gate 6',
    'prerequisites_met' => 'Registrar gate 7',
    'registrarApprovalPending' => 'Registrar gate 5, named as the code names it',
    'seven-gate checklist' => 'the checklist length',
    'requiresCourseSpecificExam' => 'the course-specific examination flag',
    'WorkflowInbox' => 'the notification recipient resolver',
    'formSignedDate' => 'the signature compute now needs',
    'formatYearLevel' => 'the one year-level formatter',
    'Year 1 (Freshman)' => 'the year-level vocabulary',
];

echo "\nstale strings (want the stated count):\n";
$bad = 0;
foreach ($stale as $needle => $want) {
    $n = substr_count($xml, $needle);
    $ok = $n === $want;
    if (! $ok) {
        $bad++;
    }
    printf("  %-40s %d %s\n", $needle, $n, $ok ? 'ok' : "*** expected {$want} ***");
}

echo "\nreplacements present:\n";
foreach ($fresh as $needle => $why) {
    $n = substr_count($flat, $needle);
    if ($n === 0) {
        $bad++;
    }
    printf("  %-40s %d  %s%s\n", $needle, $n, $why, $n === 0 ? ' *** MISSING ***' : '');
}

// The ID release must be gone from the desk table but the column dictionary may still
// describe the value the database enum allows, and prose about released references is
// unrelated. Name every surviving "released" so a reviewer can judge each one.
echo "\nsurviving uses of “released”:\n";
preg_match_all('/[^.]{0,70}released[^.]{0,70}/', $flat, $m);
foreach (array_unique($m[0]) as $hit) {
    echo '  - '.trim(preg_replace('/\s+/', ' ', $hit))."\n";
}

echo "\n".($bad === 0 ? "CONSISTENT" : "{$bad} PROBLEM(S)")."\n";
exit($bad === 0 ? 0 : 1);
