<?php

/**
 * Lane 108 (G-52): narrow the desk queues' student eager loads to the columns a queue page reads.
 *
 * Each of these loads the whole `students` row to paint a name and a school number, so the cashier's and the
 * assessment officer's payloads carry `birthdate`, `contactNumber`, `email`, `religionId` and the rest of a
 * student's record — fields no queue page reads (measured: the three `Index.jsx` files use only lastName,
 * firstName, middleName and schoolIdNumber). `Students::$hidden` already withholds `passwordHash` on the nested
 * model, which is why the live read could report it absent everywhere; what it could not withhold is everything
 * the model does not hide.
 *
 * Written as a line-neutral change on purpose: a different string on the SAME line moves no citation, so the
 * authority document's pointers into these three handlers stay true without a shift run — the property lane 97
 * learned to check for, and the reason this patch edits the `with([...])` text rather than restructuring it.
 *
 * Every needle must match exactly once or nothing is written, and the script refuses a replacement that changes a
 * file's line count.
 *
 * Usage: php storage/app/scratch/lane108-narrow-the-queue-student-loads.php [--apply] [--cols=a,b,c]
 *
 * Since the change landed, a plain run reports the tree ALREADY APPLIED: it is now also the check that the four
 * narrowed loads have not drifted back to a bare `'student'`, and every column name it writes is first read off the
 * `students` migrations, because a list naming a column the table lacks is a 500 at the desk rather than a wrong value.
 */
$root = dirname(__DIR__, 3);
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--apply' || str_starts_with($arg, '--cols=')) {
        continue;
    }
    fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this script reads only --apply and --cols=.\n");

    exit(1);
}

// The identity set a queue page actually renders. `studentId` is the relation's owner key and must be selected or
// every nested student arrives null — the silent failure a green suite would not show.
//
// This list once read `id,studentId,schoolIdNumber,...` and `students` has never held an `id`, so all three queues
// began answering 500 while every sort class stayed green. The guard below is why the mistake cannot be re-typed:
// each column is checked against the migrations that declare the table, not against memory of it.
$cols = 'studentId,schoolIdNumber,lastName,firstName,middleName';

// An override exists for one reason: so a harness can hand the guard a name the table does not hold and require it
// to refuse by name. It changes nothing else, and the needles below are built from whatever list is in force.
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--cols=')) {
        $cols = substr($arg, strlen('--cols='));
    }
}

$declared = studentsColumnsFromMigrations($root);
if ($declared === []) {
    fwrite(STDERR, "REFUSED: no `students` column was read out of the migrations, so the guard below would pass"
        ." on an empty set — the accepted-and-inert-flag defect, arrived from the schema side.\n");

    exit(1);
}
foreach (explode(',', $cols) as $c) {
    if (! in_array($c, $declared, true)) {
        fwrite(STDERR, sprintf("REFUSED: `%s` is not a column the students migrations declare (the table holds %s),"
            ." and a column list naming a column the table lacks is a 500 at the desk, not a wrong value.\n",
            $c, implode(', ', $declared)));

        exit(1);
    }
}

$edits = [
    [
        'app/Http/Controllers/Accounting/AccountingController.php',
        "Studentassessments::with(['enrollment.student', 'enrollment.course', 'enrollment.term'])",
        "Studentassessments::with(['enrollment.student:{$cols}', 'enrollment.course', 'enrollment.term'])",
    ],
    [
        'app/Http/Controllers/Assessment/AssessmentController.php',
        "Studentassessments::with(['enrollment.student', 'enrollment.course', 'enrollment.term', 'charges.feeType', 'scholarships.scholarshipType'])",
        "Studentassessments::with(['enrollment.student:{$cols}', 'enrollment.course', 'enrollment.term', 'charges.feeType', 'scholarships.scholarshipType'])",
    ],
    [
        'app/Http/Controllers/Assessment/AssessmentController.php',
        "Enrollments::with(['student', 'course', 'term', 'enrolledSubjects.subject'])",
        "Enrollments::with(['student:{$cols}', 'course', 'term', 'enrolledSubjects.subject'])",
    ],
    [
        'app/Http/Controllers/Registrar/RegistrarController.php',
        "            'student', 'course', 'major', 'term',",
        "            'student:{$cols}', 'course', 'major', 'term',",
    ],
];

