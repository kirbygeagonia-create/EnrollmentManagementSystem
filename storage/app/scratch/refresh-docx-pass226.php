<?php

/**
 * Pass 226 — the sixteenth through twentieth wrong targets, all in one family: the admission gate.
 *
 * `approvalBlockers()` was extracted out of `AdmissionPolicy::approve()`, and every pointer written against the
 * old body kept its number. The old numbers all land on live lines of the same file, so the bounds sweep cleared
 * them and the meaning sweep cleared them too (`approve` and `approvalBlockers` really do sit inside the ranges
 * quoted for them). Reading the file line by line is what found this:
 *
 *   §10.2's intro  `AdmissionPolicy.php:48-66`  approve() is :48-55; :57-66 is approvalBlockers()'s own docblock.
 *                `approvalBlockers() at :67-136`  the method returns at :151 and closes at :152 — and §10.2's own
 *                  condition-4 row cites :108-149, which falls OUTSIDE the range the intro claims is the gate list.
 *   §10.2's table  `:49`   the opening brace; the permission test is :50.
 *                  `:54-56`  `$this->approvalBlockers() === []`; "still pending" is checked at :71-75.
 *                  `:58-67`  the docblock plus the signature; the BR32 verification loop is :77-89.
 *   §10.3 / §21 / §22 / §26 carry the same three stale needles:
 *                  `AdmissionController`-adjacent `:58-67` for BR32 (×3 total), `:69-81` for the general-exam gate
 *                  (that range is the blockers-array line, the pending check and the DOCUMENT query — the exam
 *                  condition is :111-126), `:48-66` for approve() again, and `:39-47` for BR7 — :39-42 is
 *                  `update()`, so BR7's own note is :44-47.
 *
 * The old lines are asserted as well, so the drift is proved rather than claimed.
 *
 *   php storage/app/scratch/refresh-docx-pass226.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass226.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$ap = (array) file($root.'/app/Policies/AdmissionPolicy.php');
$fails = 0;
foreach ([
    // what the OLD ranges actually land on — the drift, proved
    [39, 'public function update('],
    [41, "hasPermissionTo('admission.update')"],
    [49, '{'],
    [54, 'approvalBlockers($admission) === []'],
    [58, 'The readiness half of approve()'],
    [69, '$blockers = [];'],
    [78, 'studentrequirementsubmissions()'],
    // what the NEW ranges must carry
    [46, 'BR7: First-year and transferee'],
    [48, 'public function approve('],
    [50, "hasPermissionTo('admission.approve')"],
    [55, '}'],
    [67, 'public function approvalBlockers('],
    [72, 'admissionStatus !== AdmissionStatus::Pending'],
    [75, '}'],
    [77, 'BR32'],
    [84, "submissionStatus->value !== 'verified'"],
    [89, '}'],
    [111, 'requiresEntranceExam && ! $exemptFromGeneralExam'],
    [116, 'ExamType::General->value'],
    [122, "examResult->value !== 'pass'"],
    [126, '}'],
    [151, 'return $blockers;'],
    [152, '}'],
] as [$n, $needle]) {
    $t = trim($ap[$n - 1] ?? '');
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

// [needle, replacement, expected copies]
$edits = [
    ['AdmissionPolicy.php:48-66', 'AdmissionPolicy.php:48-55', 2],
    ['AdmissionPolicy.php:67-136', 'AdmissionPolicy.php:67-152', 0],   // see the bare-number probe below
    ['at :67-136', 'at :67-152', 3],
    ['AdmissionPolicy.php:49', 'AdmissionPolicy.php:50', 1],
    ['AdmissionPolicy.php:54-56', 'AdmissionPolicy.php:71-75', 1],
    ['AdmissionPolicy.php:58-67', 'AdmissionPolicy.php:77-89', 3],
    ['AdmissionPolicy.php:69-81', 'AdmissionPolicy.php:111-126', 1],
    ['AdmissionPolicy.php:39-47', 'AdmissionPolicy.php:44-47', 2],
];

$textEdits = [];
foreach ($edits as [$needle, $replacement, $want]) {
    if ($want === 0) {
        printf("\n  needle [%s] skipped: the document never spells that pointer with its file\n", $needle);

        continue;
    }
    $n = substr_count($xml, $needle);
    printf("\n  needle [%s] occurs %d time(s) in document.xml (want %d)\n", $needle, $n, $want);
    if ($n !== $want) {
        echo "REFUSED: the count is not what this pass measured, so it will not write.\n";
        exit(1);
    }
    $textEdits[] = [$needle, $replacement, null, $want];
}

// Nothing may survive this pass still pointing at the pre-refactor body.
foreach ([':67-136', 'AdmissionPolicy.php:58-67', 'AdmissionPolicy.php:48-66', 'AdmissionPolicy.php:39-47'] as $gone) {
    $left = substr_count(str_replace(array_map(fn (array $e) => $e[0], $textEdits), array_map(fn (array $e) => $e[1], $textEdits), $xml), $gone);
    printf("  after the edits, [%s] would still occur %d time(s)\n", $gone, $left);
    if ($left !== 0) {
        echo "REFUSED: a stale pointer in this family is left standing.\n";
        exit(1);
    }
}

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass226.docx', $textEdits, [], $apply);
