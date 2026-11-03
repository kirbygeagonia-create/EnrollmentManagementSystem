<?php

/**
 * Falsifier for the timetable sheet's two half-present facts (the slot captions and the instructor in the cell).
 *
 * Both claims were "almost" built before: the grid drew a 30-minute row for every slot but printed a caption only on
 * the hour, and the schedule row has always stored `instructorId` NOT NULL with a teacher listed in the table below,
 * while the cell itself named only the subject and the room. A test that passes on both halves of a half-done
 * mechanism proves nothing, so each leg puts the retired shape back and requires the case that owns the promise to
 * print `1 failed`.
 *
 * Usage: php storage/app/scratch/mutate-timetable-slot-label-tests.php
 */
$root = dirname(__DIR__, 3);
chdir($root);
$php = PHP_BINARY;

$page = 'resources/js/Pages/Blocking/Show.jsx';
$testFile = 'tests/Feature/Blocking/TimetableSlotLabelTest.php';

$original = (string) file_get_contents($page);
$hash = md5($original);
if ($original === '') {
    fwrite(STDERR, "REFUSED: {$page} read empty.\n");

    exit(1);
}
$restore = static function () use ($page, $original): void {
    if (file_put_contents($page, $original) === false) {
        fwrite(STDERR, "REFUSED: the restore could not write {$page}.\n");

        exit(1);
    }
};
register_shutdown_function($restore);

$bad = 0;
$legs = 0;

$runCase = static function (string $filter) use ($root, $php, $testFile): array {
    $out = [];
    exec('cd '.$root.' && "'.$php.'" artisan test '.$testFile.' --filter '.$filter.' 2>&1', $out, $code);

    return [(int) $code, preg_replace('/\e\[[0-9;]*m/', '', implode("\n", $out))];
};

$leg = static function (string $name, string $from, string $to, string $filter, string $why) use (
    &$bad, &$legs, $page, $original, $hash, $restore, $runCase
): void {
    $legs++;
    $hits = substr_count($original, $from);
    if ($hits !== 1) {
        echo str_pad($name, 42)."BAD   needle occurs {$hits} time(s), wanted 1 — the leg's age, not a defect\n";
        $bad++;

        return;
    }
    $body = str_replace($from, $to, $original);
    if ($body === $original) {
        echo str_pad($name, 42)."BAD   the plant changed no bytes\n";
        $bad++;

        return;
    }
    if (file_put_contents($page, $body) === false) {
        echo str_pad($name, 42)."BAD   the plant could not be written\n";
        $bad++;

        return;
    }

    [$code, $out] = $runCase($filter);
    $restore();

    if (str_contains($out, 'No tests found')) {
        echo str_pad($name, 42)."BAD   the filter selected no test\n";
        $bad++;
    } elseif ($code === 0 || ! str_contains($out, '1 failed')) {
        echo str_pad($name, 42).'SURVIVED — the promise is real but the case stayed green'."\n";
        $bad++;
    } elseif (md5((string) file_get_contents($page)) !== $hash) {
        echo str_pad($name, 42)."BAD   killed, but the tree is not restored\n";
        $bad++;
    } else {
        echo str_pad($name, 42)."ok    killed by the case that owns it — {$why}\n";
    }
};

$control = static function (string $name, string $filter) use (&$bad, &$legs, $runCase): void {
    $legs++;
    [$code, $out] = $runCase($filter);
    if ($code !== 0 || ! str_contains($out, '1 passed')) {
        echo str_pad($name, 42).'BAD   control is not green: '.str_replace("\n", ' / ', mb_substr($out, -200))."\n";
        $bad++;

        return;
    }
    echo str_pad($name, 42)."ok    control green\n";
};

$labelLine = "{`\${hour % 12 || 12}:\${isHourMark ? '00' : '30'} \${meridiem}`}";
$oldLabelLine = "{isHourMark ? `\${(START_HOUR + s / 2) % 12 || 12}:00 \${(START_HOUR + s / 2) >= 12 ? 'PM' : 'AM'}` : ''}";
$instructorLine = "<p className=\"text-3xs text-brand-500 leading-tight truncate\">{schedule.instructor ? `\${schedule.instructor.firstName} \${schedule.instructor.lastName}` : 'No instructor'}</p>";

$control('control-labels', 'every_slot_row_prints_its_own_clock_time_not_just_the_hour_rows');
$control('control-cell', 'the_timetable_cell_that_names_a_subject_also_names_its_instructor');

$leg(
    'half-hour-captions-removed',
    $labelLine,
    $oldLabelLine,
    'every_slot_row_prints_its_own_clock_time_not_just_the_hour_rows',
    'the grid drew the :30 rows and named only the hours, which is the report this case exists to refuse'
);

$leg(
    'instructor-dropped-from-the-cell',
    $instructorLine."\n",
    '',
    'the_timetable_cell_that_names_a_subject_also_names_its_instructor',
    'the teacher was reachable only on hover and in the table below, so the sheet read as an unassigned subject'
);

$leg(
    'hour-derived-by-row-index-again',
    'const hour = START_HOUR + Math.floor(s / 2);',
    'const hour = START_HOUR + s / 2;',
    'every_slot_row_prints_its_own_clock_time_not_just_the_hour_rows',
    'a half-hour row labelled by the un-floored index prints a time that does not exist (7.5:00), and the caption drifts off the slot it names'
);

$control('control-labels-after-plants', 'every_slot_row_prints_its_own_clock_time_not_just_the_hour_rows');
$control('control-cell-after-plants', 'the_timetable_cell_that_names_a_subject_also_names_its_instructor');

echo "\n=== verdict ===\n";
echo $bad === 0
    ? "ALL KILLED: {$legs} leg(s), 0 bad\n"
    : 'NOT ALL KILLED: '.$legs.' leg(s), '.$bad." bad\n";
exit($bad === 0 ? 0 : 1);
