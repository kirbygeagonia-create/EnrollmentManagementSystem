<?php
/**
 * Does the cited span actually contain the thing the sentence says it does?
 * The range probe only proves a citation is inside the file; the controller files have grown
 * by hundreds of lines as rulings were built, so an in-range cite can still name the wrong
 * method. Prints, for each citation: the sentence around it, and the cited lines themselves.
 *
 * usage: php probe-docx-cite-context.php [File.php]
 */
$want = $argv[1] ?? 'EvaluationController.php';
$flat = file(__DIR__.'/docx-flat.txt');

$paths = [
    'EvaluationController.php' => 'app/Http/Controllers/Evaluation/EvaluationController.php',
    'RegistrarController.php' => 'app/Http/Controllers/Registrar/RegistrarController.php',
    'ClearanceController.php' => 'app/Http/Controllers/Clearance/ClearanceController.php',
    'AccountingController.php' => 'app/Http/Controllers/Accounting/AccountingController.php',
    'BlockingController.php' => 'app/Http/Controllers/Blocking/BlockingController.php',
    'AdmissionController.php' => 'app/Http/Controllers/Admission/AdmissionController.php',
    'ReferenceDataController.php' => 'app/Http/Controllers/Admin/ReferenceDataController.php',
    'AssessmentController.php' => 'app/Http/Controllers/Assessment/AssessmentController.php',
    'ClearancePolicy.php' => 'app/Policies/ClearancePolicy.php',
    'RegistrarPolicy.php' => 'app/Policies/RegistrarPolicy.php',
    'PaymentPolicy.php' => 'app/Policies/PaymentPolicy.php',
    'EnrollmentPolicy.php' => 'app/Policies/EnrollmentPolicy.php',
    'WorkflowService.php' => 'app/Services/WorkflowService.php',
    'PrintService.php' => 'app/Services/PrintService.php',
    'EnrollmentIssuer.php' => 'app/Services/EnrollmentIssuer.php',
    'EnrollmentStateMachine.php' => 'app/Services/EnrollmentStateMachine.php',
];
if (! isset($paths[$want]) || ! is_file($paths[$want])) {
    exit("no path for {$want}\n");
}
$lines = file($paths[$want]);
$total = count($lines);

$seen = [];
foreach ($flat as $entry) {
    if (! preg_match('/^(ROW|P) (\d+): (.*)$/s', $entry, $m)) {
        continue;
    }
    [$all, $kind, $at, $text] = $m;
    // Citation shape: File.php:NN or File.php:NN-MM. Delimiter is # because paths carry /.
    $quoted = preg_quote($want, "#");
    $pat = "#" . $quoted . ":([0-9]+)(?:-([0-9]+))?" . "#";
    if (! preg_match_all($pat, $text, $c)) {
        continue;
    }
    foreach ($c[1] as $i => $lo) {
        $hi = $c[2][$i] !== '' ? (int) $c[2][$i] : (int) $lo;
        $key = $at.'/'.$lo.'-'.$hi;
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        // the sentence that carries the cite: 70 chars before, 30 after
        $pos = strpos($text, $want.':'.$lo);
        $before = $pos === false ? '' : mb_substr(preg_replace('/\s+/', ' ', $text), max(0, $pos - 150), 150);
        $after = $pos === false ? '' : mb_substr(preg_replace('/\s+/', ' ', $text), $pos, 90);
        $span = '';
        for ($n = $lo; $n <= min($hi, $total); $n++) {
            $span .= '  '.str_pad((string) $n, 4, ' ', STR_PAD_LEFT).'| '.trim($lines[$n - 1])."\n";
        }
        echo str_repeat('=', 100).PHP_EOL;
        printf("%s row %s  %s:%d-%d  (%d lines)\n", $kind, $at, $want, $lo, $hi, $hi - $lo + 1);
        echo '  says: ...'.trim($before).'['.trim($after)."]\n";
        echo '  code:'.PHP_EOL.preg_replace('/^ {4}(.{0,110}).*$/m', '    $1', $span);
    }
}
