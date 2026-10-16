<?php

/**
 * Point the demo pipeline's Department Evaluation credit at the college that owns the program.
 *
 * Every `evaluatedBy` in this file credited Guidance's office head. The ruling on 2026-10-13 is that the
 * owning college's Academic Department performs the act, and `DevReferenceDataSeeder` now seats one
 * department account per college — so each of these lines is rewritten to resolve the desk from the
 * `courseId` in the very array it sits in.
 *
 * Two guards, because a bulk rewrite in an un-gated file (`pint.json` excludes tools/, PHPStan analyses
 * app/ only) is proven only by running it:
 *
 *   - the courseId is taken from the SAME array as the evaluatedBy line: the scan stops at `]);` so a
 *     neighbouring insert cannot donate its course to the wrong credit;
 *   - the expected site count is asserted before anything is written, and every planned swap is printed.
 *     A silently-shifted file is how a bulk edit becomes a data defect.
 *
 * Run with --apply once the plan reads right.
 */

$path = dirname(__DIR__, 3).'/tools/seed_full_demo_pipeline.php';
$apply = in_array('--apply', $argv, true);
$lines = file($path);

$sitePattern = "/^(\s*)'evaluatedBy' => \\\$deskSigners\\[OfficeId::Guidance->value\\]( \?\? 1)?,\s*$/";
// The courseId line usually carries a trailing comment (`'courseId' => 1, // BSIT`). A first draft
// required the line to end at the comma, so every commented site was skipped and a few arrays paired
// with a neighbouring statement's course instead — which is why the plan below prints the source line
// verbatim: the comment is the evidence that the expression came from the right array.
$coursePattern = "/^\s*'courseId' => (.+?),(?:\s*\/\/.*)?$/";
$arrayEnd = '/\]\);/';

$planned = [];
$unresolved = [];

foreach ($lines as $i => $line) {
    if (preg_match($sitePattern, $line, $m) !== 1) {
        continue;
    }

    $indent = $m[1];
    $courseExpr = null;
    $courseLine = null;

    for ($j = $i - 1; $j >= 0 && $j > $i - 25; $j--) {
        if (preg_match($coursePattern, $lines[$j], $c) === 1) {
            $courseExpr = trim($c[1]);
            $courseLine = trim($lines[$j]);
            // Reject an expression that reaches across a closed array: the courseId must belong to this
            // same create([...]) block, not to whichever statement ended just above it.
            for ($k = $j + 1; $k < $i; $k++) {
                if (preg_match($arrayEnd, $lines[$k]) === 1) {
                    $courseExpr = null;

                    break;
                }
            }
            break;
        }
    }

    if ($courseExpr === null) {
        $unresolved[] = $i + 1;
        continue;
    }

    $planned[] = [$i, $indent, $courseExpr, $j + 1, $courseLine];
}

printf("Guidance evaluatedBy sites found: %d   unresolved: %d\n", count($planned) + count($unresolved), count($unresolved));
if ($unresolved !== []) {
    echo '  UNRESOLVED at lines: '.implode(', ', $unresolved)."\n";
}

foreach ($planned as [$i, $indent, $expr, $fromLine, $source]) {
    printf("  line %4d  <=  line %4d  %-52s  => \$departmentEvaluatorForCourse(%s)\n",
        $i + 1, $fromLine, $source, $expr);
}

$expected = 19;
if (count($planned) !== $expected || $unresolved !== []) {
    fwrite(STDERR, sprintf("\nREFUSED: expected %d resolved sites, got %d resolved and %d unresolved.\n",
        $expected, count($planned), count($unresolved)));
    exit(1);
}

if (! $apply) {
    echo "\nDRY RUN — pass --apply to write\n";
    exit(0);
}

foreach ($planned as [$i, $indent, $expr]) {
    $lines[$i] = sprintf("%s'evaluatedBy' => \$departmentEvaluatorForCourse(%s),\n", $indent, $expr);
}

file_put_contents($path, implode('', $lines));

$back = file_get_contents($path);
printf("WROTE %s\n  Guidance deskSigners left in evaluatedBy: %d\n  helper calls: %d\n",
    basename($path),
    preg_match("/'evaluatedBy' => \\\$deskSigners\\[OfficeId::Guidance/", $back),
    substr_count($back, '$departmentEvaluatorForCourse(') - 1);
exit(0);