$failures = [];
$plan = [];
$byFile = [];
$done = 0;
foreach ($edits as [$file, $from, $to]) {
    $path = $root.'/'.$file;
    if (! is_file($path)) {
        $failures[] = "no file {$file}";
        continue;
    }
    $bytes = (string) file_get_contents($path);
    $hits = substr_count($bytes, $from);
    // Already narrowed: the replacement sits once and the old needle nowhere. Saying DONE rather than refusing on
    // "needle matched 0" is what keeps a re-run of a finished patcher from reading like a broken one.
    if ($hits === 0 && substr_count($bytes, $to) === 1) {
        $done++;

        continue;
    }
    if ($hits !== 1) {
        $failures[] = sprintf('%s: needle matched %d time(s), wanted exactly 1 — %s', $file, $hits, substr($from, 0, 70));
        continue;
    }
    if ($from === $to) {
        $failures[] = "{$file}: needle and replacement are the same bytes";
        continue;
    }
    $plan[] = ['file' => $file, 'from' => $from, 'to' => $to];
    $byFile[$file][] = [$from, $to];
}
if ($failures !== []) {
    fwrite(STDERR, "REFUSED — nothing written:\n".implode("\n", $failures)."\n");

    exit(1);
}

printf("students columns read from the migrations: %d\n", count($declared));
printf('%d replacement(s) over %d file(s), %d site(s) already narrowed.', count($plan), count($byFile), $done);
echo PHP_EOL;
if ($plan === []) {
    echo 'ALREADY APPLIED — the tree carries the narrowed list at every site, and the column guard above read'
        ." each name off the migrations.\n";

    exit(0);
}
foreach ($plan as $p) {
    printf("  %-52s %s\n", basename($p['file']), str_contains($p['to'], $cols) ? 'student load narrowed' : '?');
}
if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

foreach ($byFile as $file => $list) {
    $path = $root.'/'.$file;
    $bytes = (string) file_get_contents($path);
    $lines = substr_count($bytes, "\n");
    foreach ($list as [$from, $to]) {
        $bytes = str_replace($from, $to, $bytes);
    }
    if (substr_count($bytes, "\n") !== $lines) {
        fwrite(STDERR, "REFUSED: {$file} would change its line count; this edit was meant to be line-neutral.\n");

        exit(1);
    }
    file_put_contents($path, $bytes);
}

$bad = 0;
foreach ($byFile as $file => $list) {
    $after = (string) file_get_contents($root.'/'.$file);
    foreach ($list as [$from, $to]) {
        if (substr_count($after, $to) !== 1 || substr_count($after, $from) !== 0) {
            printf("  FAIL %s: %s\n", $file, substr($from, 0, 50));
            $bad++;
        }
    }
}
if ($bad > 0) {
    fwrite(STDERR, "REFUSED: {$bad} read-back check(s) failed.\n");

    exit(1);
}
echo 'written, re-read: every queue now loads the identity columns and nothing else.'."\n";

/**
 * Every column the migrations declare on `students`, read out of `database/migrations` rather than recalled.
 *
 * `Blueprint::dropColumn` is excluded, because a dropped name is not a column a select list may ask for, and the
 * read is a whole-file scan rather than an `up()`-only one for the same reason lane 60 names: a create migration
 * carries its own rollback, and the DROP lives below the CREATE it belongs to.
 *
 * @return string[]
 */
function studentsColumnsFromMigrations(string $root): array
{
    $cols = [];
    foreach (glob($root.'/database/migrations/*.php') ?: [] as $file) {
        $src = (string) file_get_contents($file);
        // Bind per BLOCK, not per file: a migration that touches `students` and also creates another table would
        // otherwise lend that table's columns to this list, and a guard that accepts a name belonging to a
        // different table is no guard at all. A block runs from `Schema::` to the first `});`, which is where
        // Laravel's own closure ends — the rollback's `Schema::dropIfExists` sits below it and is never read.
        $at = 0;
        while (($start = strpos($src, 'Schema::', $at)) !== false) {
            $close = strpos($src, '});', $start);
            if ($close === false) {
                break;
            }
            $block = substr($src, $start, $close - $start);
            $at = $close + 3;
            if (! preg_match("/^Schema::(?:create|table)\(\s*'students'/", $block)) {
                continue;
            }
            if (preg_match_all('/\$table->dropColumn\(\s*\'([a-zA-Z0-9_]+)\'/', $block, $dm)) {
                foreach ($dm[1] as $c) {
                    unset($cols[$c]);
                }
            }
            if (preg_match_all('/\$table->\w+(?:\([^)]*\))?\(\'([a-zA-Z0-9_]+)\'/', $block, $cm)) {
                foreach ($cm[1] as $c) {
                    $cols[$c] = true;
                }
            }
        }
    }

    if ($cols === []) {
        return [];
    }

    return array_keys($cols);
}
