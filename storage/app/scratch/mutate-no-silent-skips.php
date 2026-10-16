<?php

/**
 * Mutate ems:ci:no-silent-skips and require the intended test to fail.
 *
 * The pristine source is held in memory from the first read and restored from that copy in a finally,
 * so a mutant left on disk can never be re-read as the original and certified green. Each swap asserts
 * its own match count: a mutation that silently fails to apply produces a PASS, which is indistinguishable
 * from a vacuous checker.
 */

$target = 'app/Console/Commands/AssertNoSilentSkips.php';
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$pristine = file_get_contents($target);

$tests = [
    'browser' => 'a_browser_check_that_skipped_fails_the_run_by_name',
    'unexpected' => 'any_unexpected_skip_fails_even_when_it_is_not_a_browser',
    'renamed' => 'an_allowlisted_test_that_has_been_renamed_or_deleted_is_reported_loudly',
    'clean' => 'the_shape_ci_produces_today_is_accepted_and_each_allowed_skip_is_shown_with_its_reason',
];

$mutants = [
    ['every skip is allowed', "        \$rule = self::ALLOWED[\$class] ?? null;\n\n        if (\$rule === null) {\n            return null;\n        }", "        \$rule = self::ALLOWED[\$class] ?? null;\n\n        if (\$rule === null) {\n            return 'invented allowance';\n        }", $tests['browser']],
    ['the missing-log refusal removed', "            \$this->error(\$path.' is missing, so nothing can be said about what skipped.');\n\n            return self::FAILURE;", "            \$this->error(\$path.' is missing, so nothing can be said about what skipped.');\n\n            return self::SUCCESS;", 'a_missing_log_is_refused_rather_than_passing_for_want_of_evidence'],
    ['allowlisted absence ignored', "        \$problems = \$this->missingAllowed(\$xml);", "        \$problems = [];", $tests['renamed']],
    ['the Preflight allowlist row renamed', "'An absent print folder is not a fault because the print driver makes it'", "'An absent print folder is not a fault becuase the print driver makes it'", $tests['clean']],
    ['an unexpected skip no longer counts', "                \$unexpected++;\n", "\n", $tests['unexpected']],
];

$results = [];

foreach ($mutants as [$label, $from, $to, $test]) {
    $count = substr_count($pristine, $from);

    if ($count !== 1) {
        $results[] = sprintf("  %-34s NOT APPLIED (%dx) — untested rule\n", $label, $count);

        continue;
    }

    try {
        file_put_contents($target, str_replace($from, $to, $pristine));
        $landed = str_contains(file_get_contents($target), $to);

        $out = [];
        $code = 0;
        exec(sprintf('%s artisan test --filter=%s 2>&1', escapeshellarg($php), escapeshellarg($test)), $out, $code);

        $results[] = sprintf("  %-34s %s\n", $label, ! $landed
            ? 'MUTANT DID NOT LAND'
            : ($code !== 0 ? 'caught' : 'SURVIVED — the checker is decoration'));
    } finally {
        file_put_contents($target, $pristine);
    }
}

echo "mutants run against ems:ci:no-silent-skips\n".implode('', $results);
echo "\nrestored: ".(file_get_contents($target) === $pristine ? "pristine verified byte-for-byte\n" : "!!! RESTORE FAILED !!!\n");
