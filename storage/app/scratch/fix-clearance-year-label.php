<?php

/**
 * The clearance pages spell an academic year from two fields no model has.
 *
 * `Academicyears` holds `yearLabel`, `startDate` and `endDate` (measured in its `$fillable`, its casts and the
 * migration this file reads), and there is no `getYearStartAttribute` or `$appends` anywhere in `app/`. Six sites
 * in two clearance pages nevertheless build a label as `${academicYear.yearStart}-${academicYear.yearEnd}`, so each
 * renders `undefined-undefined` on the live desk: the clearance queue's Academic Term column, its period dropdown,
 * the period card in the detail modal, and three more on the periods screen. Nothing could see it — PHPStan does not
 * read JSX, there is no JavaScript suite, and no test asserts a rendered string.
 *
 * `yearLabel` already carries the hyphenated span ("2025-2026" on live `ems`), so the fix is one field per site and
 * the hyphen disappears with them. `AcademicYearLabelSurfaceTest` is the guard that keeps the class from coming back.
 *
 * Usage: php storage/app/scratch/fix-clearance-year-label.php [--apply]
 */
$root = dirname(__DIR__, 3);
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

$files = [
    $root.'/resources/js/Pages/Clearance/Index.jsx' => [
        ['label: `${p.term?.academicYear?.yearStart}-${p.term?.academicYear?.yearEnd} ${p.term?.semester} (${p.periodStatus})`,',
            'label: `${p.term?.academicYear?.yearLabel} ${p.term?.semester} (${p.periodStatus})`,'],
        ['return ay ? `${ay.yearStart}-${ay.yearEnd} ${cp.term?.semester}` : \'—\';',
            'return ay?.yearLabel ? `${ay.yearLabel} ${cp.term?.semester}` : \'—\';'],
        ['{p.term?.academicYear?.yearStart}-{p.term?.academicYear?.yearEnd} {p.term?.semester} (Open)',
            '{p.term?.academicYear?.yearLabel} {p.term?.semester} (Open)'],
        ['? `${cp.term.academicYear.yearStart}-${cp.term.academicYear.yearEnd} ${cp.term?.semester}`',
            '? `${cp.term.academicYear.yearLabel} ${cp.term?.semester}`'],
    ],
    $root.'/resources/js/Pages/Clearance/Periods.jsx' => [
        ['label: `${p.term?.academicYear?.yearStart}-${p.term?.academicYear?.yearEnd} ${p.term?.semester}`,',
            'label: `${p.term?.academicYear?.yearLabel} ${p.term?.semester}`,'],
        ['return ay ? `${ay.yearStart}-${ay.yearEnd} ${p.term?.semester}` : \'—\';',
            'return ay?.yearLabel ? `${ay.yearLabel} ${p.term?.semester}` : \'—\';'],
    ],
];

// Refuse unless every needle matches its file exactly once, before anything is written: a partial application is
// the half-moved state this repository keeps refusing.
$plans = [];
foreach ($files as $file => $edits) {
    $bytes = (string) file_get_contents($file);
    foreach ($edits as $i => [$from, $to]) {
        $hits = substr_count($bytes, $from);
        if ($hits !== 1) {
            fwrite(STDERR, sprintf("REFUSED: %s edit %d needle occurs %d time(s), want 1: [%s]\n", basename($file), $i, $hits, $from));

            exit(1);
        }
        $bytes = str_replace($from, $to, $bytes);
    }
    if (str_contains($bytes, 'yearStart') || str_contains($bytes, 'yearEnd')) {
        fwrite(STDERR, sprintf("REFUSED: %s still reads a field no model declares.\n", basename($file)));

        exit(1);
    }
    $plans[$file] = $bytes;
}

printf("%s: %d site(s) re-spelled from yearLabel\n", $apply ? 'APPLY' : 'DRY RUN', array_sum(array_map('count', $files)));

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

foreach ($plans as $file => $bytes) {
    $backup = $file.'.pre-year-label-'.date('Ymd-His');
    copy($file, $backup);
    file_put_contents($file, $bytes);
    if ((string) file_get_contents($file) !== $bytes) {
        copy($backup, $file);
        fwrite(STDERR, 'REFUSED: '.basename($file).' did not read back as written; restored from '.basename($backup).".\n");

        exit(1);
    }
}

echo "APPLIED: every clearance year label now reads the field the model holds.\n";
