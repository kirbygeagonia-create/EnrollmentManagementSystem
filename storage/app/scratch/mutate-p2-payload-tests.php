<?php

/**
 * Proof over the three P-2 payload tests.
 *
 * P-2 stays open — the blank physical enrollment form has never been supplied — so the only half this batch can
 * close is the claim that the desk already has the data. A test written to prove that is worth nothing until it can
 * fail, and two of these three assertions are of the kind that pass when they read nothing at all:
 *
 *  - the drift check compares a list in the test against lists in two source files, so a regex that matches nothing
 *    would have to refuse, not shrug;
 *  - the query counter measures lazily-loaded relations, so if the model never lazy-loads it reports 0 forever.
 *
 * Legs: A drops a relation from the desk path, B drops the same one from the fidelity command, C removes the eager
 * load from the N+1 test itself (that one mutates the test, deliberately: it proves the counter measures something).
 * Each must turn the named case red. Bytes are restored unconditionally, and the run refuses when a needle matches
 * nothing, when the control is not green, or when artisan prints no "Tests:" line.
 *
 * Usage: php storage/app/scratch/mutate-p2-payload-tests.php
 */

$root = dirname(__DIR__, 3);
chdir($root);

$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$suite = 'tests/Feature/Print/PrintEnrollmentFormPayloadTest.php';
$service = 'app/Services/PrintService.php';
$fidelity = 'app/Console/Commands/PrintFidelitySamples.php';

$DRIFT = 'test_the_desk_loads_the_relations_the_form_would_need';
$NPLUS = 'test_reading_those_facts_issues_no_further_query';

$cases = [
    ['desk path drops term.academicYear', $service, $DRIFT,
        "                'major',\n                'term.academicYear',", "                'major',"],
    ['fidelity command drops term.academicYear', $fidelity, $DRIFT,
        // The trio `'course', 'major', 'term.academicYear',` appears in seven of the command's render
        // closures, so the needle has to name the enrollment-form line it belongs to.
        "'enrollment-form' => fn () => view('prints.enrollment-form', ['enrollment' => \$enrollment->load(['student.addresses', 'student.guardians', 'course', 'major', 'term.academicYear',",
        "'enrollment-form' => fn () => view('prints.enrollment-form', ['enrollment' => \$enrollment->load(['student.addresses', 'student.guardians', 'course', 'major',"],
    ['the N+1 counter with no eager load at all', $suite, $NPLUS,
        '$enrollment = $this->issuedEnrollment()->load(self::DESK_RELATIONS);'."\n\n        ".'DB::flushQueryLog();',
        '$enrollment = $this->issuedEnrollment()->load([]);'."\n\n        ".'DB::flushQueryLog();'],
];

$fails = 0;
$touched = [];

$run = function (string $method) use ($php, $suite, $root): array {
    chdir($root);
    $out = preg_replace('/\e\[[0-9;]*m/', '', (string) shell_exec(
        escapeshellarg($php).' artisan test '.escapeshellarg($suite).' --filter '.escapeshellarg($method).' 2>&1'
    ));
    if (! preg_match('/Tests:\s+([^\n]*)/u', $out, $m)) {
        return ['unknown', $out];
    }

    return [str_contains($m[1], 'failed') ? 'red' : 'green', $out];
};

try {
    echo "control — all three cases on the unmutated tree:\n";
    foreach ([['drift check', $DRIFT], ['identity facts', 'test_the_form_payload_already_carries_every_identity_fact_the_paper_lacks'], ['n+1 counter', $NPLUS]] as [$label, $method]) {
        [$verdict, $out] = $run($method);
        printf("  %-16s %s\n", $label, $verdict);
        if ($verdict !== 'green') {
            echo '       '.(preg_match('/Tests:.*$/mu', $out, $mm) ? trim($mm[0]) : substr(trim($out), -300))."\n";
            $fails++;
        }
    }
    if ($fails > 0) {
        echo "\nREFUSED: the control is not green, so a red mutant would prove nothing.\n";
        exit(1);
    }

    foreach ($cases as [$label, $file, $method, $from, $to]) {
        printf("\ncase: %s\n", $label);
        $bytes = (string) file_get_contents($root.'/'.$file);
        $n = substr_count($bytes, $from);
        if ($n !== 1) {
            printf("  BAD  the needle matched %d times in %s — a mutant that cannot apply cannot fail\n", $n, $file);
            $fails++;

            continue;
        }
        $touched[$root.'/'.$file] ??= $bytes;
        file_put_contents($root.'/'.$file, str_replace($from, $to, $bytes));

        [$verdict, $out] = $run($method);
        $says = preg_match('/drifted from|different relations|query\(ies\) after|no longer holds an eager-load|no longer renders/u', $out)
            ? 'it named the drift in its own message'
            : 'FAILED OPEN — red, but without saying which contract broke';
        printf("  %-4s the named test on the mutant  (%s)\n", $verdict === 'red' ? 'ok  ' : 'FAIL', $says);
        if ($verdict !== 'red') {
            printf("       %s\n", preg_match('/Tests:.*$/mu', $out, $mm) ? trim($mm[0]) : substr(trim($out), -300));
            $fails++;
        }

        file_put_contents($root.'/'.$file, $bytes);
        clearstatcache(true, $root.'/'.$file);
    }
} finally {
    foreach ($touched as $path => $bytes) {
        if ((string) file_get_contents($path) !== $bytes) {
            file_put_contents($path, $bytes);
            printf("\nrestored %s after an aborted run\n", basename($path));
        }
    }
}

if ($fails > 0) {
    printf("\n%d case(s) did not behave.\n", $fails);
    exit(1);
}
echo "\nAll P-2 payload cases behaved: green on the tree, red on the defect, each naming the contract it broke.\n";
