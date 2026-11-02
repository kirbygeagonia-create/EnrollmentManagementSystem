<?php

/**
 * The falsifier for `a_fault_between_the_two_writes_of_a_drop_leaves_the_load_and_the_status_exactly_as_they_were`
 * (guide problem 6's third question, and the subject-line half of the rollback pair the guide asks for).
 *
 * The case asserts a fact about a transaction, so the only honest proof is to break the transaction three ways
 * and watch that one case refuse each time. Every plant is a **defect in the pre-fix shape**, never the absence
 * of a fix — deleting the load update would prove the opposite of what the case claims.
 *
 * The plants are applied inside `drop()`'s own byte span, not by a document-wide needle: the closure signature
 * line and the subject-line update both occur twice in this controller (approve() writes the same shape), and a
 * needle that matches two methods plants the defect in the wrong one — the AMBIGUOUS refusal the docx shift tool
 * exists for, met here in a harness. Every anchor is therefore asserted to occur **exactly once inside the span**.
 *
 * One plant is deliberately NOT here, and the reason is stated rather than hidden: moving only the `dropReason`
 * update outside the transaction cannot be seen by this case, because the fault fires on the load update that
 * runs before it. A leg that cannot fail is decoration.
 *
 * Usage: php storage/app/scratch/mutate-t06-drop-atomicity-tests.php
 */
$root = dirname(__DIR__, 3);
chdir($root);

$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$file = $root.'/app/Http/Controllers/Registrar/RegistrarController.php';
$test = 'tests/Feature/Registrar/EnrollmentDropTest.php';
$case = 'a_fault_between_the_two_writes_of_a_drop_leaves_the_load_and_the_status_exactly_as_they_were';

if (! is_file($file)) {
    fwrite(STDERR, "REFUSED: the controller under test is not at {$file}.\n");
    exit(1);
}

$original = (string) file_get_contents($file);
$hash = sha1($original);

register_shutdown_function(function () use ($file, $original, $hash): void {
    if (sha1((string) file_get_contents($file)) !== $hash) {
        file_put_contents($file, $original);
        fwrite(STDERR, "note: the controller was restored by the shutdown handler ({$hash}).\n");
    }
});

// ------------------------------------------------------------ locate the act
$spanStart = strpos($original, '    public function drop(Request $request, Enrollments $enrollment): RedirectResponse');
if ($spanStart === false) {
    fwrite(STDERR, "REFUSED: this harness could not find `drop()` in the controller — its anchors would then be guesses.\n");
    exit(1);
}
$next = strpos($original, "\n    public function ", $spanStart + 10);
$spanLen = ($next === false ? strlen($original) : $next) - $spanStart;
$body = substr($original, $spanStart, $spanLen);
if (substr_count($body, 'DB::transaction(') !== 1) {
    fwrite(STDERR, 'REFUSED: the located span holds '.substr_count($body, 'DB::transaction(')
        ." `DB::transaction(` call(s), expected 1 — the span boundary is wrong, not the act.\n");
    exit(1);
}

$runCase = static function () use ($php, $test, $case): string {
    $lines = [];
    $status = 0;
    exec('"'.$php.'" artisan test "'.$test.'" --filter="'.$case.'" 2>&1', $lines, $status);

    return implode("\n", $lines);
};

$bad = 0;
$legs = [];
$report = static function (string $label, bool $pass, string $detail = '') use (&$bad, &$legs): void {
    $legs[] = $label;
    echo str_pad($label, 44).' '.($pass ? 'ok' : 'BAD').' '.$detail."\n";
    if (! $pass) {
        $bad++;
    }
};

/** Apply [from, to] pairs to the drop() span only; refuse before writing unless each anchor is unique there. */
$plant = static function (string $label, string $needle, array $pairs) use ($original, $body, $spanStart, $spanLen, $file, $runCase, $report): void {
    $newBody = $body;
    foreach ($pairs as [$from, $to]) {
        $count = substr_count($newBody, $from);
        if ($count !== 1) {
            $report($label, false, "MUTANT DID NOT APPLY: the anchor occurs {$count} time(s) inside drop(), expected 1");

            return;
        }
        $newBody = str_replace($from, $to, $newBody);
    }
    if ($newBody === $body) {
        $report($label, false, 'the plant changed nothing');

        return;
    }
    file_put_contents($file, substr_replace($original, $newBody, $spanStart, $spanLen));
    $out = $runCase();
    file_put_contents($file, $original);
    // Both the count AND the reason: a plant that does not compile also prints `1 failed`, and that is a broken
    // harness wearing the costume of a killed defect.
    $red = str_contains($out, '1 failed') && str_contains($out, $needle);
    $report($label, $red, $red ? '' : 'expected `1 failed` naming "'.substr($needle, 0, 40).'": '
        .substr(preg_replace('/\s+/', ' ', $out), 0, 220));
};

// ------------------------------------------------------------ control
$out = $runCase();
$report('control-must-be-green', str_contains($out, '1 passed'));
if (! str_contains($out, '1 passed')) {
    fwrite(STDERR, "REFUSED: the control is not green, so no planted verdict below would mean anything.\n");
    exit(1);
}

$open = '        DB::transaction(function () use ($enrollment, $validated) {';
$close = "        });\n\n        return redirect()->route('registrar.index')";
$load = <<<'PHP'
            $enrollment->enrolledSubjects()
                ->whereIn('status', [
                    EnrolledSubjectStatus::Proposed->value,
                    EnrolledSubjectStatus::Confirmed->value,
                ])
                ->update(['status' => EnrolledSubjectStatus::Dropped->value]);
PHP;
$reason = '            $enrollment->update([\'dropReason\' => $validated[\'dropReason\']]);';

// 1. The whole act outside a transaction: the transition commits, the load update dies, and the record is left
//    dropped with its lines still confirmed — exactly the half-cancelled cancellation the case forbids.
$plant('transaction-removed', 'rolled back with the load', [
    [$open, '(function () use ($enrollment, $validated) {'],
    [$close, "        })();\n\n        return redirect()->route('registrar.index')"],
]);

// 2. Only the load leaves the transaction, so the status and its history row commit before the fault fires.
$plant('load-update-moved-outside', 'rolled back with the load', [
    [$load."\n\n".$reason, $reason],
    [$close, "        });\n\n".$load."\n\n        return redirect()->route('registrar.index')"],
]);

// 3. A controller that catches its own transaction's throw reports success for a cancellation that never happened.
$plant('fault-swallowed-by-the-controller', 'dies quietly', [
    [$open, '        try { '.$open],
    [$close, "        }); } catch (\\Throwable) {}\n\n        return redirect()->route('registrar.index')"],
]);

// ------------------------------------------------------------ restore proof, then the control again
$restored = sha1((string) file_get_contents($file)) === $hash;
$report('tree-restored-by-hash', $restored);
$out = $runCase();
$report('control-green-after', str_contains($out, '1 passed'));

if (! $restored || ! str_contains($out, '1 passed')) {
    fwrite(STDERR, "REFUSED: the tree or the case is damaged after the plants — read that as this harness's own failure.\n");
    exit(1);
}

echo "\n".($bad > 0 ? "{$bad} BAD" : 'ALL KILLED').' over '.count($legs)." leg(s).\n";
exit($bad > 0 ? 1 : 0);
