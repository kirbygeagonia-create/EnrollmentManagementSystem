<?php
/**
 * Read-only: for every citation this batch re-numbered, print the first and last line of the NEW window next to the
 * claim the document's sentence makes about it. A shifted pointer may only inherit its verdict after those lines are
 * seen to carry it — AGENTS.md's rule that a re-point must be read, never shifted by arithmetic.
 */
$root = dirname(__DIR__, 3);

$cases = [
    ['app/Http/Controllers/Evaluation/EvaluationController.php', 737, 747, 'mandatory-block diff gated on standing and type'],
    ['app/Http/Controllers/Evaluation/EvaluationController.php', 227, 229, 'the curriculum pin comment and resolveCurriculum()'],
    ['app/Http/Controllers/Evaluation/EvaluationController.php', 749, 773, 'the prerequisite auto-gate through its ValidationException'],
    ['app/Http/Controllers/Evaluation/EvaluationController.php', 600, 600, 'evaluatedBy written by profile capture'],
    ['app/Http/Controllers/Evaluation/EvaluationController.php', 387, 414, 'satisfiedPrerequisites() whole'],
    ['app/Http/Controllers/Evaluation/EvaluationController.php', 109, 127, 'the queue filter that became DeskQueues::evaluation()'],
    ['app/Http/Controllers/Exam/ExamController.php', 269, 299, 'the entrance-applicant query the exam desks share'],
    ['app/Http/Controllers/Exam/ExamController.php', 303, 310, 'the general-path head named in the G-8 pair'],
    ['app/Http/Controllers/Exam/ExamController.php', 338, 347, 'the passer-transfer branch'],
    ['app/Http/Controllers/Exam/ExamController.php', 424, 432, 'BR9 comment through the departmental requiresEntranceExam guard'],
    ['app/Http/Controllers/Exam/ExamController.php', 475, 482, 'the course-specific G-8 comment and its redirect'],
    ['app/Http/Controllers/Exam/ExamController.php', 560, 560, "results() echoing result and search"],
    ['app/Support/WorkflowService.php', 54, 73, "stepsFor()'s box entries and the array_filter that makes six"],
];

foreach ($cases as [$rel, $from, $to, $claim]) {
    $lines = file($root.'/'.$rel);
    if ($lines === false) {
        fwrite(STDERR, "REFUSED: cannot read {$rel}.\n");

        exit(1);
    }
    $a = trim((string) ($lines[$from - 1] ?? ''));
    $b = trim((string) ($lines[$to - 1] ?? ''));
    printf("%s:%d-%d\n    claim : %s\n    first : %s\n    last  : %s\n",
        basename($rel), $from, $to, $claim,
        substr($a, 0, 96), substr($b, 0, 96));
}
