<?php

/**
 * Pass 231 — the twenty-sixth wrong target: a "never examined at either stage" claim citing one stage's upsert.
 *
 * §10.4's board-course row reads: "non-board programs are never examined at either stage (§10.4) |
 * ExamPolicy.php:35-51, ExamController.php:181-247 and :311-343". The property is enforced by two guards —
 * `recordGeneral()` refuses a course that does not require the entrance exam (`if (! $course->requiresEntranceExam)`
 * at :306, "This course does not require an entrance exam" at :307) and `recordCourseSpecific()` asks
 * `$course->requiresEntranceExam` at :366, under the BR9/C-4/C-5 comment at :358 that pass 224 established.
 * `:311-343` is neither of those: it is the tail of the *general upsert* (`firstOrNew` :312-318, `save()`) plus the
 * opening lines of `recordCourseSpecific()`'s signature and authorize, so the sentence quoted a range that spans a
 * method boundary while naming the rule that keeps both methods from running.
 *
 * The needle `ExamController.php:181-247 and :311-343` occurs exactly once in `document.xml`; `:311-343` itself
 * also occurs once, so both spellings are asserted rather than trusted. Old and new lines are checked before the
 * write, including that :311 is a comment line inside the general upsert.
 *
 *   php storage/app/scratch/refresh-docx-pass231.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass231.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$ec = (array) file($root.'/app/Http/Controllers/Exam/ExamController.php');
$fails = 0;
foreach ([
    [293, 'public function recordGeneral('],
    [306, 'if (! $course->requiresEntranceExam) {'],
    [307, 'This course does not require an entrance exam'],
    [308, '}'],
    [333, '}'],
    [339, 'public function recordCourseSpecific('],
    [358, 'BR9'],
    [366, 'if ($course->requiresEntranceExam)'],
    // what the wrong pointer actually lands on
    [311, '//'],
    [312, '$exam = Examresults::firstOrNew(['],
    [343, ''],
] as [$n, $needle]) {
    $t = trim((string) ($ec[$n - 1] ?? ''));
    $ok = $needle === '' || str_contains($t, $needle);
    printf("  %s :%-4d [%s]\n", $ok ? 'ok  ' : 'BAD ', $n, mb_substr($t, 0, 62));
    $fails += $ok ? 0 : 1;
}
foreach ([[293, 'recordGeneral'], [339, 'recordCourseSpecific']] as [$n, $needle]) {
    if (! str_contains((string) ($ec[$n - 1] ?? ''), $needle)) {
        echo "BAD    the method boundary the pass relies on is not where it was measured.\n";
        $fails++;
    }
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

$needle = 'ExamController.php:181-247 and :311-343';
$want = 'ExamController.php:181-247, :306-308 and :358-366';
$n = substr_count($xml, $needle);
printf("\n  needle [%s] occurs %d time(s) in document.xml (want 1)\n", $needle, $n);
if ($n !== 1 || substr_count($xml, ':311-343') !== 1) {
    echo "REFUSED: the counts are not the one copy this pass measured.\n";
    exit(1);
}
$after = str_replace($needle, $want, $xml);
printf("  after: repaired needle occurs %d (want 1); the old pair occurs %d (want 0)\n",
    substr_count($after, $want), substr_count($after, $needle));
if (substr_count($after, $want) !== 1 || substr_count($after, $needle) !== 0) {
    echo "REFUSED: the consequence of the edit is not what this pass predicts.\n";
    exit(1);
}

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass231.docx', [[$needle, $want]], [], $apply);
