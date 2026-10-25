<?php

/**
 * Lane 70, pass 233: the ExamController candidate-list cluster — the 27th, 28th and 29th wrong targets.
 *
 * Six copies of `ExamController.php:181-247` all name the *passer transfer*: the branch that runs when the program
 * administers its general paper. That branch closes at :241 (`$students = collect($rows);` is :243) and `} else {`
 * is :244, so every one of the six ranges reaches six lines past the arm it names, into the comment that explains
 * the *opposite* case (:245-249). Six copies become `:181-241`.
 *
 * §13's candidate row then carries two bare continuations whose spans say more than their clause: `:225` is one line
 * inside the waived-transferee query while the clause it is offered for ("the transferees whose program waives that
 * paper") is the whole guarded block :210-240, and `:239-250` opens on the closing bracket of the waived row and ends
 * past the else arm, which is :244-251.
 *
 * §13's prose paragraph is the sharpest: it reads `entranceApplicants at :239-250`, but the method is :279-288 — it
 * named the call site (:250) and re-used that number as if it were the definition. And §13's hand-off row already
 * cites `:279-290`, which walks past the method's closing brace at :288 through a blank line into
 * `recordGeneral()`'s docblock at :290. Both become `:279-288`.
 *
 * Old and new lines are asserted, including the blank at :242, the brace at :244, the brace at :288 and the `/**` at
 * :290 — those four lines are the whole claim that the old ranges were wrong.
 */
$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$tmp = $root.'/storage/app/scratch/pass233-backup.docx';
require $root.'/storage/app/scratch/docx-edit-engine.php';

$ex = explode("\n", (string) file_get_contents($root.'/app/Http/Controllers/Exam/ExamController.php'));

$fails = 0;
foreach ([
    [181, 'if ($type === ExamType::CourseSpecific && $course?->requiresEntranceExam)'],
    [185, 'Examresults::with'],
    [201, '])->all();'],
    [210, '$course->entranceExamExemptsTransferee'],
    [225, 'ExamType::General'],
    [238, "'generalExamWaived' => true"],
    [240, '}'],
    [241, '}'],
    [243, '$students = collect($rows);'],
    [244, '} else {'],
    [250, '$this->entranceApplicants('],
    [251, '}'],
    [279, 'private function entranceApplicants'],
    [284, 'admissionStatus', 'Pending'],
    [287, '->get(['],
    [288, '}'],
] as $a) {
    [$no, $needle] = [$a[0], $a[1]];
    $actual = trim((string) ($ex[$no - 1] ?? ''));
    if (! str_contains($actual, $needle) || (isset($a[2]) && ! str_contains($actual, $a[2]))) {
        echo "ASSERT FAILED  ExamController.php:{$no} must carry [{$needle}] — reads [{$actual}]\n";
        $fails++;
    }
}
// Blank lines are asserted as blanks: :242, :289 prove the old ranges crossed an arm or a method.
foreach ([242, 289] as $no) {
    if (trim((string) ($ex[$no - 1] ?? '')) !== '') {
        echo 'ASSERT FAILED  ExamController.php:'.$no.' must be blank, reads ['.trim((string) ($ex[$no - 1] ?? ''))."]\n";
        $fails++;
    }
}
if (! str_contains(trim((string) ($ex[289] ?? '')), '/**')) {
    echo "ASSERT FAILED  ExamController.php:290 must open the NEXT method's docblock, reads [".trim((string) ($ex[289] ?? ''))."]\n";
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
    [':181-247', ':181-241', null, 6],
    [':225', ':210-240', 'General passers for the program and term'],
    [':239-250', ':244-251', 'General passers for the program and term'],
    [':239-250', ':279-288', 'entranceApplicants at '],
    // NOT taken: `:279-290` occurs 3 times in document.xml. Each copy must be read against its own sentence before
    // anything is renamed — `expect=3` would be a blind replace of a shared token (the lane 69 and pass 215 rule).
], [], in_array('--apply', $argv, true));

echo in_array('--apply', $argv, true) ? "APPLIED\n" : "DRY RUN — nothing written\n";
