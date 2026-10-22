<?php

/**
 * Lane 62 / P-7 — proof over the two tests that pin the letterhead.
 *
 * P-7 sat open for twenty days because nothing could see a blank letterhead line: the value
 * reaches config from a table row, the template coalesces to an empty string, and the paper
 * prints an empty row with no error anywhere. So the guard that closes it is two assertions —
 * the keys the six print views read, and the values SettingsSeeder hands them — and an
 * assertion that has only ever been watched pass has not been proved.
 *
 * Each case plants exactly the defect its name says into a working-tree file, runs the ONE
 * test that should refuse it, and restores the bytes in a finally. Refuses rather than
 * reporting a pass when: a needle matches nothing (a mutant that cannot apply cannot fail),
 * the artisan run prints no `Tests:` line (a broken instrument is not a result), or the tree
 * is dirty in one of the two files it is about to mutate (someone else's work would be
 * reverted by the restore).
 *
 * Usage: php storage/app/scratch/mutate-lane62-letterhead-tests.php
 */

$root = dirname(__DIR__, 3);
chdir($root);

$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$class = 'tests/Feature/Print/PrintTemplateSelfContainmentTest.php';
$seeder = 'database/seeders/SettingsSeeder.php';
$slip = 'resources/views/prints/clearance-slip.blade.php';

$KEYS = 'test_the_print_views_read_only_settings_keys_the_seeder_supplies';
$VALUES = 'test_the_letterhead_settings_are_seeded_from_the_school_sheet';

$cases = [
    ['blank address row', $seeder, $VALUES,
        "'value' => 'National Highway, Purok 7, Crossing Rubber, Tupi, South Cotabato, Philippines, 9505',",
        "'value' => '',"],
    ['invented address', $seeder, $VALUES,
        "'value' => 'National Highway, Purok 7, Crossing Rubber, Tupi, South Cotabato, Philippines, 9505',",
        "'value' => 'Some Campus The Repository Made Up',"],
    ['phone key renamed away', $seeder, $VALUES,
        "'schoolPhone' => [", "'schoolTelephone' => ["],
    ['template reads a key nobody seeds', $slip, $KEYS,
        "<div class=\"school-address\">{{ config('settings.schoolAddress', '') }}</div>",
        "<div class=\"school-address\">{{ config('settings.schoolAddress', '') }}</div>\n        <div>{{ config('settings.schoolMotto', '') }}</div>"],
];

$fails = 0;
$touched = [];

$run = function (string $method) use ($php, $class, $root): array {
    chdir($root);
    $cmd = escapeshellarg($php).' artisan test '.escapeshellarg($class).' --filter '.escapeshellarg($method).' 2>&1';
    $out = preg_replace('/\e\[[0-9;]*m/', '', (string) shell_exec($cmd));
    if (! preg_match('/Tests:\s+([^\n]*)/u', $out, $m)) {
        // lane 51's rule: no tally means the command never ran, and an unrun test is neither
        // green nor red — reporting it as either is how a harness sleeps through its own cases.
        return ['unknown', $out];
    }

    return [str_contains($m[1], 'failed') ? 'red' : 'green', $out];
};

try {
    // No `git` check here: shell_exec cannot reach git from PHP on this box, and the harness
    // restores the exact bytes it read, so a file this batch already edited (the seeder) is
    // returned to that state rather than to HEAD's.
    echo "clean control — both tests on the unmutated tree:\n";
    foreach ([['key list', $KEYS], ['seeded values', $VALUES]] as [$label, $method]) {
        [$verdict, $out] = $run($method);
        printf("  %-14s %s\n", $label, $verdict);
        if ($verdict !== 'green') {
            echo substr($out, -900)."\n";
            $fails++;
        }
    }
    if ($fails > 0) {
        echo "\nREFUSED: the control is not green, so a red mutant would prove nothing.\n";
        exit(1);
    }

    foreach ($cases as [$label, $file, $method, $from, $to]) {
        $bytes = (string) file_get_contents($root.'/'.$file);
        $n = substr_count($bytes, $from);
        printf("\ncase: %s\n", $label);
        if ($n !== 1) {
            printf("  BAD  the mutant's anchor matched %d times in %s, not 1\n", $n, $file);
            $fails++;

            continue;
        }
        if (! isset($touched[$file])) {
            $touched[$file] = $bytes;
        }
        file_put_contents($root.'/'.$file, str_replace($from, $to, $bytes));

        [$verdict, $out] = $run($method);
        printf("  %-4s the named test after planting it\n", 'red' === $verdict ? 'ok  ' : 'FAIL');
        if ($verdict !== 'red') {
            printf("       %s\n", preg_match('/Tests:.*$/mu', $out, $mm) ? trim($mm[0]) : substr(trim($out), -300));
            $fails++;
        } elseif (preg_match('/Failed asserting|longer seeds|is blank|which SEAIT|the letterhead keys are three/u', $out)) {
            echo "       it refused with its own words, not with a crash\n";
        } else {
            printf("  FAIL it went red without naming the defect\n");
            $fails++;
        }

        file_put_contents($root.'/'.$file, $bytes);
        clearstatcache(true, $root.'/'.$file);
    }
} finally {
    foreach ($touched as $file => $bytes) {
        $now = (string) file_get_contents($root.'/'.$file);
        if ($now !== $bytes) {
            file_put_contents($root.'/'.$file, $bytes);
            printf("restored %s after an aborted run\n", $file);
        } else {
            printf("verified %s is byte-for-byte as this harness found it\n", $file);
        }
    }
}

if ($fails > 0) {
    printf("\n%d case(s) did not behave. A mutant that survives is an assertion that does not check anything.\n", $fails);
    exit(1);
}
echo "\nAll lane 62 letterhead cases behaved.\n";
