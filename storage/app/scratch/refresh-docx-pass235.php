<?php

/**
 * Lane 70, pass 235: two register rows cited a live line of the right file that says the opposite thing.
 *
 * (a) §28 E-11 ("A block is scheduled into a room smaller than the block's maxStudents — the schedule is saved and
 * a warning string is returned") cites `BlockingController.php:288-289`. :288 is the ternary's FALSE branch —
 * `: back()->with('success', 'Schedule added.');`, the return when there is no warning — and :289 closes the
 * transaction. The warn-and-proceed whose words the same row quotes is :286-287 (`return $warning` /
 * `? back()->with('success', …)->with('warning', $warning)`).
 *
 * (b) §13's Department Evaluation step row says "evaluatedBy written by profile capture ( :273 )". :273 is a
 * *comment* inside `returningStudents()` (259-292), the returning-student picker; `evaluatedBy` is written at
 * :496, which is inside `captureProfile()` (416-522) — the profile capture the sentence names. The other write is
 * :539, in `decideStanding()`, which the row does not claim.
 *
 * `:273` is not unique in the document (§1.1's own quoted example uses it), so (b) goes by anchor. Old and new
 * lines are asserted, including that the old target must NOT carry the claim.
 */
$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$tmp = $root.'/storage/app/scratch/pass235-backup.docx';
require $root.'/storage/app/scratch/docx-edit-engine.php';

$bl = explode("\n", (string) file_get_contents($root.'/app/Http/Controllers/Blocking/BlockingController.php'));
$ev = explode("\n", (string) file_get_contents($root.'/app/Http/Controllers/Evaluation/EvaluationController.php'));

$fails = 0;
foreach ([
    [$bl, 286, 'return $warning'],
    [$bl, 287, "->with('warning', \$warning)"],
    [$bl, 471, 'assignStudents'],
    [$bl, 502, '$roomEnrolled + $requestedCount > $room->capacity'],
    [$ev, 416, 'public function captureProfile'],
    [$ev, 496, "'evaluatedBy' => Auth::user()->userId"],
    [$ev, 259, 'private function returningStudents'],
] as [$rows, $no, $needle]) {
    $actual = trim((string) ($rows[$no - 1] ?? ''));
    if (! str_contains($actual, $needle)) {
        echo 'ASSERT FAILED line '.$no." must carry [$needle] — reads [$actual]\n";
        $fails++;
    }
}
// The old targets must be provably wrong, or this pass has no business re-pointing them.
$old288 = trim((string) ($bl[287] ?? ''));
if (! str_starts_with($old288, ':') || str_contains($old288, 'warning')) {
    echo "ASSERT FAILED  BlockingController.php:288 should be the no-warning branch, reads [$old288]\n";
    $fails++;
}
$old273 = trim((string) ($ev[272] ?? ''));
if (str_contains($old273, 'evaluatedBy')) {
    echo "ASSERT FAILED  EvaluationController.php:273 does carry evaluatedBy — the old pointer was right\n";
    $fails++;
}
if ($fails > 0) {
    echo "REFUSED: {$fails} assertion(s) failed — no number written\n";
    exit(1);
}

if (! copy($src, $tmp)) {
    echo "REFUSED: no backup at {$tmp}\n";
    exit(1);
}

docxApply($src, $tmp, [
    [':288-289', ':286-287'],
    // (b) NOT taken here. The anchor resolves uniquely but owns no `:273`: in this cell the colon and the digits are
    // separate `<w:t>` runs, so no text needle can reach it. It goes through the engine's `cell` kind in pass 236,
    // which rebuilds the cell from document.xml — the same route pass 199/212 took for run-split citations.
], [], in_array('--apply', $argv, true));

echo in_array('--apply', $argv, true) ? "APPLIED\n" : "DRY RUN — nothing written\n";
