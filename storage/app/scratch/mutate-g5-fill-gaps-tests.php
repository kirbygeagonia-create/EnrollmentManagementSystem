<?php

/**
 * Falsifier for the timetable import's fill-gaps policy (owner ruling 2026-11-03).
 *
 * Four defects, one per promise the ruling makes. Each is planted into the live command, the named case is
 * re-run and MUST print `1 failed`, and the file is restored by hash immediately after — a harness that restores
 * only at shutdown poisons its own next leg. A plant whose needle matches nothing is reported BAD rather than
 * passed, because a leg that cannot land is the harness's age, not a guard that held. A plant that does not
 * compile is reported BAD too: `1 failed` is not a kill when the file could not be read.
 *
 * Usage: php storage/app/scratch/mutate-g5-fill-gaps-tests.php
 */
$root = dirname(__DIR__, 3);
chdir($root);

$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
if (! is_file($php)) {
    $php = trim((string) shell_exec('where php 2>nul'));
}
if ($php === '' || ! is_file($php)) {
    fwrite(STDERR, "REFUSED: no PHP binary found, so the suite cannot be run.\n");
    exit(1);
}

$target = $root.'/app/Console/Commands/ImportTimetable.php';
$testFile = 'tests/Feature/Console/TimetableImportTest.php';
$original = (string) file_get_contents($target);
$originalHash = sha1($original);

$legs = [
    [
        // The case's collision comes from the DATABASE pass (a meeting already on file), so the plant blinds
        // that pass. The within-sheet pass has its own increment and its own leg below, because a single plant
        // aimed at the wrong branch reports a correct guard as surviving.
        'name' => 'collision-never-recorded',
        'case' => 'fill_gaps_publishes_the_gap_and_skips_the_collision',
        'from' => "                    \$reasons[\$row][] = \$conflict.' — that hour is already on file for the same officer, room or block';\n                    \$this->conflictRows[(int) \$row] = (\$this->conflictRows[(int) \$row] ?? 0) + 1;",
        'to' => "                    \$reasons[\$row][] = \$conflict.' — that hour is already on file for the same officer, room or block';",
        'why' => 'an unclassified collision refuses the sheet as a whole, which is the pre-ruling behaviour',
    ],
    [
        'name' => 'within-sheet-collision-unclassified',
        'case' => 'a_sheet_that_double_books_itself_under_fill_gaps_publishes_nothing',
        'from' => "                \$reasons[\$row][] = \$conflict;\n                \$this->conflictRows[(int) \$row] = (\$this->conflictRows[(int) \$row] ?? 0) + 1;",
        'to' => '                $reasons[$row][] = $conflict;',
        'why' => 'two rows that collide with each other are invisible to a database query, so this pass is the only one that can see them',
    ],
    [
        'name' => 'unreadable-row-skipped-as-well',
        'case' => 'fill_gaps_still_refuses_the_whole_sheet_when_one_row_cannot_be_read',
        'from' => '        $blocking = array_values(array_diff($refusedRows, $skippable));',
        'to' => "        \$skippable = \$refusedRows;\n        \$blocking = [];",
        'why' => 'skipping a row the tool could not read publishes a week missing a meeting the department asked for',
    ],
    [
        'name' => 'nothing-left-reports-success',
        'case' => 'fill_gaps_refuses_a_sheet_whose_every_row_collides_rather_than_claiming_a_publish',
        'from' => '            if ($schedules === []) {',
        'to' => '            if (false) {',
        'why' => 'a re-submitted sheet that writes zero rows must not read as a publish',
    ],
    [
        'name' => 'preview-json-never-written',
        'case' => 'the_json_preview_and_the_console_tally_answer_the_same_question',
        'from' => "        \$path = (string) \$this->option('json');\n        if (\$path === '') {",
        'to' => "        \$path = '';\n        if (\$path === '') {",
        'why' => 'the desk screen reads the payload, so a command that writes nothing would show an empty preview as a clean one',
    ],
];

$runCase = static function (string $method) use ($root, $php, $testFile): array {
    $filter = $method === '' ? '' : ' --filter='.$method;
    exec('cd /d '.$root.' && "'.$php.'" artisan test '.$testFile.$filter.' 2>&1', $out, $status);

    return [$status, implode("\n", $out)];
};

// Pest writes ANSI colour codes into captured output, so the tally is read from a stripped copy: a matcher that
// meets `Tests:\e[1m 26` answers "no tests ran" about a green file (lane 73b's lesson, met in a new harness).
$plain = static fn (string $out): string => (string) preg_replace('/\e\[[0-9;]*m/', '', $out);

$tally = static function (string $out) use ($plain): ?int {
    return preg_match('/Tests:\s+(\d+) passed/', $plain($out), $m) === 1 ? (int) $m[1] : null;
};

$green = static fn (?int $want, string $out): bool => $tally($out) === $want && ! str_contains($plain($out), 'failed');

$restore = static function () use ($target, $original, $originalHash, $php): void {
    file_put_contents($target, $original);
    exec('"'.$php.'" -l '.escapeshellarg($target).' 2>&1', $lint, $st);
    if ($st !== 0) {
        fwrite(STDERR, "The restore did not produce a parseable file:\n".implode("\n", $lint)."\n");
        exit(1);
    }
    if (sha1((string) file_get_contents($target)) !== $originalHash) {
        fwrite(STDERR, "The tree is not the batch's own bytes after the restore.\n");
        exit(1);
    }
};

$bad = 0;

// The control runs green FIRST, before anything is planted, and its output is read rather than assumed.
[, $controlOut] = $runCase('');
$cases = $tally($controlOut);
if ($cases === null || str_contains($controlOut, 'failed')) {
    echo "CONTROL                                       BAD   the file is not green before any plant\n"
        .wordwrap(substr($controlOut, -400), 96, "\n  ", true)."\n";
    exit(1);
}
echo 'CONTROL                                       ok    '.$cases." cases green before any plant (the count is read, not remembered)\n";

foreach ($legs as $leg) {
    $count = substr_count($original, $leg['from']);
    if ($count !== 1) {
        echo str_pad($leg['name'], 44)."BAD   needle matches {$count} time(s), expected exactly 1 — the harness has aged, not the guard\n";
        $bad++;

        continue;
    }

    file_put_contents($target, str_replace($leg['from'], $leg['to'], $original));
    [$status, $out] = $runCase($leg['case']);
    $restore();

    if (str_contains($out, 'Parse error') || str_contains($out, 'syntax error') || str_contains($out, 'Class "')) {
        echo str_pad($leg['name'], 44)."BAD   the plant did not compile, so `1 failed` would be a story rather than a kill\n";
        $bad++;

        continue;
    }

    if (str_contains($out, '1 failed')) {
        echo str_pad($leg['name'], 44)."ok    killed its own case ({$leg['why']})\n";

        continue;
    }

    echo str_pad($leg['name'], 44)."BAD   the case stayed green over the planted defect\n"
        .wordwrap(substr($out, -320), 96, "\n  ", true)."\n";
    $bad++;
}

[, $finalOut] = $runCase('');
if (! $green($cases, $finalOut)) {
    echo "CONTROL AFTER                             BAD   the file is not green after the restores\n";
    $bad++;
} else {
    echo 'CONTROL AFTER                             ok    '.$cases." cases green again, tree restored by hash\n";
}

printf("\n%s: %d leg(s), %d bad\n", $bad === 0 ? 'ALL KILLED' : 'REFUSED', count($legs) + 2, $bad);
exit($bad === 0 ? 0 : 1);
