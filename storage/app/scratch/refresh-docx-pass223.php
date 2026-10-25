<?php

/**
 * Pass 223 — the twelfth wrong target, surfaced by the gloss checker's own reconciliation.
 *
 * `lane70-gloss-snippets.php` claimed to examine 11 glossed citations and accounted for 6: OK 6, NOT-IN-FILE 0,
 * queue 0. Five rows fell through a `continue` that counted nothing, and the printed zero looked like a clean
 * document. Giving those rows a bucket — and an invariant that refuses when the tally does not add up — turned one
 * of them into a finding:
 *
 *   "Requirements are selected by applicant type: only rows whose `appliesTo` equals the chosen `applicantType` or
 *   `all` receive a submission row ( AdmissionController.php:211-222 )."
 *
 * Lines 211-222 of that controller are the `Admissions::create([...])` array — it writes `applicantType`, and never
 * reads `appliesTo`. The selection the sentence describes is :225-238: the comment, the
 * `where('appliesTo', $validated['applicantType'])->orWhere('appliesTo','all')`, and the loop that creates each
 * `Studentrequirementsubmissions` row. The same citation sits in two places (§6's prose and the §28.x requirement
 * row), and both make the same claim, so both move.
 *
 * This is the tenth shape again from the other side: a live range, in the right file, describing something else —
 * which no bounds check can see, and which a gloss that QUOTES the column name can see, provided the tool counts
 * the rows it examined.
 *
 *   php storage/app/scratch/refresh-docx-pass223.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass223.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$ad = (array) file($root.'/app/Http/Controllers/Admission/AdmissionController.php');
$fails = 0;
foreach ([
    [$ad, 211, 'honorsCertifications'],
    [$ad, 212, 'supportingDocumentPath'],
    [$ad, 221, "'applicantType' =>"],
    [$ad, 225, 'Create requirement submissions'],
    [$ad, 226, "where('appliesTo', \$validated['applicantType'])"],
    [$ad, 227, "orWhere('appliesTo', 'all')"],
    [$ad, 231, 'Studentrequirementsubmissions::create('],
    [$ad, 238, '}'],
] as [$l, $n, $needle]) {
    $t = trim($l[$n - 1] ?? '');
    $ok = str_contains($t, $needle);
    printf("  %s :%-4d [%s]\n", $ok ? 'ok  ' : 'BAD ', $n, mb_substr($t, 0, 62));
    $fails += $ok ? 0 : 1;
}
if ($fails > 0) {
    echo "REFUSED: a claimed line does not carry the claim.\n";
    exit(1);
}

$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$needle = 'AdmissionController.php:211-222';
printf("\n  needle [%s] occurs %d time(s) in document.xml (want 2: the prose and the register row)\n", $needle, substr_count($xml, $needle));
if (substr_count($xml, $needle) !== 2) {
    echo "REFUSED: this pass expects exactly two citations to move, and will not guess at a third.\n";
    exit(1);
}

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass223.docx',
    [[$needle, 'AdmissionController.php:225-238', null, 2]], [], $apply);
