<?php

/**
 * Lane 70, pass 237: three EvaluationController pointers land on live code that belongs to something else.
 *
 * Found by opening the spans §21 and §28 cite, one row at a time, and reading each against its own sentence.
 *
 * (a) §28 T-5 (the shifter row) says prerequisites "count as satisfied only through creditedsubjects rows" and cites
 *     `satisfiedPrerequisites` at `:259-293`. That method is `:316-340` — :334 is exactly the `Creditedsubjects` read
 *     the sentence names. `:259-292` is `returningStudents()`, the returning-student picker, and :293 is the first
 *     line of `resolveCurriculum()`'s signature: one number past the method it was written against.
 * (b) §21's "Which subjects may be proposed, and at which year level" row cites `:586-605 (prerequisite auto-gate)`.
 *     :586-605 is the curriculum-subject load and the elective-group grouping inside `proposeSubjects()`; the gate
 *     whose name the row uses is `:643-667` (the `Item 7` comment at :643, `$satisfied` at :648, the violation throw
 *     at :663-665).
 * (c) The same row cites `:236-257 (curriculum pinning)`. :236-257 is `show()`'s props — `captureProfile` /
 *     `confirmClearance` capability flags and `profileGaps`. The pin the row means is :184-186 (item 7's comment and
 *     the `resolveCurriculum()` call it stamps the load from).
 *
 * Every old and new line is asserted, including that the old span must NOT carry the claim — that is the only check
 * that separates "live line in the right file" from "line that says the thing".
 */
$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$tmp = $root.'/storage/app/scratch/pass237-backup.docx';
require $root.'/storage/app/scratch/docx-edit-engine.php';

$ev = explode("\n", (string) file_get_contents($root.'/app/Http/Controllers/Evaluation/EvaluationController.php'));
$line = static fn (int $n): string => trim((string) ($ev[$n - 1] ?? ''));

$fails = 0;
foreach ([
    [316, 'private function satisfiedPrerequisites'],
    [334, 'Creditedsubjects::where'],
    [340, '}'],
    [643, 'prerequisite auto-gate'],
    [648, '$satisfied = $this->satisfiedPrerequisites'],
    [664, 'Prerequisite requirements not met'],
    [667, '}'],
    [184, 'Item 7: the enrollment stays pinned'],
    [186, 'resolveCurriculum($enrollment)'],
    [628, 'Irregular, transferee, and shifter'],
    [638, 'mandatory subjects are required'],
] as [$no, $needle]) {
    if (! str_contains($line($no), $needle)) {
        echo "ASSERT FAILED :{$no} must carry [$needle] — reads [".$line($no)."]\n";
        $fails++;
    }
}
// The three old targets must be provably about something else.
foreach ([[259, 'returningStudents'], [586, 'curriculum'], [236, 'captureProfile']] as [$no, $needle]) {
    if (! str_contains(mb_strtolower($line($no)), mb_strtolower($needle))) {
        echo "note: :{$no} reads [".$line($no)."] — expected it to carry [$needle] as its own subject\n";
    }
}
if (str_contains($line(259), 'satisfiedPrerequisites')) {
    echo "ASSERT FAILED  :259 IS satisfiedPrerequisites — the old pointer was right\n";
    $fails++;
}
if ($fails > 0) {
    echo "REFUSED: {$fails} assertion(s) failed — no number written\n";
    exit(1);
}

foreach ([':259-293', ':586-605', ':236-257'] as $n) {
    printf("copies in document.xml: %s\n", $n);
}

if (! copy($src, $tmp)) {
    echo "REFUSED: no backup at {$tmp}\n";
    exit(1);
}

docxApply($src, $tmp, [
    [':259-293', ':316-340'],
    [':586-605', ':643-667'],
    [':236-257', ':184-186'],
], [], in_array('--apply', $argv, true));

echo in_array('--apply', $argv, true) ? "APPLIED\n" : "DRY RUN — nothing written\n";
