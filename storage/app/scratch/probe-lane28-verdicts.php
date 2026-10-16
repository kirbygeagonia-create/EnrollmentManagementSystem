<?php

/**
 * Where does each claimed method actually live? Pair this with the construct probe's candidate list: a
 * citation is a real defect only when the declaration sits outside the range the document points at, and a
 * false alarm when the sentence names two methods and the probe picked the other one.
 */

$root = dirname(__DIR__, 3);

$want = [
    ['app/Services/EnrollmentStateMachine.php', 'transition', 'EnrollmentStateMachine.php:62-76'],
    ['app/Services/EnrollmentStateMachine.php', 'transition', 'EnrollmentStateMachine.php:62-69'],
    ['app/Services/EnrollmentStateMachine.php', 'transition', 'EnrollmentStateMachine.php:56'],
    ['app/Services/WorkflowService.php', 'signStep', 'WorkflowService.php:126-131'],
    ['app/Services/WorkflowService.php', 'stepsFor', 'WorkflowService.php:31-39'],
    ['app/Services/WorkflowService.php', 'stepsFor', 'WorkflowService.php:49-80'],
    ['app/Models/Clearanceperiods.php', 'accepting', 'app/Models/Clearanceperiods.php:42'],
    ['app/Http/Middleware/HandleInertiaRequests.php', 'share', 'HandleInertiaRequests.php:94-100'],
    ['app/Services/EnrollmentIssuer.php', 'issue', 'app/Services/EnrollmentIssuer.php:73'],
    ['app/Http/Controllers/Evaluation/ShiftRequestController.php', 'store', 'ShiftRequestController.php:45'],
    ['app/Services/PrintService.php', 'generatePdf', 'PrintService.php:23'],
];

foreach ($want as [$rel, $method, $cite]) {
    $path = $root.'/'.$rel;
    if (! is_file($path)) {
        printf("%-56s MISSING FILE for %s\n", $rel, $cite);

        continue;
    }
    $lines = file($path);
    $at = [];
    foreach ($lines as $i => $line) {
        if (preg_match('/function\s+'.preg_quote($method, '/').'\s*\(/', $line)) {
            $at[] = $i + 1;
        }
    }

    // The call sites inside the same file, because "X() inside the service (File.php:76)" cites a *call*.
    $calls = [];
    foreach ($lines as $i => $line) {
        if (preg_match('/(->|::|\s)'.preg_quote($method, '/').'\s*\(/', $line) && ! in_array($i + 1, $at, true)) {
            $calls[] = $i + 1;
        }
    }

    [, $range] = explode(':', $cite, 2);
    $nums = array_map('intval', explode('-', $range));
    $from = $nums[0];
    $to = $nums[1] ?? $nums[0];

    $declIn = array_values(array_filter($at, fn ($n) => $n >= $from && $n <= $to));
    $callIn = array_values(array_filter($calls, fn ($n) => $n >= $from && $n <= $to));

    printf("%-52s %-14s declares %-14s first call %-16s cite %-38s %s\n",
        basename($rel), $method.'()',
        implode(',', $at) ?: 'not found',
        implode(',', array_slice($calls, 0, 3)) ?: '—',
        $cite,
        $declIn !== [] ? 'HOLDS the declaration'
            : ($callIn !== [] ? 'HOLDS a call' : 'holds neither'));
}
