<?php

/**
 * Falsifier for the intake requirement catalogue's tone vocabulary (§28.3 G-39's class, reached from `appliesTo`).
 *
 * Each leg plants one way the pairing could break and requires the case that OWNS that promise to print `1 failed`
 * naming its own assertion. The leg that matters most is the first: renaming the export inside the shared module
 * leaves every page's import text untouched, so a guard that only reads the pages cannot see the far end move —
 * that is the defect the account-tone guard already learned the hard way, and it is why case 1 asserts the module
 * AND the barrel.
 *
 * Usage: php storage/app/scratch/mutate-applies-to-tone-tests.php
 */
$root = dirname(__DIR__, 3);
chdir($root);
$php = PHP_BINARY;

$module = 'resources/js/Components/ui/statusLabel.js';
$barrel = 'resources/js/Components/ui/index.js';
$page = 'resources/js/Pages/Admin/ReferenceData/AdmissionRequirements.jsx';
$other = 'resources/js/Pages/Admin/ReferenceData/FeeTypes.jsx';
$testFile = 'tests/Feature/Admission/AdmissionRequirementToneVocabularyTest.php';

// Every file any leg writes MUST be in the restore set. The first cut left FeeTypes.jsx — the second-page plant's
// own target — out of it, so that mutant survived the run while $restored() reported the tree clean, because it only
// hashed the three files it knew about. A harness that restores what it remembers is a harness that leaves defects.
$targets = [$module, $barrel, $page, $other];
$originals = [];
foreach ($targets as $f) {
    if (! is_file($f)) {
        fwrite(STDERR, "REFUSED: {$f} is not in the tree.\n");
        exit(1);
    }
    $originals[$f] = (string) file_get_contents($f);
}
$hashes = array_map('md5', $originals);

$planted = [];
$cleanup = function () use (&$planted, &$originals) {
    foreach ($planted as $f) {
        if (is_file($f) && ! isset($originals[$f])) {
            unlink($f);
        }
    }
    foreach ($originals as $f => $body) {
        file_put_contents($f, $body);
    }
    $planted = [];
};
register_shutdown_function($cleanup);

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
    &$bad, &$legs, $run, $cleanup, $restored, $originals
): void {
    $legs++;
    $body = (string) file_get_contents($file);
    $hits = substr_count($body, $from);
    if ($hits !== 1) {
        echo str_pad($name, 42)."BAD   needle occurs {$hits} time(s), wanted 1 — the leg's age, not a defect\n";
        $bad++;

        return;
    }
    $mutated = str_replace($from, $to, $body);
    if ($mutated === $body) {
        echo str_pad($name, 42)."BAD   the plant is a no-op\n";
        $bad++;

        return;
    }
    if (file_put_contents($file, $mutated) === false) {
        echo str_pad($name, 42)."BAD   the plant could not be written\n";
        $bad++;

        return;
    }
    [$code, $out] = $run($filter);
    $cleanup();

    if (str_contains($out, 'No tests found')) {
        echo str_pad($name, 42)."BAD   the filter selected no test\n";
        $bad++;
    } elseif ($code === 0 || ! str_contains($out, '1 failed')) {
        echo str_pad($name, 42)."SURVIVED — the promise is real but the case stayed green\n";
        $bad++;
    } elseif (! str_contains($out, $needle)) {
        echo str_pad($name, 42)."BAD   failed, but not naming [{$needle}]\n";
        $bad++;
    } elseif (! $restored()) {
        $bad++;
    } else {
        echo str_pad($name, 42)."ok    killed — {$needle}\n";
    }
};

$control = static function (string $name, string $filter) use (&$bad, &$legs, $run): void {
    $legs++;
    [$code, $out] = $run($filter);
    if ($code !== 0 || ! str_contains($out, '1 passed')) {
        echo str_pad($name, 42).'BAD   control not green: '.str_replace("\n", ' / ', mb_substr($out, -200))."\n";
        $bad++;

        return;
    }
    echo str_pad($name, 42)."ok    control green\n";
};

$control('control-module-and-barrel', 'the_module_and_its_barrel_both_answer_the_one_map');
$control('control-enum-keys', 'the_map_holds_exactly_the_values_the_enum_declares');

$leg(
    'export-renamed-in-the-module',
    $module,
    'export const appliesToTone = {',
    'export const appliesToToneV2 = {',
    'the_module_and_its_barrel_both_answer_the_one_map',
    'stopped exporting the map'
);

$leg(
    'barrel-stops-re-exporting',
    $barrel,
    'studentTypeTone, appliesToTone,',
    'studentTypeTone,',
    'the_module_and_its_barrel_both_answer_the_one_map',
    'the barrel no longer re-exports'
);

$leg(
    'enum-key-dropped-from-the-map',
    $module,
    "    all: 'neutral',\n};",
    "};",
    'the_map_holds_exactly_the_values_the_enum_declares',
    'answer different lists'
);

$leg(
    'page-redeclares-its-own-map',
    $page,
    'const statusOptions = [',
    "const appliesToToneMap = { firstYear: 'info' };\n\nconst statusOptions = [",
    'the_page_reads_the_shared_map_and_holds_no_copy_of_its_own',
    'declared its own tone map again'
);

$leg(
    'page-stops-reading-the-shared-map',
    $page,
    'tone={appliesToTone[row.appliesTo] || \'neutral\'}',
    "tone={'neutral'}",
    'the_page_reads_the_shared_map_and_holds_no_copy_of_its_own',
    'not reading the shared map'
);

$leg(
    'a-second-page-declares-a-map',
    $other,
    'const unitBasisToneMap = {',
    "const appliesToBadgeTone = { all: 'neutral' };\nconst unitBasisToneMap = {",
    'no_second_surface_declares_an_applies_to_tone_map',
    'declared outside the shared module'
);

echo "\n=== verdict ===\n";
echo $bad === 0
    ? "ALL KILLED: {$legs} leg(s), 0 bad\n"
    : 'NOT ALL KILLED: '.$legs.' leg(s), '.$bad." bad\n";
exit($bad === 0 ? 0 : 1);
