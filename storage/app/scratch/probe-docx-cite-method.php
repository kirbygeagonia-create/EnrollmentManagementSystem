<?php
// Compact: for each citation of one file, print the enclosing method of the cited line and
// the sentence that cites it. A citation whose method has nothing to do with the sentence is
// the drift; the range probe cannot see that because the file only grew longer.
$want = $argv[1] ?? 'EvaluationController.php';
$paths = [
    'EvaluationController.php' => 'app/Http/Controllers/Evaluation/EvaluationController.php',
    'RegistrarController.php' => 'app/Http/Controllers/Registrar/RegistrarController.php',
    'ClearanceController.php' => 'app/Http/Controllers/Clearance/ClearanceController.php',
    'AccountingController.php' => 'app/Http/Controllers/Accounting/AccountingController.php',
    'BlockingController.php' => 'app/Http/Controllers/Blocking/BlockingController.php',
    'AdmissionController.php' => 'app/Http/Controllers/Admission/AdmissionController.php',
    'ReferenceDataController.php' => 'app/Http/Controllers/Admin/ReferenceDataController.php',
    'AssessmentController.php' => 'app/Http/Controllers/Assessment/AssessmentController.php',
    'ExamPolicy.php' => 'app/Policies/ExamPolicy.php',
    'AssessmentPolicy.php' => 'app/Policies/AssessmentPolicy.php',
    'IDPolicy.php' => 'app/Policies/IDPolicy.php',
    'ClinicPolicy.php' => 'app/Policies/ClinicPolicy.php',
    'BlockingPolicy.php' => 'app/Policies/BlockingPolicy.php',
    'EvaluationPolicy.php' => 'app/Policies/EvaluationPolicy.php',
    'StudentPolicy.php' => 'app/Policies/StudentPolicy.php',
    'ShiftRequestPolicy.php' => 'app/Policies/ShiftRequestPolicy.php',
    'AuthServiceProvider.php' => 'app/Providers/AuthServiceProvider.php',
    'EventServiceProvider.php' => 'app/Providers/EventServiceProvider.php',
    'HandleInertiaRequests.php' => 'app/Http/Middleware/HandleInertiaRequests.php',
    'NotificationController.php' => 'app/Http/Controllers/NotificationController.php',
    'ExamController.php' => 'app/Http/Controllers/Exam/ExamController.php',
    'IDController.php' => 'app/Http/Controllers/ID/IDController.php',
    'ClinicController.php' => 'app/Http/Controllers/Clinic/ClinicController.php',
    'ShiftRequestController.php' => 'app/Http/Controllers/Evaluation/ShiftRequestController.php',
    'UserManagementController.php' => 'app/Http/Controllers/Admin/UserManagementController.php',
    'DashboardController.php' => 'app/Http/Controllers/DashboardController.php',
    'StudentController.php' => 'app/Http/Controllers/StudentController.php',
    'EnrollmentReadiness.php' => 'app/Support/EnrollmentReadiness.php',
    'WorkflowInbox.php' => 'app/Support/WorkflowInbox.php',
    'Feetypes.php' => 'app/Models/Feetypes.php',
    'Studentassessments.php' => 'app/Models/Studentassessments.php',
    'Enrollments.php' => 'app/Models/Enrollments.php',
    'Clearanceperiods.php' => 'app/Models/Clearanceperiods.php',
    'Payments.php' => 'app/Models/Payments.php',
    'ClearancePolicy.php' => 'app/Policies/ClearancePolicy.php',
    'RegistrarPolicy.php' => 'app/Policies/RegistrarPolicy.php',
    'PaymentPolicy.php' => 'app/Policies/PaymentPolicy.php',
    'StudentPolicy.php' => 'app/Policies/StudentPolicy.php',
    'EnrollmentPolicy.php' => 'app/Policies/EnrollmentPolicy.php',
    'AdmissionPolicy.php' => 'app/Policies/AdmissionPolicy.php',
    'EvaluationPolicy.php' => 'app/Policies/EvaluationPolicy.php',
    'WorkflowService.php' => 'app/Services/WorkflowService.php',
    'PrintService.php' => 'app/Services/PrintService.php',
    'EnrollmentIssuer.php' => 'app/Services/EnrollmentIssuer.php',
    'EnrollmentStateMachine.php' => 'app/Services/EnrollmentStateMachine.php',
];
if (! isset($paths[$want]) || ! is_file($paths[$want])) {
    exit("no path for {$want}\n");
}
$src = file($paths[$want]);
$methods = [];
foreach ($src as $n => $line) {
    if (preg_match('/(public|protected|private)\s+(static\s+)?function\s+(\w+)/', $line, $m)) {
        $methods[] = ['at' => $n + 1, 'name' => $m[3]];
    }
}
$enclosing = function (int $at) use ($methods) {
    $name = '(top of file)';
    foreach ($methods as $m) {
        if ($m['at'] <= $at) {
            $name = $m['name'];
        }
    }

    return $name;
};

$quoted = preg_quote($want, '#');
$pat = '#'.$quoted.':([0-9]+)(?:-([0-9]+))?#';
$rows = file(__DIR__.'/docx-flat.txt');
$seen = [];
foreach ($rows as $entry) {
    if (! preg_match('/^(ROW|P) (\d+): (.*)$/s', $entry, $m)) {
        continue;
    }
    [$all, $kind, $at, $text] = $m;
    if (! preg_match_all($pat, $text, $c, PREG_OFFSET_CAPTURE)) {
        continue;
    }
    foreach ($c[1] as $i => $lo) {
        $lo = (int) $lo[0];
        $hi = isset($c[2][$i][0]) && $c[2][$i][0] !== '' ? (int) $c[2][$i][0] : $lo;
        $key = $kind.' '.$at.' '.$lo.'-'.$hi;
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $pos = $c[0][$i][1];
        $says = mb_substr(preg_replace('/\s+/', ' ', $text), max(0, $pos - 110), 110);
        printf("%s %-5s %-12s %-22s %5d-%-5d | %s\n", $kind, $at, $lo.'-'.$hi, $enclosing($lo).'/'.($hi !== $lo ? $enclosing($hi) : ''), $lo, $hi, $says);
    }
}
