<?php

/**
 * Lane 54c — prove the `Host time zone` assertions can fail.
 *
 * The claim this batch makes is narrow: the row reads the machine's zone from a source that can disagree with
 * the school's declaration, and refuses to guess when that source answers nothing. The first draft was built on
 * `date_default_timezone_get()`, which Laravel pins to `app.timezone`, so it could only ever print agreement —
 * a check that cannot fail. Each case plants one way back into the real command and requires the named test to
 * go red.
 *
 * One mutant per process: a reused process reported the same victim for five structurally different defects in
 * an earlier lane, and "exit non-zero" is not attribution — so each case names the test that died, read out of
 * its own log. The pristine file is held in memory so a restore can never re-read a tree already carrying a
 * mutant, and every case refuses when its anchor does not match exactly once or leaves the file byte-identical.
 * Anchors are rewritten to the file's own line endings, because this checkout is CRLF and a `from` carrying a
 * bare `\n` silently matches nothing.
 *
 * Usage: php storage/app/scratch/mutate-lane54-host-zone.php [--only=gap] [--keep]
 */

$root = dirname(__DIR__, 3);
$php = getenv('EMS_PHP') ?: 'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';
$target = $root.'/app/Console/Commands/Preflight.php';
$keep = in_array('--keep', $argv, true);
$dry = in_array('--dry', $argv, true);
$only = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--only=')) {
        $only = substr($arg, strlen('--only='));
    }
}

$pristine = @file_get_contents($target);
if ($pristine === false) {
    fwrite(STDERR, "REFUSED: cannot read {$target}\n");
    exit(1);
}
$hash0 = hash('sha256', $pristine);
$eol = str_contains($pristine, "\r\n") ? "\r\n" : "\n";

$failures = 0;
$finished = false;

$restore = function () use ($target, $pristine, $hash0, $keep, &$failures): void {
    if ($keep) {
        return;
    }
    file_put_contents($target, $pristine);
    if (hash('sha256', (string) file_get_contents($target)) !== $hash0) {
        printf("  BAD  restore did not produce the original bytes\n");
        $failures++;
    }
};

register_shutdown_function(function () use ($restore, &$finished): void {
    if (! $finished) {
        fwrite(STDERR, "\nThe run ended before restoring — restoring now.\n");
        $restore();
    }
});

$artisanTest = function (string $filter) use ($php, $root): array {
    $out = [];
    $code = 0;
    exec('cd /d '.escapeshellarg($root).' && '.escapeshellarg($php).' artisan test --filter='
        .escapeshellarg($filter).' 2>&1', $out, $code);

    return [$code, implode("\n", $out)];
};

$summaryOf = function (string $log): ?string {
    foreach (explode("\n", $log) as $line) {
        if (str_contains($line, 'Tests:')) {
            return trim($line);
        }
    }

    return null;
};

$failedOf = function (string $log): string {
    $names = [];
    foreach (explode("\n", $log) as $line) {
        if (str_contains($line, 'FAILED')) {
            $names[] = trim($line);
        }
    }

    return implode(' | ', $names);
};

/**
 * @param  list<string>  $expects  name fragments that must appear among the failures
 */
$case = function (string $name, string $method, string $from, string $to, array $expects) use (
    $target, $pristine, $eol, $artisanTest, $restore, $summaryOf, $failedOf, $only, $dry, &$failures
): void {
    if ($only !== null && ! str_contains($name, $only)) {
        return;
    }

    $from = str_replace("\n", $eol, $from);
    $to = str_replace("\n", $eol, $to);

    $hits = substr_count($pristine, $from);
    if ($hits !== 1) {
        printf("  BAD  %-22s anchor matched %d times, expected 1 — this mutant would sleep\n", $name, $hits);
        $failures++;

        return;
    }

    file_put_contents($target, str_replace($from, $to, $pristine));
    $now = (string) file_get_contents($target);
    if ($now === $pristine) {
        printf("  BAD  %-22s the mutation left the file byte-identical\n", $name);
        $restore();
        $failures++;

        return;
    }
    printf("  ..   %-22s planted: %s\n", $name, str_contains($now, $to)
        ? trim(strtok($to, "\r\n") ?: '')
        : 'THE REPLACEMENT IS NOT IN THE FILE');

    if ($dry) {
        $restore();
        printf("  ok   %-22s anchor lands once and the bytes change (no test run)\n", $name);

        return;
    }

    // A plain method-name filter, not `Class::method`: this suite is Pest-driven and the fully-qualified form
    // collects nothing ("No tests found."), which would read as a surviving mutant.
    [$code, $log] = $artisanTest($method);
    $restore();

    if (stripos($log, 'no tests found') !== false) {
        printf("  BAD  %-22s the filter collected no tests — nothing was measured\n", $name);
        $failures++;

        return;
    }

    $summary = $summaryOf($log);
    if ($summary === null || ! str_contains($log, 'PreflightTest')) {
        printf("  BAD  %-22s the run printed no Tests: line naming PreflightTest — a broken instrument, not a result\n", $name);
        $failures++;

        return;
    }

    $failed = $failedOf($log);
    $missing = array_values(array_filter($expects, fn (string $e): bool => ! str_contains($failed, $e)));

    if ($code !== 0 && $missing === []) {
        printf("  ok   %-22s red: %s\n", $name, $summary);
        foreach ($expects as $e) {
            printf("          named victim: %s\n", $e);
        }
    } else {
        printf("  FAIL %-22s expected %s to fail — got exit %d / %s\n", $name, implode(', ', $expects), $code, $summary);
        printf("          FAILED lines: %s\n", $failed === '' ? '(none)' : $failed);
        $failures++;
    }
};

