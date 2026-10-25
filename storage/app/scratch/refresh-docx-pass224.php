<?php

/**
 * Pass 224 — the thirteenth wrong target: a parenthetical that names the departmental paper and points at the general one.
 *
 * §28's register row answers "Does this program require an entrance examination at all" with
 * `ExamController.php:305-308 (general) and :311-314 (departmental, where the program runs the general paper at all)`.
 * The first is true: :306-308 is `if (! $course->requiresEntranceExam) { return … }`. The second is not.
 * :310-318 is the head of the **general** upsert — `'examStage' => ExamStage::Entrance, 'examType' =>
 * ExamType::General` — so the range cited for "departmental" is the same branch's own statement.
 *
 * The condition the aside describes lives in `recordCourseSpecific`, the departmental recording path: after the
 * `administers no entrance examination` refusal at :354-356, the comment at :358-365 records BR9 (stage 1 before
 * stage 2) together with the two rulings that narrow it — C-4 (a program may waive the general paper for its
 * transferees) and C-5 (a board program may run its own paper with no general one) — and :366 is the guard itself,
 * `if ($course->requiresEntranceExam)`. So the aside's range is :358-366.
 *
 * The old target is asserted too: the pass refuses unless :311-314 really is the general upsert and :366 really is
 * the condition, so the drift is proved rather than claimed. `:311-314` occurs once in document.xml.
 *
 *   php storage/app/scratch/refresh-docx-pass224.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass224.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$ec = (array) file($root.'/app/Http/Controllers/Exam/ExamController.php');
$fails = 0;
foreach ([
    [$ec, 306, 'if (! $course->requiresEntranceExam) {'],
    [$ec, 311, 'creating a duplicate (mirrors the retention path)'],
    [$ec, 314, "'courseId' => \$validated['courseId'],"],
    [$ec, 317, 'ExamType::General'],
    [$ec, 358, 'BR9: Stage 1 before Stage 2'],
    [$ec, 365, 'applicants unapprovable'],
    [$ec, 366, 'if ($course->requiresEntranceExam) {'],
] as [$l, $n, $needle]) {
    $t = trim($l[$n - 1] ?? '');
    $ok = str_contains($t, $needle);
    printf("  %s :%-4d [%s]\n", $ok ? 'ok  ' : 'BAD ', $n, mb_substr($t, 0, 62));
    $fails += $ok ? 0 : 1;
}
if ($fails > 0) {
    echo "REFUSED: a claimed line does not carry the claim.\n";
    exit(1);
}

$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();
printf("\n  needle [:311-314] occurs %d time(s) in document.xml (want 1)\n", substr_count($xml, ':311-314'));
if (substr_count($xml, ':311-314') !== 1) {
    echo "REFUSED: the range to move is not unique.\n";
    exit(1);
}

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass224.docx',
    [[':311-314', ':358-366', null, 1]], [], $apply);
