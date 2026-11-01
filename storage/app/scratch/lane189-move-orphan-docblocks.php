<?php

/**
 * Moves the docblocks that the seat-key fixture patches orphaned.
 *
 * Each patch inserted a new helper (with its own docblock) directly above an existing helper, and the existing
 * helper's docblock came to sit above the NEW one instead of its own method — so `@param array<string, mixed>
 * $overrides` documents a method that takes no parameters, and a sentence about an enrollment record documents a
 * student clone. A scanner that only checks syntax cannot see this; the tell is a closing comment marker on one
 * line and an opening one on the next, with no declaration between them — which is also why this file's own header
 * must not quote those two tokens literally: the first one ends the comment it sits in.
 *
 * Usage: php storage/app/scratch/lane189-move-orphan-docblocks.php [--apply]
 */
$apply = in_array('--apply', $argv, true);

$jobs = [
    [
        'file' => 'tests/Feature/Evaluation/ClearancePassSlipConfirmationTest.php',
        'orphan' => "    /**\n     * @param  array<string, mixed>  \$overrides\n     */\n",
        'above' => '    private function enrollment(array $overrides = []): Enrollments',
    ],
    [
        'file' => 'tests/Feature/Registrar/ClearanceWindowGateTest.php',
        'orphan' => "    /**\n     * @param  array<string, mixed>  \$overrides\n     */\n",
        'above' => '    private function enrollment(array $overrides = []): Enrollments',
    ],
    [
        'file' => 'tests/Feature/Registrar/EnrollmentDropTest.php',
        'orphan' => "    /**\n     * A record at the point this desk is answerable for, carrying a confirmed load.\n     */\n",
        'above' => '    private function enrolledRecord(EnrollmentStatus $status = EnrollmentStatus::Paid, ?Students $student = null): Enrollments',
    ],
];

foreach ($jobs as $job) {
    $path = $job['file'];
    $src = (string) file_get_contents($path);
    $orphans = substr_count($src, $job['orphan']);
    $targets = substr_count($src, $job['above']."\n");
    if ($orphans !== 1) {
        printf("SKIP %-58s orphan occurs %d time(s), expected 1 — re-read before moving anything\n", basename($path), $orphans);

        continue;
    }
    if ($targets !== 1) {
        printf("SKIP %-58s target declaration occurs %d time(s), expected 1\n", basename($path), $targets);

        continue;
    }
    // The move is a delete-then-insert, so the orphan text cannot land twice; the byte-identity of the result is
    // asserted, not assumed.
    $without = str_replace($job['orphan'], '', $src);
    if (substr_count($without, $job['orphan']) !== 0) {
        printf("SKIP %-58s the orphan survived its own removal\n", basename($path));

        continue;
    }
    $moved = str_replace($job['above']."\n", $job['orphan'].$job['above']."\n", $without);
    if (substr_count($moved, $job['orphan']) !== 1) {
        printf("SKIP %-58s the docblock did not reappear exactly once above its method\n", basename($path));

        continue;
    }
    if (strlen($moved) !== strlen($src)) {
        printf("SKIP %-58s the move changed the byte count (%d -> %d)\n", basename($path), strlen($src), strlen($moved));

        continue;
    }
    printf("%s %-58s moved one docblock onto its own method\n", $apply ? 'ok  ' : 'DRY ', basename($path));
    if ($apply && file_put_contents($path, $moved) === false) {
        fwrite(STDERR, "REFUSED: could not write {$path}\n");
        exit(1);
    }
}
exit(0);
