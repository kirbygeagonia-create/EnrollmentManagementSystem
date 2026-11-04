<?php

/**
 * Falsifier for the timetable import SCREEN (not the command — that has its own harness).
 *
 * Each leg plants the defect the screen's promise exists to refuse, re-runs the case that owns that promise, and
 * requires it to print `1 failed`. A plant that cannot compile, cannot land, or cannot be seen by the reader is
 * reported BAD rather than passed. Files are restored by hash between legs, because a harness whose planted defect
 * poisons the next leg reports its own age as a surviving guard.
 *
 * Usage: php storage/app/scratch/mutate-g5-import-screen-tests.php
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

$controller = $root.'/app/Http/Controllers/Blocking/TimetableImportController.php';
$testFile = 'tests/Feature/Blocking/TimetableImportScreenTest.php';
$original = (string) file_get_contents($controller);
$originalHash = sha1($original);

$plain = static fn (string $out): string => (string) preg_replace('/\e\[[0-9;]*m/', '', $out);
$tally = static function (string $out) use ($plain): ?int {
    return preg_match('/Tests:\s+(\d+) passed/', $plain($out), $m) === 1 ? (int) $m[1] : null;
};

$runCase = static function (string $method) use ($root, $php, $testFile): array {
    $filter = $method === '' ? '' : ' --filter='.$method;
    exec('cd /d '.$root.' && "'.$php.'" artisan test '.$testFile.$filter.' 2>&1', $out, $status);

    return [$status, implode("\n", $out)];
};

$restore = static function () use ($controller, $original, $originalHash, $php): void {
    file_put_contents($controller, $original);
    exec('"'.$php.'" -l '.escapeshellarg($controller).' 2>&1', $lint, $st);
    if ($st !== 0) {
        fwrite(STDERR, "The restore did not produce a parseable controller:\n".implode("\n", $lint)."\n");
        exit(1);
    }
    if (sha1((string) file_get_contents($controller)) !== $originalHash) {
        fwrite(STDERR, "The tree is not the batch's own bytes after the restore.\n");
        exit(1);
    }
};

$legs = [
    [
        'name' => 'preview-commits',
        'case' => 'the_preview_reports_the_rows_and_writes_nothing',
        'from' => '$preview = $this->run($key, (int) $validated[\'termId\'], commit: false, fillGaps: false);',
        'to' => '$preview = $this->run($key, (int) $validated[\'termId\'], commit: true, fillGaps: true);',
        'why' => 'a preview that writes is not a preview',
    ],
    [
        'name' => 'sheet-not-spent-after-publish',
        'case' => 'publishing_writes_the_rows_and_spends_the_sheet_so_a_second_click_writes_nothing',
        'from' => "        foreach ([\$key.'.csv', \$key.'.json'] as \$gone) {\n            Storage::disk(self::DISK)->delete(self::DIR.'/'.\$gone);\n        }",
        'to' => "        // plant: the sheet survives the act, so a second click can publish it again",
        'why' => 'a double submit must not be able to write a second week',
    ],
    [
        'name' => 'any-file-accepted',
        'case' => 'a_photograph_of_a_timetable_is_refused_because_only_a_sheet_is_read',
        'from' => "        if (! in_array(\$extension, ['csv', 'txt'], true)) {",
        'to' => '        if (false) {',
        'why' => 'the ruling named a spreadsheet or a text sheet, so an image is refused rather than read',
    ],
    [
        'name' => 'sheet-on-the-public-disk',
        'case' => 'the_sheet_waits_on_the_private_disk_and_never_on_a_served_folder',
        'from' => "    private const DISK = 'local';",
        'to' => "    private const DISK = 'public';",
        'why' => 'a department roster of hours on a served folder is the disclosure the 2026-10-31 ruling closed',
    ],
    [
        'name' => 'publish-does-not-ask',
        'case' => 'a_desk_without_the_import_right_is_refused_at_both_doors',
        // A nowdoc, because a double-quoted needle interpolates `$request` and `$this` away and the plant then
        // lands nowhere (the documented trap, met again in a new file).
        'from' => <<<'PHP'
    public function publish(Request $request): RedirectResponse
    {
        $this->authorize('blocking.importSheet');
PHP,
        'to' => <<<'PHP'
    public function publish(Request $request): RedirectResponse
    {
        // plant: the door is open
PHP,
        'why' => 'one door guarded and the other unguarded is the half-applied authorization this file keeps refusing',
    ],
];

$bad = 0;

[, $controlOut] = $runCase('');
$cases = $tally($controlOut);
if ($cases === null || str_contains($plain($controlOut), 'failed')) {
    echo "CONTROL                                       BAD   the class is not green before any plant\n"
        .wordwrap(substr($controlOut, -500), 96, "\n  ", true)."\n";
    exit(1);
}
echo 'CONTROL                                       ok    '.$cases." cases green before any plant (count read, not remembered)\n";

foreach ($legs as $leg) {
    $count = substr_count($original, $leg['from']);
    if ($count !== 1) {
        echo str_pad($leg['name'], 42)."BAD   needle matches {$count} time(s), expected exactly 1 — the harness has aged\n";
        $bad++;

        continue;
    }

    file_put_contents($controller, str_replace($leg['from'], $leg['to'], $original));
    exec('"'.$php.'" -l '.escapeshellarg($controller).' 2>&1', $lint, $lintStatus);
    if ($lintStatus !== 0) {
        echo str_pad($leg['name'], 42)."BAD   the plant does not parse: ".end($lint)."\n";
        $restore();
        $bad++;

        continue;
    }

    [, $out] = $runCase($leg['case']);
    $restore();

    if (str_contains($out, 'Parse error') || str_contains($out, 'Class "') || str_contains($out, 'No tests found') || str_contains($out, 'not found')) {
        echo str_pad($leg['name'], 42)."BAD   the run did not execute the case, so `1 failed` would be a story\n"
            .wordwrap(substr($out, -260), 96, "\n  ", true)."\n";
        $bad++;

        continue;
    }

    if (str_contains($out, '1 failed')) {
        echo str_pad($leg['name'], 42)."ok    killed its own case ({$leg['why']})\n";

        continue;
    }

    echo str_pad($leg['name'], 42)."BAD   the case stayed green over the planted defect\n"
        .wordwrap(substr($out, -320), 96, "\n  ", true)."\n";
    $bad++;
}

[, $finalOut] = $runCase('');
if ($tally($finalOut) !== $cases || str_contains($plain($finalOut), 'failed')) {
    echo "CONTROL AFTER                             BAD   the class is not green after the restores\n";
    $bad++;
} else {
    echo 'CONTROL AFTER                             ok    '.$cases." cases green again, controller restored by hash\n";
}

printf("\n%s: %d leg(s), %d bad\n", $bad === 0 ? 'ALL KILLED' : 'REFUSED', count($legs) + 2, $bad);
exit($bad === 0 ? 0 : 1);
