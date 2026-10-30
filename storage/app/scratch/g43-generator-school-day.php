<?php

/**
 * G43, the generator's half.
 *
 * lane87 found 27 writes in tools/seed_full_demo_pipeline.php that feed a column the migrations declare DATE
 * with `now()`. Eloquent formats a Carbon for a `date` cast in the APPLICATION timezone — UTC here — so each of
 * those rows carried the daemon's civil day, and between 16:00Z and midnight that is the day before the school's.
 * Lane 54 already ruled the principle for this file: the demo data follows the desk, not the daemon.
 *
 * The three deliberate ages (`now()->subMonths(9)` and `now()->subMonths(15)`) keep their age and gain the
 * school's day: `SchoolTime::civilDateOf()` answers which calendar day that instant is at the school.
 *
 * Every replacement is asserted by COUNT before the file is written. The needles carry their trailing comma, so
 * the plain and the aged form are disjoint — but the ages are still rewritten first, because a later edit that
 * drops the comma from one needle would turn this into a rewrite that silently replaces an age with a today.
 *
 * Usage: php storage/app/scratch/g43-generator-school-day.php [--apply]
 */

$root = dirname(__DIR__, 3);
$path = $root.'/tools/seed_full_demo_pipeline.php';
$apply = in_array('--apply', array_slice($argv, 1), true);

/** [needle, replacement, expected count] — longest first. */
$edits = [
    ["'enrolledDate' => now()->subMonths(9),", "'enrolledDate' => SchoolTime::civilDateOf(now()->subMonths(9)),", 2],
    ["'enrolledDate' => now()->subMonths(15),", "'enrolledDate' => SchoolTime::civilDateOf(now()->subMonths(15)),", 1],
    ["'formSignedDate' => now()->subMonths(9),", "'formSignedDate' => SchoolTime::civilDateOf(now()->subMonths(9)),", 1],
    ["'formSignedDate' => now()->subMonths(15),", "'formSignedDate' => SchoolTime::civilDateOf(now()->subMonths(15)),", 1],
    ["'enrolledDate' => now(),", "'enrolledDate' => SchoolTime::today(),", 6],
    ["'formSignedDate' => now(),", "'formSignedDate' => SchoolTime::today(),", 11],
    ["'evaluatedDate' => now(),", "'evaluatedDate' => SchoolTime::today(),", 1],
    ["'assessmentDate' => now(),", "'assessmentDate' => SchoolTime::today(),", 2],
    ["'requestDate' => now(),", "'requestDate' => SchoolTime::today(),", 2],
];

$src = (string) file_get_contents($path);
$failures = 0;
$total = 0;

foreach ($edits as [$from, $to, $want]) {
    $n = substr_count($src, $from);
    printf("%-46s %2d found, %2d expected  %s\n", $from, $n, $want, $n === $want ? '' : 'MISMATCH');
    $total += $n;
    if ($n !== $want) {
        $failures++;
    }
}

// Nothing outside the DATE family may move: these three names are instants, and lane 50 says the viewer's zone
// is the right answer for them.
foreach (['validatedDate', 'signedDate', 'receivedDate'] as $instant) {
    $n = substr_count($src, "'{$instant}' => now(),");
    printf('untouched %-38s %2d write(s) — an instant, not a day', $instant, $n);
    echo "\n";
}

if ($failures > 0) {
    fwrite(STDERR, "\nREFUSED: {$failures} needle(s) did not land the expected count. NOTHING WRITTEN.\n");

    exit(1);
}

$out = $src;
foreach ($edits as [$from, $to, $want]) {
    $out = str_replace($from, $to, $out);
}

if (! str_contains($out, 'use App\Support\SchoolTime;')) {
    fwrite(STDERR, "REFUSED: the file does not import SchoolTime, so every line above would fatal at runtime.\n");

    exit(1);
}

printf("\n%s%d write(s) moved to the school's day.\n", $apply ? '' : 'DRY RUN — ', $total);

if ($apply) {
    file_put_contents($path, $out);
    echo "written.\n";
}
