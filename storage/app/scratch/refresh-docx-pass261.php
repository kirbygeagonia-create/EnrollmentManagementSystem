<?php

/**
 * The two sentences §18 owed this batch: the command inventory gained `ImportTimetable`, and the timetable
 * importer now says what it does where the other console tools are described.
 *
 * `app/Console/Commands` holds nine classes today; the document named eight and its list is a membership
 * claim, so it moved the moment G-5 added a file — the same shape as §24.17's empty-table list that lane 64
 * was built to catch, and one no checker binds (the citation sweeps see a live sentence, lane 60 binds
 * figures, lane 59 binds instrument counts). The count and the list were both re-measured off the directory
 * before being typed, and the new sentence states only what the harness proved: two conflict surfaces, a
 * refusal that writes nothing, and the name-not-id resolution.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass261.php [--apply]
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/pass261-backup-'.date('Ymd-His').'.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

$classes = array_values(array_map('basename', glob($root.'/app/Console/Commands/*.php') ?: []));

if (count($classes) !== 9 || ! in_array('ImportTimetable.php', $classes, true)) {
    fwrite(STDERR, 'REFUSED: app/Console/Commands answers '.count($classes).' classes ('.implode(', ', $classes).'), not the nine including ImportTimetable this pass states.'."\n");

    exit(1);
}

printf("read %-24s %s\n", 'command classes', implode(', ', $classes));

$importer = $root.'/app/Console/Commands/ImportTimetable.php';
$conflicts = $root.'/app/Support/TimetableConflicts.php';

foreach ([$importer, $conflicts] as $f) {
    if (! is_file($f)) {
        fwrite(STDERR, "REFUSED: {$f} is not on disk, so the sentence naming it would be false.\n");

        exit(1);
    }
}

// What the command actually does, each clause checked against the file it describes.
$src = file_get_contents($importer);

foreach ([
    '--commit' => 'the flag that lets it write at all',
    '--template' => 'the column map it can print',
    '--term' => 'the term a sheet is read against',
    "TimetableConflicts::againstDatabase" => 'the database surface',
    'TimetableConflicts::withinSheet' => 'the surface that reads a collision between two rows of the same file',
] as $needle => $why) {
    if (! str_contains($src, $needle)) {
        fwrite(STDERR, "REFUSED: ImportTimetable.php does not contain {$needle} — {$why}.\n");

        exit(1);
    }

    printf("ok   ImportTimetable.php carries %-32s (%s)\n", $needle, $why);
}

$zip = new ZipArchive;

if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the document for probing.\n");

    exit(1);
}

$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$edits = [
    // The list is written across styled runs — `BenchmarkHeavyQueries` and `CreateAdminUser` are their own
    // Consolas runs, then `ImportStudents, PrintFidelitySamples, …` continues in one plain run — so the
    // count and the name go in on needles that live inside a single run. A long needle spanning the whole
    // list is a needle that reads continuously on screen and exists nowhere in the file.
    ['holds these eight:', 'holds these nine:', null, 1],
    ['ImportStudents, PrintFidelitySamples', 'ImportStudents, ImportTimetable, PrintFidelitySamples', null, 1],
    [
        'ems:uploads:prune reports uploaded files',
        'ems:import:timetable reads a timetable sheet and resolves every name on it — the block, the subject, the instructor, the room — against the tables rather than '
            .'against an id someone typed, then asks both questions Blocking asks: does this meeting collide with one already on the database inside the block\'s own term, '
            .'and does it collide with another row of the same file? A block that would meet in two rooms at one hour is named as its own defect. Every row is answered with a '
            .'verdict and, where it was refused, one line saying why; an unresolved row, an ambiguous name, a clock that is not a zero-padded 24-hour time, a meeting that ends '
            .'before it begins, and a field count that an unquoted comma has shifted are all refusals. Nothing is written unless --commit is present AND the whole sheet resolved, '
            .'so a preview cannot half-load a term, and --template prints the column map a sheet must follow ( ImportTimetable.php , App\\Support\\TimetableConflicts.php , which '
            .'holds the half-open overlap rule once for both surfaces). Pinned by tests/Feature/Console/TimetableImportTest.php and proved by '
            .'storage/app/scratch/mutate-g5-timetable-import-tests.php. ems:uploads:prune reports uploaded files',
        null, 1,
    ],
];

foreach ($edits as $i => [$from, $to, $anchor, $expect]) {
    $seen = substr_count($xml, $from);

    if ($seen !== $expect) {
        fwrite(STDERR, "REFUSED: needle {$i} occurs {$seen} time(s), expected {$expect}: ".substr($from, 0, 60)."\n");

        exit(1);
    }

    printf("probe needle %d occurs %d as intended\n", $i, $seen);
}

echo "\n";

docxApply($docx, $backup, $edits, [], $apply);

if (! $apply) {
    echo "\nDRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$again = new ZipArchive;
$again->open($docx);
$after = (string) $again->getFromName('word/document.xml');
$again->close();

foreach ([
    'holds these eight:' => 0,
    'holds these nine:' => 1,
    'ImportStudents, ImportTimetable, PrintFidelitySamples' => 1,
    'ems:import:timetable reads a timetable sheet' => 1,
    'which holds the half-open overlap rule once for both surfaces' => 1,
] as $needle => $expected) {
    $seen = substr_count($after, $needle);

    if ($seen !== $expected) {
        fwrite(STDERR, "REFUSED (post-write): '{$needle}' occurs {$seen}, expected {$expected}.\n");

        exit(1);
    }

    printf("after %-66s %d\n", substr($needle, 0, 64), $seen);
}

echo "\npass 261 applied: §18's command inventory is nine and the timetable importer is described where the other tools are.\n";
