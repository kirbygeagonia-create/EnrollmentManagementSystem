<?php

/**
 * Mutant harness for the account-status tone vocabulary (§28.3 G-39's class, the account half of it).
 *
 * Four legs, each planting a defect in one of the three files the rule spans — a page, the shared module, the
 * barrel — and each requiring the case that owns the promise to print `1 failed`. The renaming and barrel legs are
 * the ones a page-only harness would miss: a map that exists but is not exported, or is exported under a name no
 * page imports, still renders correctly in every screen a person opens, because each page keeps its own copy alive.
 *
 * Usage: php storage/app/scratch/mutate-account-tone-tests.php
 */
$root = dirname(__DIR__, 3);
chdir($root);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
if (! is_file($php)) {
    fwrite(STDERR, "REFUSED: no PHP at {$php}\n");

    exit(1);
}

$test = 'tests/Feature/Admin/AccountStatusToneVocabularyTest.php';
if (! is_file($root.'/'.$test)) {
    fwrite(STDERR, "REFUSED: the guard class is missing.\n");

    exit(1);
}

$files = [
    $root.'/resources/js/Pages/Students/Index.jsx',
    $root.'/resources/js/Components/ui/statusLabel.js',
    $root.'/resources/js/Components/ui/index.js',
];

$snapshot = [];
foreach ($files as $f) {
    if (! is_file($f)) {
        fwrite(STDERR, "REFUSED: {$f} is missing.\n");

        exit(1);
    }
    $snapshot[$f] = (string) file_get_contents($f);
}

$restore = function () use ($snapshot): void {
    foreach ($snapshot as $f => $body) {
        $written = false;
        foreach ([0, 400_000, 1_200_000] as $wait) {
            if ($wait > 0) {
                usleep($wait);
            }
            $written = @file_put_contents($f, $body);
            if ($written === strlen($body)) {
                break;
            }
        }
        if ($written !== strlen($body) || (string) file_get_contents($f) !== $body) {
            fwrite(STDERR, 'REFUSED: could not restore '.basename($f).", so the tree may still hold a planted defect.\n"
                ."  Repair it with: git checkout -- ".str_replace($root.'/', '', $f)."\n");

            exit(1);
        }
    }
};
register_shutdown_function($restore);

$legs = [
    [
        'name' => 'local-account-map-re-declared',
        'file' => $files[0],
        'from' => "function formatName(row) {",
        'to' => "const statusToneMap = {\n    active: 'success',\n    inactive: 'neutral',\n};\n\nfunction formatName(row) {",
        'case' => 'no_desk_page_declares_its_own_map_of_account_status_tones',
        'promise' => 'no screen keeps its own copy of the account colours',
    ],
    [
        'name' => 'shared-map-renamed-in-its-own-module',
        'file' => $files[1],
        'from' => 'export const accountStatusTone = {',
        'to' => 'export const accountStatusColour = {',
        'case' => 'every_surface_that_paints_an_account_status_reads_the_shared_map',
        'promise' => 'the pages import the symbol that actually exists',
    ],
    [
        'name' => 'barrel-export-dropped',
        'file' => $files[2],
        'from' => 'idRequestStatusTone, accountStatusTone, registrarGateLabels',
        'to' => 'idRequestStatusTone, registrarGateLabels',
        'case' => 'the_shared_map_keeps_the_colours_the_two_screens_already_painted',
        'promise' => 'the map is reachable through the barrel the pages import from',
    ],
    [
        'name' => 'badge-painted-from-a-literal',
        'file' => $files[0],
        'from' => 'tone={accountStatusTone[row.status] || \'neutral\'}',
        'to' => 'tone={\'success\'}',
        'case' => 'every_surface_that_paints_an_account_status_reads_the_shared_map',
        'promise' => 'the colour still comes from the shared map rather than from the call site',
    ],
];

function runCase(string $php, string $test, string $case): array
{
    $cmd = escapeshellarg($php).' artisan test '.escapeshellarg($test)
        .($case === '' ? '' : ' --filter='.escapeshellarg($case)).' 2>&1';
    $out = (string) preg_replace('/\e\[[0-9;]*m/', '', (string) shell_exec($cmd));

    return [preg_match('/Tests:\s+\d+ failed/', $out) === 1, preg_match('/Tests:\s/', $out) === 1, $out];
}

echo "control: the guard must be green before anything is planted\n";
[$failed, $selected, $out] = runCase($php, $test, '');
if (! $selected || $failed) {
    fwrite(STDERR, "REFUSED: the control is not green, so no verdict below would mean anything.\n".substr($out, 0, 400)."\n");

    exit(1);
}
echo "  ok   control green\n";

$bad = 0;
foreach ($legs as $leg) {
    $body = $snapshot[$leg['file']];
    $n = substr_count($body, $leg['from']);
    if ($n !== 1) {
        printf("  BAD  %-42s needle occurs %d time(s), wanted 1 in %s — the file moved and this leg proves nothing\n",
            $leg['name'], $n, basename($leg['file']));
        $bad++;

        continue;
    }
    $mutated = str_replace($leg['from'], $leg['to'], $body);
    if ($mutated === $body) {
        printf("  BAD  %-42s MUTANT DID NOT APPLY — the replacement changed no bytes\n", $leg['name']);
        $bad++;

        continue;
    }
    $written = @file_put_contents($leg['file'], $mutated);
    if ($written !== strlen($mutated)) {
        printf("  BAD  %-42s PLANT FAILED — the write returned %s\n", $leg['name'], var_export($written, true));
        $bad++;
        $restore();

        continue;
    }
    if ((string) file_get_contents($leg['file']) !== $mutated) {
        printf("  BAD  %-42s the planted bytes are not what the leg claims to have written\n", $leg['name']);
        $bad++;
        $restore();

        continue;
    }
    [$caseFailed, $caseSelected] = runCase($php, $test, $leg['case']);
    if (! $caseSelected) {
        printf("  BAD  %-42s --filter selected no test\n", $leg['name']);
        $bad++;
    } elseif ($caseFailed) {
        printf("  ok   %-42s killed by %s\n", $leg['name'], $leg['case']);
    } else {
        printf("  BAD  %-42s SURVIVED — %s stayed green, so nothing reads that promise\n", $leg['name'], $leg['case']);
        $bad++;
    }
    $restore();
}

foreach ($snapshot as $f => $body) {
    if ((string) file_get_contents($f) !== $body) {
        fwrite(STDERR, 'REFUSED: '.basename($f)." is not the snapshot's bytes after the run.\n");

        exit(1);
    }
}

[, $again, $after] = runCase($php, $test, '');
if ($again !== true || preg_match('/Tests:\s+\d+ failed/', $after) === 1) {
    fwrite(STDERR, "REFUSED: the trailing control is not green — this run left the tree wrong.\n");

    exit(1);
}
echo "  ok   trailing control green, all three files restored by bytes\n";

printf("%s ALL KILLED: %d leg(s), %d bad\n", $bad === 0 ? 'ok  ' : 'FAIL', count($legs), $bad);

exit($bad === 0 ? 0 : 1);
