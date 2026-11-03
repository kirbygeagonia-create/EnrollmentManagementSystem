<?php

/**
 * Falsifier for the ListSort fallback guard (task #61).
 *
 * Two directions are proved, and the second is the one that matters: a guard that refuses EVERYTHING would satisfy
 * the two refusal cases and break all 26 wired screens, so a leg blinds the "is the asked key listed" test and
 * requires the fall-back case to go red. Each plant is restored by hash, and a leg whose needle matches nothing
 * reports BAD rather than passing.
 *
 * Usage: php storage/app/scratch/mutate-g41-listsort-guard-tests.php
 */
$root = dirname(__DIR__, 3);
chdir($root);
$php = PHP_BINARY;

$helper = 'app/Support/ListSort.php';
$controller = 'app/Http/Controllers/Admin/ReferenceDataController.php';
$testFile = 'tests/Feature/Sorting/ListSortGuardTest.php';

$targets = [$helper, $controller];
$originals = [];
foreach ($targets as $f) {
    if (! is_file($f)) {
        fwrite(STDERR, "REFUSED: {$f} is not in the tree.\n");
        exit(1);
    }
    $originals[$f] = (string) file_get_contents($f);
}
$hashes = array_map('md5', $originals);
$restore = static function () use ($originals): void {
    foreach ($originals as $f => $body) {
        file_put_contents($f, $body);
    }
};
register_shutdown_function($restore);

$bad = 0;
$legs = 0;

$run = static function (string $filter) use ($root, $php, $testFile): array {
    $out = [];
    exec('cd '.$root.' && "'.$php.'" artisan test '.$testFile.' --filter '.$filter.' 2>&1', $out, $code);

    return [(int) $code, preg_replace('/\e\[[0-9;]*m/', '', implode("\n", $out))];
};

$restored = static function () use ($targets, $hashes): bool {
    foreach ($targets as $f) {
        if (md5((string) file_get_contents($f)) !== $hashes[$f]) {
            echo '  TREE NOT RESTORED after '.basename($f)."\n";

            return false;
        }
    }

    return true;
};

$leg = static function (string $name, string $file, string $from, string $to, string $filter, string $needle) use (
    &$bad, &$legs, $run, $restore, $restored
): void {
    $legs++;
    $body = (string) file_get_contents($file);
    $hits = substr_count($body, $from);
    if ($hits !== 1) {
        echo str_pad($name, 40)."BAD   needle occurs {$hits} time(s), wanted 1 — the leg's age, not a defect\n";
        $bad++;

        return;
    }
    $planted = str_replace($from, $to, $body);
    if ($planted === $body || ! str_contains($planted, $to)) {
        echo str_pad($name, 40)."BAD   the plant left the file unchanged\n";
        $bad++;

        return;
    }
    if (file_put_contents($file, $planted) === false) {
        echo str_pad($name, 40)."BAD   the plant could not be written\n";
        $bad++;

        return;
    }
    [$code, $out] = $run($filter);
    $restore();

    if (str_contains($out, 'No tests found')) {
        echo str_pad($name, 40)."BAD   the filter selected no test\n";
        $bad++;
    } elseif ($code === 0 || ! str_contains($out, '1 failed')) {
        echo str_pad($name, 40)."SURVIVED — the case stayed green over the planted defect\n";
        $bad++;
    } elseif (! str_contains($out, $needle)) {
        echo str_pad($name, 40)."BAD   failed, but not naming [{$needle}]\n";
        $bad++;
    } elseif (! $restored()) {
        $bad++;
    } else {
        echo str_pad($name, 40)."ok    killed — {$needle}\n";
    }
};

$control = static function (string $name, string $filter) use (&$bad, &$legs, $run): void {
    $legs++;
    [$code, $out] = $run($filter);
    if ($code !== 0 || ! str_contains($out, '1 passed')) {
        echo str_pad($name, 40).'BAD   control not green: '.str_replace("\n", ' / ', mb_substr($out, -200))."\n";
        $bad++;

        return;
    }
    echo str_pad($name, 40)."ok    control green\n";
};

$fallbackNeedle = <<<'PHP'
        if (! array_key_exists($column, $allowed)) {
PHP;
$emptyNeedle = <<<'PHP'
        if ($allowed === []) {
PHP;
$askedNeedle = <<<'PHP'
        $column = array_key_exists((string) $asked, $allowed) ? (string) $asked : $fallback;
PHP;
$messageNeedle = <<<'PHP'
the keys are "
PHP;

$control('control-fallback-refused', 'a_fallback_that_names_no_key_is_refused_naming_both_the_value_and_the_keys_that_exist');
$control('control-fall-back-normal', 'a_column_the_desk_never_lists_still_falls_back_rather_than_being_refused');

$leg(
    'fallback-guard-removed',
    $helper,
    $fallbackNeedle,
    "        if (false) {\n",
    'a_fallback_that_names_no_key_is_refused_naming_both_the_value_and_the_keys_that_exist',
    'Undefined array key'
);

$leg(
    'empty-list-guard-removed',
    $helper,
    $emptyNeedle,
    "        if (false) {\n",
    'an_empty_allow_list_is_refused_rather_than_falling_back_into_nothing',
    'empty allow-list'
);

$leg(
    'guard-widened-to-refuse-unlisted-requests',
    $helper,
    $askedNeedle,
    "        \$column = array_key_exists((string) \$asked, \$allowed) ? (string) \$asked : \$fallback;\n        if (! array_key_exists((string) \$asked, \$allowed)) {\n            \$column = '__none__';\n        }",
    'a_column_the_desk_never_lists_still_falls_back_rather_than_being_refused',
    'ListSort settled on the column'
);

$leg(
    'refusal-stops-naming-the-keys',
    $helper,
    $messageNeedle,
    'the value is "',
    'a_fallback_that_names_no_key_is_refused_naming_both_the_value_and_the_keys_that_exist',
    'did not print the keys that do exist'
);

$leg(
    'screen-hands-an-empty-allow-list',
    $controller,
    "        \$sortable = [\n            'roomName' => ['column' => 'roomName'],",
    "        \$sortable = [];\n        \$sortableUnused = [\n            'roomName' => ['column' => 'roomName'],",
    'no_screen_hands_the_helper_an_empty_allow_list',
    'declares an empty allow-list'
);

$leg(
    'membership-test-duplicated-in-the-helper',
    $helper,
    $fallbackNeedle,
    $fallbackNeedle."\n            array_key_exists(\$column, \$allowed);",
    'the_refusal_lives_in_the_shared_helper_and_nowhere_else',
    'ListSort.php x2'
);

echo "\n=== verdict ===\n";
echo $bad === 0
    ? "ALL KILLED: {$legs} leg(s), 0 bad\n"
    : 'NOT ALL KILLED: '.$legs.' leg(s), '.$bad." bad\n";
exit($bad === 0 ? 0 : 1);
