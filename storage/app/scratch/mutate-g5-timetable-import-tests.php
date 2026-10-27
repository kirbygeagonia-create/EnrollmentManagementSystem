<?php

/**
 * Proves `tests/Feature/Console/TimetableImportTest.php` and the Blocking desk's own tests can fail.
 *
 * G-5 (prompt §7.9) rests on promises a green suite alone cannot keep: that a preview writes nothing,
 * that one bad row refuses the whole sheet even under `--commit`, that a collision inside the file is
 * named on both rows, that the half-open boundary answers alike when the column holds `09:30` and when
 * it holds `09:30:00`. Each leg plants the defect that would break one of those and requires the case
 * that owns it to report `1 failed`.
 *
 * Leg 22 is the other direction: the desk's own collision tests must die when the controller stops
 * asking the shared rule, which is what proves the importer and the screen have one definition rather
 * than two that agree today.
 *
 * A leg whose needle does not match exactly once reports BAD rather than passing, a mutation that leaves
 * the file byte-identical is refused before the run, and every touched file is restored by hash whether
 * the run succeeds, fails, or dies.
 *
 * Usage: php storage/app/scratch/mutate-g5-timetable-import-tests.php [--only=leg[,leg]] [--list]
 */

$root = dirname(__DIR__, 3);
chdir($root);

$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$command = 'ems:import:timetable';
$import = 'app/Console/Commands/ImportTimetable.php';
$rule = 'app/Support/TimetableConflicts.php';
$desk = 'app/Http/Controllers/Blocking/BlockingController.php';
$sheetTest = 'tests/Feature/Console/TimetableImportTest.php';
$deskTest = 'tests/Feature/Blocking/BlockingControllerTest.php';

foreach (array_slice($argv, 1) as $arg) {
    if (! in_array($arg, ['--list'], true) && ! str_starts_with($arg, '--only=')) {
        fwrite(STDERR, "REFUSED: this harness reads only --only=<leg numbers> and --list; got {$arg}\n");

        exit(1);
    }
}