echo $dry
    ? "Dry run — only the anchors are checked, no test is executed.\n"
    : "Baseline — the fixed tree must be green before any mutant is believed.\n";

if (! $dry) {
    [$code, $log] = $artisanTest('PreflightTest');
    $summary = $summaryOf($log);
    if ($code === 0 && $summary !== null && ! str_contains($summary, 'failed')) {
        printf("  ok   clean tree: %s (line endings %s)\n", $summary, $eol === "\r\n" ? 'CRLF' : 'LF');
    } else {
        printf("  BAD  the unmutated class is not green (exit %d, %s) — no mutant verdict would mean anything\n",
            $code, $summary ?? 'no Tests: line');
        exit(1);
    }
}

echo "\nMutants — each a way the check could stop measuring, planted one at a time.\n";

$case('equality-flipped',
    'a_host_that_keeps_the_schools_hour_is_an_ok_and_an_unreadable_host_is_a_warning_never_a_fault',
    '        if ($hostOffset === $schoolOffset) {',
    '        if ($hostOffset !== $schoolOffset) {',
    ['a host that keeps the schools hour']);

$case('blind-says-agreement',
    'a_host_that_keeps_the_schools_hour_is_an_ok_and_an_unreadable_host_is_a_warning_never_a_fault',
    "            return ['Host time zone', 'WARN', \$schoolOffset === null",
    "            return ['Host time zone', 'OK', \$schoolOffset === null",
    ['a host that keeps the schools hour']);

$case('gap-hard-coded',
    'the_gap_is_measured_from_the_two_offsets_so_a_half_hour_zone_and_a_changed_declaration_both_read_true',
    '                self::offsetGapHours($hostOffset, $schoolOffset),',
    "                '19',",
    ['the gap is measured from the two offsets']);

$case('answers-when-blind',
    'the_offset_is_read_from_the_database_so_a_suite_on_sqlite_cannot_answer_it',
    "        } catch (\\Throwable) {\n            return null;\n        }\n\n        \$offset = self::normaliseOffset",
    "        } catch (\\Throwable) {\n            return ['+00:00', 'date_default_timezone_get'];\n        }\n\n        \$offset = self::normaliseOffset",
    ['the offset is read from the database']);

$case('refusal-says-agreement',
    'the_host_zone_row_names_the_source_it_read_or_says_it_could_not_read_and_never_faults',
    "            return ['Host time zone', 'WARN', \$schoolOffset === null\n                ? \$school.' is not a zone PHP can resolve, so nothing here can be compared — check the clock by hand'\n                : 'this machine\\'s zone could not be read — compare its clock with '",
    "            return ['Host time zone', 'WARN', \$schoolOffset === null\n                ? \$school.' is not a zone PHP can resolve, so nothing here can be compared — check the clock by hand'\n                : 'the clocks agree — compare its clock with '",
    ['the host zone row names the source it read']);

$case('unknown-zone-guesses',
    'the_gap_is_measured_from_the_two_offsets_so_a_half_hour_zone_and_a_changed_declaration_both_read_true',
    "            \$seconds = (new DateTimeZone(\$zone))->getOffset(new DateTimeImmutable('now'));\n        } catch (\\Throwable) {\n            return null;",
    "            \$seconds = (new DateTimeZone(\$zone))->getOffset(new DateTimeImmutable('now'));\n        } catch (\\Throwable) {\n            return 0;",
    ['the gap is measured from the two offsets']);

$finished = true;

if ($failures > 0) {
    printf("\n%d case(s) did not behave. Do not report this check as proved.\n", $failures);
    exit(1);
}

echo $dry
    ? "\nEvery anchor lands once and changes the bytes — run without --dry for the verdicts.\n"
    : "\nAll planted defects were caught by the test that names them.\n";
exit(0);
