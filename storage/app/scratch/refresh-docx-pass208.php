<?php

/**
 * Pass 208 — name the owner of the addressable blind pointers, and re-point two that cite the wrong construct.
 *
 * Found by reading each pointer's sentence against the file it names, the only check that sees this shape:
 *
 *   §13's elective row cites `:542-558` for "refuses a proposal outside min–max", but that range is the standing
 *     override message; the band check is EvaluationController.php:603-617 (`elective_min_choices`, then
 *     `if ($count < $minChoices || $count > $maxChoices)`). Two rows carry it.
 *   §28's staff row cites `:676-682` for "also seeds staff: staff8 and one office{N}_head", but that range is
 *     curriculum creation; the staff block is DevReferenceDataSeeder.php:782-827.
 *
 * The rest only gain a file name, which is what moves them out of the blind queue: NotificationSeeder.php,
 * IDController.php, RegistrarController.php, WorkflowService.php, AccountingController.php, AssessmentController.php
 * and enrollment-form.blade.php (whose Document No. block ends at :76, one line short of the cited :77).
 *
 * Stage 1 probes every needle against document.xml and writes only the ones that are contiguous; the rest are
 * printed so a later pass can take them through the cell or para kind. A pass that wrote nothing while printing a
 * clean table is the failure this guards against.
 *
 *   php storage/app/scratch/refresh-docx-pass208.php          # probe + dry run
 *   php storage/app/scratch/refresh-docx-pass208.php --apply  # write what proved out
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

// ------------------------------------------------------------------ 1. the code claims behind each number
$code = [
    'eval' => 'app/Http/Controllers/Evaluation/EvaluationController.php',
    'devseed' => 'database/seeders/DevReferenceDataSeeder.php',
    'notif' => 'database/seeders/NotificationSeeder.php',
    'id' => 'app/Http/Controllers/ID/IDController.php',
    'reg' => 'app/Http/Controllers/Registrar/RegistrarController.php',
    'wf' => 'app/Services/WorkflowService.php',
    'acct' => 'app/Http/Controllers/Accounting/AccountingController.php',
    'assess' => 'app/Http/Controllers/Assessment/AssessmentController.php',
    'block' => 'app/Http/Controllers/Blocking/BlockingController.php',
    'form' => 'resources/views/prints/enrollment-form.blade.php',
];
$L = [];
foreach ($code as $k => $rel) {
    $L[$k] = (array) file($root.'/'.$rel);
}
$claims = [
    // the NEW targets, each asserted to carry the claim the sentence makes
    ['eval', 603, 'if (! $groupName)'],
    ['eval', 606, 'elective_min_choices'],
    ['eval', 612, '$count < $minChoices || $count > $maxChoices'],
    ['eval', 617, '}'],
    ['devseed', 782, 'Staff (staff8 = admin'],
    ['devseed', 810, 'office{$officeId}_head'],
    ['devseed', 827, '}'],
    ['notif', 28, "'data' => ["],
    ['notif', 39, "'message' => 'A student has completed"],
    ['notif', 43, '],'],
    ['id', 195, 'public function attachPhoto('],
    ['id', 214, "'cardPhotoPath' =>"],
    ['reg', 293, "'returnReason' => 'required"],
    ['reg', 308, "'returnReason' => \$validated"],
    ['wf', 208, 'public function signStep('],
    ['acct', 188, "min:0.01"],
    ['acct', 172, 'public function record('],
    ['assess', 129, '$enrolledUnits = $enrollment->enrolledSubjects()'],
    ['assess', 157, '$totalScholarshipCoverage = 0;'],
    ['assess', 160, '$remainingBalance = $totalAssessed'],
    ['assess', 232, '$coverageAmount = $scholarshipType->coverageType'],
    ['block', 483, 'Block capacity enforcement'],
    ['block', 495, 'Room capacity check'],
    ['form', 71, '@isset($documentNumber)'],
    ['form', 76, '@endisset'],
    // what the document cites TODAY for the two wrong targets — proves the drift, not just the fix
    ['eval', 542, '$derived = $this->standingService->derive'],
    ['devseed', 677, "'majorId' => null"],
];
$fails = 0;
foreach ($claims as [$k, $n, $needle]) {
    $t = trim($L[$k][$n - 1] ?? '');
    $ok = str_contains($t, $needle);
    printf("  %s %-8s :%-4d [%s]\n", $ok ? 'ok  ' : 'BAD ', $k, $n, mb_substr($t, 0, 58));
    $fails += $ok ? 0 : 1;
}
if ($fails > 0) {
    printf("\nREFUSED: %d claimed line(s) do not carry the claim.\n", $fails);
    exit(1);
}

// ------------------------------------------------------------------ 2. probe the needles
$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$candidates = [
    ['outside min–max (:542-558)', 'outside min–max ( EvaluationController.php:603-617 )', 2],
    ['(:676-682, office8_head', '( DevReferenceDataSeeder.php:782-827, office8_head', 1],
    ['NotificationSeeder passes a real array', 'NotificationSeeder.php passes a real array', 1],
    ['and sets cardPhotoPath ( :195-225 )', 'and sets cardPhotoPath ( IDController.php:195-225 )', 1],
    ['mandatory returnReason (:288-312', 'mandatory returnReason ( RegistrarController.php:288-312', 1],
    ['signStep() and its two gates ( :208', 'signStep() and its two gates ( WorkflowService.php:208', 1],
    ['cannot be ₱0 ( :188 , :172-232 )', 'cannot be ₱0 ( AccountingController.php:188 , :172-232 )', 2],
    ['cites at :129-141. A grant reduces the bill at :232-237',
        'cites at AssessmentController.php:129-141. A grant reduces the bill at :232-237', 1],
    ['the result is stored at :160-168; at compute time :157',
        'the result is stored at AssessmentController.php:160-168; at compute time :157', 1],
    [':71-77 is the Document No. field', 'enrollment-form.blade.php:71-76 is the Document No. field', 1],
    ['alongside the block total (:495-507, :483-493)',
        'alongside the block total ( BlockingController.php:495-507, :483-493 )', 2],
];

$usable = [];
$unusable = [];
foreach ($candidates as [$from, $to, $want]) {
    $n = substr_count($xml, $from);
    printf("  count %d (want %d) — %s\n", $n, $want, mb_substr($from, 0, 56));
    if ($n === $want) {
        $usable[] = [$from, $to, null, $want];
    } else {
        $unusable[] = $from;
    }
}
if ($usable === []) {
    echo "\nREFUSED: no needle is addressable on these bytes; this pass would report success while writing nothing.\n";
    exit(1);
}
if ($unusable !== []) {
    printf("\n%d needle(s) are not contiguous and are LEFT FOR A LATER PASS (cell or para kind):\n", count($unusable));
    foreach ($unusable as $u) {
        echo '  - '.mb_substr($u, 0, 76)."\n";
    }
    echo "\n";
}

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass208.docx', $usable, [], $apply);