// [label, file, from, to, test file, method whose case must go red]
$legs = [
    ['the half-open boundary is widened to touch', $rule,
        'return self::toSeconds($aStart) < self::toSeconds($bEnd)
            && self::toSeconds($bStart) < self::toSeconds($aEnd);',
        'return self::toSeconds($aStart) <= self::toSeconds($bEnd)
            && self::toSeconds($bStart) <= self::toSeconds($aEnd);',
        $sheetTest, 'the_shared_overlap_rule_names_the_pairing_and_holds_the_half_open_boundary'],
    ['the database comparison is no longer padded', $rule,
        '$q->where(\'startTime\', \'<\', self::toSeconds($meeting->endTime))
                        ->where(\'endTime\', \'>\', self::toSeconds($meeting->startTime));',
        '$q->where(\'startTime\', \'<\', $meeting->endTime)
                        ->where(\'endTime\', \'>\', $meeting->startTime);',
        $sheetTest, 'the_database_half_of_the_same_rule_reads_the_stored_shape_without_inventing_a_collision'],
    ['the collision query stops scoping to the term', $rule,
        '$termId = $schedule->block?->termId;',
        '$termId = null; // MUTANT: an unscoped query reads a retired term as a collision',
        $sheetTest, 'a_meeting_in_another_term_does_not_refuse_the_term_being_built'],
    ['the block branch is dropped', $rule,
        "if (\$shared('blockId', \$schedule->blockId)) {",
        "if (false && \$shared('blockId', \$schedule->blockId)) { // MUTANT",
        $sheetTest, 'committing_a_sheet_and_running_it_again_refuses_every_row'],
    ['the sheet is never read against itself', $import,
        'foreach (TimetableConflicts::withinSheet($asMeetings) as $conflict) {',
        'foreach ([] as $conflict) { // MUTANT: file-internal collisions are invisible',
        $sheetTest, 'the_sheet_itself_cannot_double_book_a_room_and_both_rows_are_named'],
    ['a refused sheet may still be committed', $import,
        'if ($refused > 0) {',
        'if (false) { // MUTANT',
        $sheetTest, 'one_malformed_row_refuses_the_whole_sheet_even_under_commit'],
    ['a preview publishes anyway', $import,
        'if (! $this->option(\'commit\')) {',
        'if (false) { // MUTANT',
        $sheetTest, 'a_clean_sheet_previews_and_writes_nothing_without_commit'],
    ['a clock is stored exactly as it was typed', $import,
        "return sprintf('%02d:%02d', (int) \$m[1], (int) \$m[2]);",
        'return trim($value); // MUTANT: 8:00 stays un-padded and orders wrong',
        $sheetTest, 'commit_publishes_one_schedule_carrying_both_meetings'],
    ['a day is matched by prefix', $import,
        'if (strtolower($value) === strtolower($case->value)) {',
        'if (strlen(trim($value)) > 1) { // MUTANT: any abbreviation is accepted',
        $sheetTest, 'one_malformed_row_refuses_the_whole_sheet_even_under_commit'],
    ['the field-count guard is gone', $import,
        'if (count($row) !== $width) {',
        'if (false) { // MUTANT',
        $sheetTest, 'a_row_with_the_wrong_field_count_is_refused_naming_both_numbers'],
    ['an end before its start is allowed', $import,
        'if ($start !== null && $end !== null && ! ($start < $end)) {',
        'if (false) { // MUTANT',
        $sheetTest, 'a_clock_that_cannot_be_ordered_or_an_end_before_its_start_is_refused'],
    ['a finalized timetable accepts rows', $import,
        "if (\$blockId !== null && \$lookups['blockById'][\$blockId]->scheduleStatus === 'final') {",
        'if (false) { // MUTANT',
        $sheetTest, 'a_finalized_block_refuses_its_rows_because_the_desk_would'],
    ['an ambiguous block name picks one', $import,
        'if (count($matches) > 1) {',
        'if (false) { // MUTANT',
        $sheetTest, 'an_ambiguous_or_unknown_name_is_refused_with_the_candidates_it_saw'],
    ['a duplicate staff name resolves to the first', $import,
        'if (count($names) > 1) {',
        'if (false) { // MUTANT',
        $sheetTest, 'an_ambiguous_or_unknown_name_is_refused_with_the_candidates_it_saw'],
    ['an unknown column in the header is ignored', $import,
        "if (\$name !== '' && ! isset(self::COLUMNS[\$this->canonical(\$name)])) {",
        'if (false) { // MUTANT',
        $sheetTest, 'a_missing_or_forged_header_is_refused_before_any_row_is_read'],
    ['a missing required column is ignored', $import,
        'if (! isset($given[strtolower($name)])) {',
        'if (false) { // MUTANT',
        $sheetTest, 'a_missing_or_forged_header_is_refused_before_any_row_is_read'],
    ['--limit is accepted and inert', $import,
        'if ($max !== null && count($rows) >= $max + 1) {',
        'if (false) { // MUTANT',
        $sheetTest, 'limit_says_what_was_read_rather_than_what_the_file_holds'],
    ['rows that share a schedule are split apart', $import,
        '$key = "{$line[\'blockId\']}|{$line[\'subjectId\']}|{$line[\'instructorId\']}|{$line[\'roomId\']}";',
        '$key = "{$line[\'blockId\']}|{$line[\'subjectId\']}|{$line[\'instructorId\']}|{$line[\'roomId\']}|{$line[\'row\']}"; // MUTANT',
        $sheetTest, 'commit_publishes_one_schedule_carrying_both_meetings'],
    ['the sheet may be run without naming a term', $import,
        "if (\$given === '') {",
        'if (false) { // MUTANT',
        $sheetTest, 'the_term_is_required_and_an_unknown_id_prints_the_terms_on_file'],
    ['a too-small room is never warned about', $import,
        'if ($block === null || $room === null || $block->maxStudents <= $room->capacity) {',
        'if (true) { // MUTANT',
        $sheetTest, 'a_room_too_small_for_the_block_warns_without_refusing_the_row'],
    ['a header-only sheet is read as clean', $import,
        'if (count($rows) < 2) {',
        'if (false) { // MUTANT',
        $sheetTest, 'an_empty_sheet_and_an_unreadable_path_are_refused_rather_than_reported_clean'],
    ['the desk stops asking the shared rule', $desk,
        'return TimetableConflicts::againstDatabase($schedule, $exceptScheduleId);',
        'return collect(); // MUTANT: the screen publishes whatever it is given',
        $deskTest, 'test_conflict_prevents_schedule_creation'],
];

$only = null;

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--only=')) {
        $only = array_map('intval', explode(',', substr($arg, 7)));
    }
}

