<?php

/**
 * Pass 215 — six pointers name their owner, and two of them were pointed at the wrong thing entirely.
 *
 * Verified owners, each read back from the line before it is written:
 *   `:113-135` + `:515-517` — BlockingController.php's eligibility filter and its per-row guard
 *   `:519-520`             — the same file's "next uncompleted box must be the Blocking box" check
 *   `:76-78` + `:83-86`     — AuditLogObserver.php: the Gate::before comment and the self-service exclusion
 *
 * Wrong targets, re-pointed (the sixth and seventh this lane has found — a range that is live code in a sensible
 * file, describing something else):
 *   `:129-140` for "only when the balance reaches zero does the enrollment move to paid and the Accounting box get
 *     signed" is a docblock about covered accounts; the settlement is AccountingController.php:236-245, where
 *     `$newBalance <= 0` triggers the transition and `signStepByOffice(..., OfficeId::Accounting…)` signs the box.
 *   `:328` for "studentType copied from applicantType" is an upload record's `uploadedDate`; the copy is
 *     AdmissionController.php:411, `'studentType' => $admission->applicantType->value`.
 *
 * `:876` is left alone: it is unique in the document, but neither RegistrarController nor EvaluationController has
 * a course-mismatch refusal at that line (EvaluationController:876 is blank, its neighbourhood is the retention
 * examination blocker), so naming a file there would invent an owner.
 *
 *   php storage/app/scratch/refresh-docx-pass215.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass215.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$code = [
    'block' => 'app/Http/Controllers/Blocking/BlockingController.php',
    'obs' => 'app/Observers/AuditLogObserver.php',
    'acct' => 'app/Http/Controllers/Accounting/AccountingController.php',
    'adm' => 'app/Http/Controllers/Admission/AdmissionController.php',
];
$L = [];
foreach ($code as $k => $rel) {
    $L[$k] = (array) file($root.'/'.$rel);
}
$claims = [
    ['block', 113, '$eligibleEnrollments = Enrollments::with('],
    ['block', 134, 'OfficeId::Block'],
    ['block', 515, 'EnrollmentStatus::Enrolled'],
    ['block', 516, 'continue;'],
    ['block', 519, '$enrollment->enrollmentworkflow'],
    ['block', 520, 'workflowsteps()'],
    ['obs', 76, 'Gate::before makes'],
    ['obs', 78, 'dilute this marker into noise'],
    ['obs', 83, '$isSelfService = $user !== null'],
    ['obs', 85, '$model->getKey() === $user->getKey();'],
    // the two new targets
    ['acct', 237, 'if ($newBalance <= 0 && $enrollment)'],
    ['acct', 238, 'EnrollmentStatus::Paid'],
    ['acct', 243, 'signStepByOffice($workflow, OfficeId::Accounting'],
    ['acct', 245, '}'],
    ['adm', 411, "'studentType' => \$admission->applicantType->value"],
    // what the document cites today, asserted so the pass proves the wrong target
    ['acct', 129, 'holding can only mean the fees were covered'],
    ['adm', 328, "'uploadedDate' => now(),"],
];
$fails = 0;
foreach ($claims as [$k, $n, $needle]) {
    $t = trim($L[$k][$n - 1] ?? '');
    $ok = str_contains($t, $needle);
    printf("  %s %-6s :%-4d [%s]\n", $ok ? 'ok  ' : 'BAD ', $k, $n, mb_substr($t, 0, 58));
    $fails += $ok ? 0 : 1;
}
if ($fails > 0) {
    printf("\nREFUSED: %d claimed line(s) do not carry the claim.\n", $fails);
    exit(1);
}

$edits = [
    [':113-135', 'BlockingController.php:113-135'],
    [':519-520', 'BlockingController.php:519-520'],
    [':76-78', 'AuditLogObserver.php:76-78'],
    [':83-86', 'AuditLogObserver.php:83-86'],
    [':129-140', 'AccountingController.php:236-245'],
    [':328 )', 'AdmissionController.php:411 )'],
];

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

foreach ($edits as $i => [$from, $to]) {
    $n = substr_count($xml, $from);
    printf("  needle %-12s count %d (want 1)\n", $from, $n);
    if ($n !== 1) {
        printf("REFUSED: needle %d is seen %d time(s).\n", $i + 1, $n);
        exit(1);
    }
}

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass215.docx', $edits, [], $apply);
