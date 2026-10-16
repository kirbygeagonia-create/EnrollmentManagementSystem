<?php

/**
 * Prove pass 109 from end to end: the sentence the document made BEFORE the pass, about the code as it
 * stood BEFORE the batch, must be the sentence the document makes NOW, about the code as it stands NOW.
 *
 * Re-running the shift probe after the pass is not this check — it re-measures the new numbers against the
 * old file and reports them shifted again, which is what a correct pass is supposed to produce. So the two
 * artifacts are read from their historical places: the docx from `HEAD` (the commit before this pass
 * touched it) and the code from `HEAD~1` (the commit before the boundary landed), and each cited span is
 * located afresh in the working tree. The number found there must be the number the current document now
 * carries.
 *
 * Read-only. A file that ends the run with nothing reported is a file whose citations all check out.
 */

$root = dirname(__DIR__, 3);

$files = [
    'EvaluationController.php' => 'app/Http/Controllers/Evaluation/EvaluationController.php',
    'ShiftRequestController.php' => 'app/Http/Controllers/Evaluation/ShiftRequestController.php',
    'AdmissionController.php' => 'app/Http/Controllers/Admission/AdmissionController.php',
    'ExamController.php' => 'app/Http/Controllers/Exam/ExamController.php',
    'StudentController.php' => 'app/Http/Controllers/StudentController.php',
    'EvaluationPolicy.php' => 'app/Policies/EvaluationPolicy.php',
    'StudentPolicy.php' => 'app/Policies/StudentPolicy.php',
];

$norm = fn (string $l): string => trim(preg_replace('/\s+/', ' ', $l));

/** The docx as committed before this pass rewrote any number in it. */
$oldXml = '';
exec(sprintf('cd %s && git show HEAD:"Documentation/EMS Complete Documentation.docx" > "%s"',
    escapeshellarg($root), escapeshellarg($root.'/storage/app/scratch/docx-head.bin')));
$zip = new ZipArchive();
$zip->open($root.'/storage/app/scratch/docx-head.bin');
$oldXml = $zip->getFromName('word/document.xml');
$zip->close();

$newXml = (function () use ($root): string {
    $z = new ZipArchive();
    $z->open($root.'/Documentation/EMS Complete Documentation.docx');
    $x = $z->getFromName('word/document.xml');
    $z->close();

    return $x;
})();

$checked = 0;
$bad = 0;

foreach ($files as $name => $rel) {
    $oldCode = array_map($norm, explode("\n", shell_exec(sprintf('cd %s && git show HEAD~1:%s',
        escapeshellarg($root), escapeshellarg($rel))) ?: ''));
    $newCode = array_map($norm, explode("\n", file_get_contents($root.'/'.$rel)));

    preg_match_all('/'.preg_quote($name, '/').':\d+(?:-\d+)?/', $oldXml, $found);
    foreach (array_values(array_unique($found[0])) as $token) {
        $nums = array_map('intval', explode('-', substr($token, strlen($name) + 1)));
        $from = $nums[0];
        $to = $nums[1] ?? $nums[0];
        $len = $to - $from + 1;
        $span = array_slice($oldCode, $from - 1, $len);

        if (count($span) < $len || trim(implode('', $span)) === '') {
            continue;
        }

        $at = false;
        if (array_slice($newCode, $from - 1, $len) === $span) {
            $at = $from - 1;
        } else {
            for ($i = 0; $i + $len <= count($newCode); $i++) {
                if (array_slice($newCode, $i, $len) === $span) {
                    $at = $i;

                    break;
                }
            }
        }

        if ($at === false) {
            printf("  UNPROVABLE %s — this batch edited inside the span; the sentence needs a human\n", $token);
            $bad++;
            $checked++;

            continue;
        }

        $expect = $name.':'.($at + 1).($len > 1 ? '-'.($at + $len) : '');
        $samePosition = $at === $from - 1;
        $carried = substr_count($newXml, $expect) >= substr_count($oldXml, $token);

        // The pass was allowed to leave a citation alone only when the span never moved. Anything else has
        // to appear in the current document under its new number, and the old number must be gone.
        if (! $samePosition && ! $carried) {
            printf("  WRONG %s → %s   (the document still says 「%s」)\n", $token, $expect, $token);
            $bad++;
        } elseif (substr_count($newXml, $token) > 0 && ! $samePosition && $token !== $expect) {
            printf("  STALE %s still appears %d time(s) in the document\n", $token, substr_count($newXml, $token));
            $bad++;
        } else {
            $checked++;
        }
    }
}

printf("\nchecked %d citation token(s) across %d files; %d problem(s)\n", $checked, count($files), $bad);
exit($bad === 0 ? 0 : 1);