if (in_array('--list', $argv, true)) {
    foreach ($legs as $i => $leg) {
        printf("%2d  %-52s %s\n", $i + 1, $leg[0], $leg[1]);
    }

    exit(0);
}

$files = [];
$bad = 0;
$ran = 0;

$restore = function () use (&$files, $root): void {
    foreach ($files as $path => $bytes) {
        file_put_contents($root.'/'.$path, $bytes);
    }

    $files = [];
};

register_shutdown_function($restore);

$run = function (string $file, string $method) use ($root, $php): array {
    $cmd = sprintf('%s artisan test %s --filter=%s 2>&1', escapeshellarg($php), escapeshellarg($file), escapeshellarg($method));
    $out = shell_exec($cmd) ?? '';
    $clean = preg_replace('/\e\[[0-9;]*m/', '', $out) ?? '';

    return [$clean, str_contains($clean, 'Tests:') ? 'measured' : 'unreadable'];
};

$guard = function (string $path, string $needle, string $label) use ($root): ?string {
    $bytes = file_get_contents($root.'/'.$path);

    if ($bytes === false) {
        fwrite(STDERR, "REFUSED [{$label}]: cannot read {$path}\n");

        return null;
    }

    $hits = substr_count($bytes, $needle);

    if ($hits !== 1) {
        fwrite(STDERR, "REFUSED [{$label}]: the needle occurs {$hits} time(s) in {$path}, and a mutation must land on exactly one place\n");

        return null;
    }

    return $bytes;
};

echo "== green control: every case the harness relies on must pass before anything is planted\n";

$controls = [
    [$sheetTest, 'the_shared_overlap_rule_names_the_pairing_and_holds_the_half_open_boundary'],
    [$deskTest, 'test_conflict_prevents_schedule_creation'],
];

foreach ($controls as [$file, $method]) {
    [$out, $state] = $run($file, $method);

    if ($state !== 'measured' || ! str_contains($out, '1 passed')) {
        fwrite(STDERR, "BAD: control {$method} is not green — nothing is being measured\n");
        exit(1);
    }

    echo "ok   control {$method}\n";
}

foreach ($legs as $i => [$label, $file, $from, $to, $testFile, $method]) {
    if ($only !== null && ! in_array($i + 1, $only, true)) {
        continue;
    }

    if ($from === $to) {
        fwrite(STDERR, "REFUSED [{$label}]: the needle and its replacement are the same text\n");
        $bad++;

        continue;
    }

    $bytes = $guard($file, $from, $label);

    if ($bytes === null) {
        $bad++;

        continue;
    }

    $files[$file] ??= $bytes;
    $mutated = str_replace($from, $to, $bytes);

    if ($mutated === $bytes) {
        fwrite(STDERR, "REFUSED [{$label}]: the mutation changed no byte of {$file}\n");
        $bad++;

        continue;
    }

    file_put_contents($root.'/'.$file, $mutated);

    if (file_get_contents($root.'/'.$file) === $bytes) {
        fwrite(STDERR, "REFUSED [{$label}]: the planted file is byte-identical to the clean one\n");
        $bad++;

        continue;
    }

    [$out, $state] = $run($testFile, $method);

    file_put_contents($root.'/'.$file, $bytes);
    $files[$file] = $bytes;

    $ran++;

    if ($state !== 'measured') {
        fwrite(STDERR, "BAD  [{$label}]: the run printed no `Tests:` line, so it measured nothing\n");
        $bad++;

        continue;
    }

    if (! str_contains($out, '1 failed')) {
        $tail = trim(preg_replace('/\s+/', ' ', $out) ?? '');
        fwrite(STDERR, "BAD  [{$label}]: {$method} did not report `1 failed` — the defect survived\n        ".substr($tail, 0, 400)."\n");
        $bad++;

        continue;
    }

    printf("ok   %-52s %s reports 1 failed\n", $label, $method);
}

echo "\n== control again, with every file restored\n";

foreach ($controls as [$file, $method]) {
    [$out, $state] = $run($file, $method);

    if ($state !== 'measured' || ! str_contains($out, '1 passed')) {
        fwrite(STDERR, "BAD: the closing control is not green — a restore did not land\n");
        exit(1);
    }

    echo "ok   control-after {$method}\n";
}

printf("\nLegs run: %d of %d; refused or bad: %d\n", $ran, count($legs), $bad);

if ($bad > 0) {
    exit(1);
}

echo "All {$ran} legs passed: each promise the timetable importer makes is guarded by a test that bites.\n";
