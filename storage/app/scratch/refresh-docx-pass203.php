<?php

/**
 * Pass 203 — four more blind pointers, and one sentence the build overtook.
 *
 * Same method as pass 199: take each unattributed number, open the file its own sentence names as the owner, and
 * refuse unless the line carries the claim. Four of the five numbers in §25's P-11 paragraph had drifted without
 * anyone seeing it, because each landed on a live line of the right file — they were simply no longer the lines
 * that print the status:
 *
 *   "the desk page prints overallStatus in its footer ( :47 )"   -> PrintSlip.jsx:53   (:47 is the DOCUMENT
 *      NUMBER row, and the file was never named, so no sweep opened it)
 *   "and again in the card ( :93 )"                              -> :99   (:93 is a closing `</Card>`)
 *   "whose badge renders a third at :101-102"                    -> :107-108   (:101 is the received-on line)
 *   "the PDF reads it too ( :61-62 )"                            -> clearance-slip.blade.php:61-62, true, named
 *   "prints HELD — NOT YET CLEARED unless … ( :112-116 )"        -> :120-123   (:112 is the Registrar signature
 *      line; the `$cleared = in_array(…approved, waived…)` test and its echo are :120-123)
 *
 * The second is a different kind of wrong. §12 still says "Gate 4 treats *no open clearance period* as satisfied
 * (:116-119). Deliberate, but it means a Registrar who has not opened the period silently clears everyone." That
 * was the build before ruling 4 — the method's own docblock says so: "Ruling 4 closes the silence that used to
 * pass this gate … the fewer windows existed the more approvals went through." The check now refuses when no
 * window is accepting (EnrollmentReadiness.php:61-63), so the sentence describes a state this repository
 * retired, in the present tense, in the section a desk reads before approving anyone.
 *
 * Also re-taken: the clinic row's "(:112-141)" for a record corrected and reopened (update() begins at :126 —
 * :112 is a blank line), and the §22 matrix cell that calls the type-keyed clearance gate "( :170-190 )" while
 * the neighbouring row cites `RegistrarController.php:170-190` for something else — the only gate keyed on
 * student type is `clearanceVerdict()`'s first branch at EnrollmentReadiness.php:55-57.
 *
 *   php storage/app/scratch/refresh-docx-pass203.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass203.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$files = [
    'slip-jsx' => 'resources/js/Pages/Clearance/PrintSlip.jsx',
    'slip' => 'resources/views/prints/clearance-slip.blade.php',
    'readiness' => 'app/Support/EnrollmentReadiness.php',
    'clinic' => 'app/Http/Controllers/Clinic/ClinicController.php',
];
$L = [];
foreach ($files as $k => $rel) {
    $L[$k] = (array) file($root.'/'.$rel);
}
$claims = [
    ['slip-jsx', 47, 'documentNumber'],            // what the document WRONGLY cites
    ['slip-jsx', 53, 'Clearance Status'],
    ['slip-jsx', 93, '</Card>'],                  // what it wrongly cites
    ['slip-jsx', 99, 'Status:'],
    ['slip-jsx', 101, 'Received on'],              // what it wrongly cites
    ['slip-jsx', 107, 'Badge'],
    ['slip-jsx', 108, 'overallStatus'],
    ['slip', 61, 'Overall Status'],
    ['slip', 62, 'overallStatus'],
    ['slip', 112, 'signature-line'],               // what it wrongly cites
    ['slip', 120, "'approved', 'waived'"],
    ['slip', 123, 'HELD — NOT YET CLEARED'],
    ['readiness', 55, 'StudentType::Continuing'],
    ['readiness', 56, "'passed' => true"],
    ['readiness', 61, 'if (! $window)'],
    ['readiness', 62, 'No clearance window is accepting'],
    ['clinic', 112, ''],                           // blank: the old pointer could not be seen as wrong
    ['clinic', 126, 'public function update('],
    ['clinic', 141, '$clinic->update('],
];
$fails = 0;
foreach ($claims as [$k, $n, $needle]) {
    $t = trim($L[$k][$n - 1] ?? '');
    $ok = $needle === '' ? $t === '' : str_contains($t, $needle);
    printf("  %s %-10s :%-4d [%s]%s\n", $ok ? 'ok  ' : 'BAD ', $k, $n, mb_substr($t, 0, 58),
        $needle === '' ? '  ← blank line' : '');
    if (! $ok) {
        $fails++;
    }
}
if ($fails > 0) {
    printf("\nREFUSED: %d line(s) do not carry the claim.\n", $fails);
    exit(1);
}

$paraEdits = [
    // The anchor is a phrase inside the paragraph's LAST run — the whole tail after the second citation is one
    // run, while the earlier "… in its footer ( :47 )" is split across five, so only this phrase is contiguous.
    ['whose badge renders a third',
        'Clearance state. The desk page prints overallStatus in its footer ( PrintSlip.jsx:53 ) and again in the '
        .'card ( :99 ), whose badge renders a third at :107-108; the PDF reads it too ( clearance-slip.blade.php:61-62 ) '
        .'and, since 2026-10-01, prints HELD — NOT YET CLEARED unless the status is approved or waived ( :120-123 ), '
        .'so a pending slip no longer looks cleared on paper (P-11 closed).'],
    ['Gate 4 treats',
        'Gate 4 refuses when no window is accepting ( EnrollmentReadiness.php:61-63 ). Ruling 4 closed the '
        .'silence that used to pass it: with no accepting clearance period the check returned true, so the fewer '
        .'periods a Registrar opened, the more continuing students were approved with no office having cleared them.'],
];

$textEdits = [
    [':112-141', 'ClinicController.php:126-141'],
];

$cellEdits = [
    ['the only gate keyed on type', 'Yes — the only gate keyed on type',
        'Yes — the only gate keyed on type ( EnrollmentReadiness.php:55-57 ).'],
];

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass203.docx', $textEdits, $cellEdits, $apply, $paraEdits);
