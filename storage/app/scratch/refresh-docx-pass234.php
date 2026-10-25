<?php

/**
 * Lane 70, pass 234: the two §13 rows that cite `entranceApplicants()` at `:279-290`.
 *
 * The method is 279-288: `:288` is its closing brace, `:289` is blank and `:290` opens `recordGeneral()`'s docblock,
 * so both ranges walk one construct past the one their own sentence names — the straddle shape, invisible to a bounds
 * check because every line exists and the file is right.
 *
 * `:279-290` occurs THREE times in `document.xml`. The third is `IDController.php:279-290` in §20's ID-remark row —
 * a different file and a different construct — so this pass takes the two ExamController copies by anchor rather than
 * `expect=3`. That refusal is what prompted the pass: an earlier draft of pass 233 asked for three and the engine
 * stopped it.
 */
$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$tmp = $root.'/storage/app/scratch/pass234-backup.docx';
require $root.'/storage/app/scratch/docx-edit-engine.php';

$ex = explode("\n", (string) file_get_contents($root.'/app/Http/Controllers/Exam/ExamController.php'));

$fails = 0;
foreach ([
    [279, 'private function entranceApplicants'],
    [284, 'admissionStatus'],
    [285, 'whereDoesntHave'],
    [287, '->get(['],
    [288, '}'],
] as [$no, $needle]) {
    $actual = trim((string) ($ex[$no - 1] ?? ''));
    if (! str_contains($actual, $needle)) {
        echo "ASSERT FAILED  ExamController.php:{$no} must carry [{$needle}] — reads [{$actual}]\n";
        $fails++;
    }
}
if (trim((string) ($ex[288] ?? '')) !== '') {
    echo 'ASSERT FAILED  ExamController.php:289 must be blank, reads ['.trim((string) ($ex[288] ?? ''))."]\n";
    $fails++;
}
if (! str_contains(trim((string) ($ex[289] ?? '')), '/**')) {
    echo "ASSERT FAILED  ExamController.php:290 must open the NEXT method's docblock, reads [".trim((string) ($ex[289] ?? ''))."]\n";
    $fails++;
}
// The third copy must stay untouched, and its file must be a different one.
$id = explode("\n", (string) file_get_contents($root.'/app/Http/Controllers/Id/IDController.php'));
if (! str_contains(trim((string) ($id[278] ?? '')), 'public function')
    || ! str_contains(trim((string) ($id[289] ?? '')), '}')) {
    echo "REFUSED: IDController.php:279-290 is not a method span any more — the third copy needs its own read\n";
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
    [':279-290', ':279-288', 'The candidate list is genera'],
    [':279-290', ':279-288', 'Admitted-track students with admissionStatus'],
], [], in_array('--apply', $argv, true));

echo in_array('--apply', $argv, true) ? "APPLIED\n" : "DRY RUN — nothing written\n";
