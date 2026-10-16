<?php

/**
 * Mutate the two rules added on 2026-10-12 and require the intended test to fail.
 *
 * The pristine source is held in memory from the first read and restored from that copy in a
 * finally, so a mutant left on disk can never be re-read as the original and then certified green.
 * Each swap asserts its own match count before writing: a mutation that silently does not apply
 * produces a PASS, which reads exactly like a vacuous checker.
 */

$target = 'app/Console/Commands/AuditRecordStates.php';
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';

$pristine = file_get_contents($target);
if ($pristine === false) {
    exit("cannot read {$target}\n");
}

$noteTest = 'a_box_signed_without_the_record_behind_it_is_noted_and_leaves_the_run_green';
$cleanTest = 'a_record_the_desks_produced_reports_every_rule_clean';

$mutants = [
    ['the Clinic rule never fires', "Enrollments::whereHas('enrollmentworkflow.workflowsteps', fn (\$q) => \$q\n            ->where('officeId', OfficeId::Clinic->value)", "Enrollments::whereRaw('1 = 0')->whereHas('enrollmentworkflow.workflowsteps', fn (\$q) => \$q\n            ->where('officeId', OfficeId::Clinic->value)", $noteTest],
    ['the ID rule never fires', "Enrollments::whereHas('enrollmentworkflow.workflowsteps', fn (\$q) => \$q\n            ->where('officeId', OfficeId::IdOffice->value)", "Enrollments::whereRaw('1 = 0')->whereHas('enrollmentworkflow.workflowsteps', fn (\$q) => \$q\n            ->where('officeId', OfficeId::IdOffice->value)", $noteTest],
    ['both notes promoted to impossible', "'severity' => 'decision',\n                'why' => 'Recording the assessment", "'severity' => 'impossible',\n                'why' => 'Recording the assessment", $noteTest],
    ['the summary lies about its own count', "count(\$findings).'; each is listed", "99.'; each is listed", $cleanTest],
];

$results = [];

foreach ($mutants as [$label, $from, $to, $test]) {
    $count = substr_count($pristine, $from);
    if ($count !== 1) {
        $results[] = sprintf("  %-46s NOT APPLIED (%dx found) — untested rule\n", $label, $count);

        continue;
    }

    try {
        file_put_contents($target, str_replace($from, $to, $pristine));

        // Prove the mutant is the file on disk before trusting anything the run says.
        $landed = str_contains(file_get_contents($target), $to);

        $out = [];
        $code = 0;
        exec(sprintf('%s artisan test --filter=%s 2>&1', escapeshellarg($php), escapeshellarg($test)), $out, $code);
        $joined = implode("\n", $out);

        $verdict = ! $landed
            ? 'MUTANT DID NOT LAND'
            : ($code !== 0 ? 'caught (test failed as it should)' : 'SURVIVED — the checker is decoration');

        $results[] = sprintf("  %-46s %s\n", $label, $verdict);

        if ($code === 0) {
            $tail = preg_split('/\n/', $joined);
            $results[] = '      '.trim(end($tail) ?: '')."\n";
        }
    } finally {
        file_put_contents($target, $pristine);
    }
}

echo "mutants run against ems:audit:record-states\n";
echo implode('', $results);
echo "\nrestored: ".(file_get_contents($target) === $pristine ? "pristine file verified byte-for-byte\n" : "!!! RESTORE FAILED !!!\n");
