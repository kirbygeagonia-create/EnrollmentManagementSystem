<?php

/**
 * Lane 114 (task #41's honest half): make the Evaluation desk list ask the class its dashboard card already asks.
 *
 * `DeskQueues::evaluation($desk)` is `CollegeScope::ofEnrollments(Enrollments::query(), $desk)` plus
 * `whereIn(Pending, ReturnedToEvaluation)` — which is EXACTLY the population `EvaluationController::index()` builds
 * today, restated. §28.3 G-46 closed that class by giving the badge one definition; three lists joined the call in
 * batch 3 and two were deliberately left alone because a card names a subset. This is the fourth list, and it is a
 * true match, so unifying it removes a drift rather than inventing one.
 *
 * Admission is NOT touched by this patcher, and that is the finding: `DeskQueues::admission($desk)` carries
 * `where('admissionStatus', Pending)` while the desk's list shows submitted cases AND saved drafts of every status.
 * Converting it would hide rows from the desk — the §8 contradiction with the sign flipped, which lane 96 already
 * refused once for the assessment badges. The follow-up is a case that states the subset, not a splice.
 *
 * Safety: the block is replaced with the SAME number of lines, so the only line movement in the file is the one
 * `use` statement Pint will file for the new call (this script writes the fully-qualified call and lets Pint shorten
 * it, because a hand-added import above an existing block is how the ordered-imports rule then moves every line
 * below it twice). Run `pint` first, then `lane76-shift-cites.php`, then the bounds sweep — the order this repository
 * has paid for before.
 *
 * Usage: php storage/app/scratch/lane114-convert-the-evaluation-list.php [--apply]
 */
$root = dirname(__DIR__, 3);
$rel = 'app/Http/Controllers/Evaluation/EvaluationController.php';
$path = $root.'/'.$rel;
$apply = in_array('--apply', $argv, true);
foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unknown argument {$arg}; this script reads only --apply.\n");

        exit(1);
    }
}

$bytes = (string) file_get_contents($path);

$from = <<<'PHP'
        // Ruled 2026-10-13: a college's department receives the enrollments of its own college. A desk
        // belonging to no college (Registrar, Guidance, Accounting, the clinic) is bounded by nothing and
        // keeps the whole queue, which is what the ruling says about those desks.
        $query = CollegeScope::ofEnrollments(
            Enrollments::with(['student:studentId,schoolIdNumber,lastName,firstName,middleName', 'course', 'major', 'term', 'evaluatedByUser']),
            $desk
        )
            // Item 8: enrollments returned by the Registrar land back on this
            // desk with a return reason, alongside fresh Pending records.
            ->whereIn('enrollmentStatus', [EnrollmentStatus::Pending, EnrollmentStatus::ReturnedToEvaluation])
            ->when($request->search, $search)
            ->orderByDesc('enrollmentId');
PHP;

$to = <<<'PHP'
        // Ruled 2026-10-13: a college's department receives the enrollments of its own college. A desk
        // belonging to no college (Registrar, Guidance, Accounting, the clinic) is bounded by nothing and
        // keeps the whole queue, which is what the ruling says about those desks.
        // Item 8: enrollments the Registrar sent back with a return reason land here alongside the fresh
        // Pending ones. Those two states are exactly what the dashboard card counts, so the list now asks
        // the same call rather than restating it (§28.3 G-46) — one predicate, two readers, and no way for
        // the number above the queue to describe a set the queue does not. The search and the order stay
        // here: they read what this desk typed, which is the page's business, not the population's.
        $query = \App\Support\DeskQueues::evaluation($desk)
            ->with(['student:studentId,schoolIdNumber,lastName,firstName,middleName', 'course', 'major', 'term', 'evaluatedByUser'])
            ->when($request->search, $search)
            ->orderByDesc('enrollmentId');
PHP;

$hits = substr_count($bytes, $from);
$linesFrom = substr_count($from, "\n");
$linesTo = substr_count($to, "\n");
printf('block occurs %d time(s), want 1 — %d line(s) in, %d line(s) out%s', $hits, $linesFrom + 1, $linesTo + 1, PHP_EOL);
if ($hits !== 1) {
    fwrite(STDERR, "REFUSED: the block did not match exactly once; the tree moved since this patcher was written.\n");

    exit(1);
}
if ($linesFrom !== $linesTo) {
    fwrite(STDERR, "REFUSED: the replacement would move ".($linesTo - $linesFrom)." line(s) inside this method;"
        ." re-cut the comment block so the count is neutral.\n");

    exit(1);
}

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$written = str_replace($from, $to, $bytes);
file_put_contents($path, $written);
$again = (string) file_get_contents($path);
if ($again !== $written) {
    fwrite(STDERR, "REFUSED: the file did not come back as written; restoring.\n");
    file_put_contents($path, $bytes);

    exit(1);
}
if (substr_count($again, 'DeskQueues::evaluation(') !== 1 || substr_count($again, 'CollegeScope::ofEnrollments(') !== substr_count($bytes, 'CollegeScope::ofEnrollments(') - 1) {
    fwrite(STDERR, "REFUSED: read-back did not show exactly one new call and one retired scope call.\n");
    file_put_contents($path, $bytes);

    exit(1);
}
printf("written, re-read: the desk list now asks the helper (%d line(s) moved inside the method)%s", $linesTo - $linesFrom, PHP_EOL);
echo "next: php vendor/bin/pint on this file, then lane76-shift-cites for its citations, then the bounds sweep.\n";
