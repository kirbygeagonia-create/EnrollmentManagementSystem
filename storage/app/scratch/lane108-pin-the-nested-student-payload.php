<?php

/**
 * Lane 108: make the three queue payload cases ask the question they were not asking.
 *
 * Two gaps, both found by doing rather than reasoning:
 *
 *  (a) The case compared the column set sorted against unsorted, but never asked whether the desk ANSWERED. When this
 *      batch first narrowed the eager loads with a column `students` does not have, all three queues began answering
 *      500 — and the sort classes still reported green. A case that cannot see the difference between a page and an
 *      error page is the "leg that cannot fail" class, reached from the response side.
 *  (b) It looked only at the ROW's own keys, which is the right test for G-51 and the wrong one for G-52: the student
 *      fields that must not arrive do so NESTED, inside `enrollment.student`.
 *
 * So each class gained an `assertOk()` on every request it compares, plus an exact key set for the nested student.
 *
 * The patching is DONE, so a plain run of this file is now the READ-BACK: it counts each of the four checks per
 * class and refuses if any has drifted. An earlier draft carried a placeholder of the shape
 * `assertTrue($plain instanceof View || true)` — an assertion that cannot fail — and that block has been DELETED
 * rather than left in the source, because a scratch file a later reader runs is read as evidence of what landed.
 *
 * Usage: php storage/app/scratch/lane108-pin-the-nested-student-payload.php
 */
$root = dirname(__DIR__, 3);

// A copy of the three classes can be pointed at so a harness can delete one assertion in the copy and require this
// reader to refuse by name. Without that, the only way to falsify this file is to edit the tree mid-suite.
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--root=')) {
        $root = substr($arg, strlen('--root='));
        if (! is_dir($root.'/tests/Feature')) {
            fwrite(STDERR, "REFUSED: --root={$root} holds no tests/Feature, so nothing could be verified there.\n");

            exit(1);
        }

        continue;
    }
    fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this script reads only --root=.\n");

    exit(1);
}

// The identity set each narrowed load must answer with, and each class's own nested path. The Registrar queue lists
// enrollments, so its student is one level shallower than the two money queues' — which is why the path is per
// class and not one string searched everywhere.
$IDENTITY = "['firstName', 'lastName', 'middleName', 'schoolIdNumber', 'studentId']";
$classes = [
    ['tests/Feature/Sorting/AssessmentQueueSortTest.php', 'officer', "\$twoHop[0]['enrollment']['student']", 3],
    ['tests/Feature/Sorting/AccountingQueueSortTest.php', 'cashier', "\$twoHop[0]['enrollment']['student']", 3],
    ['tests/Feature/Registrar/RegistrarQueueSortTest.php', 'registrar', "\$twoHop[0]['student']", 2],
];

$failures = [];
foreach ($classes as [$file, $actor, $nested, $okExpected]) {
    $path = $root.'/'.$file;
    if (! is_file($path)) {
        $failures[] = "no file {$file}";

        continue;
    }
    $bytes = (string) file_get_contents($path);
    $ok = substr_count($bytes, '->assertOk()');
    $hasNested = substr_count($bytes, $nested);
    $hasSet = substr_count($bytes, $IDENTITY);
    $hasG51 = substr_count($bytes, 'assertArrayNotHasKey($leaked');

    // Counted rather than present-or-absent, because a class that kept one check and lost two must not read as done.
    // The `assertOk` floor is the number of requests this batch compares, so a silent deletion of one is a finding.
    if ($ok < $okExpected) {
        $failures[] = sprintf('%s: %d `->assertOk()` call(s), wanted at least %d — a case that reads the column set'
            ." of a 500 measures nothing, which is how this batch's own bad column list went unseen", $file, $ok, $okExpected);
    }
    if ($hasNested !== 1) {
        $failures[] = sprintf('%s: the nested student read (`%s`) occurs %d time(s), wanted exactly 1', $file, $nested, $hasNested);
    }
    if ($hasSet !== 1) {
        $failures[] = sprintf('%s: the identity key set occurs %d time(s), wanted exactly 1', $file, $hasSet);
    }
    if ($hasG51 < 1) {
        $failures[] = sprintf('%s: the G-51 top-level leak assertion has disappeared (%d copies left) — G-52 was'
            .' meant to sit beside it, not replace it', $file, $hasG51);
    }
    printf("  %-34s assertOk x%d (>=%d)  nested x%d  key-set x%d  G-51 leak x%d  [as %s]\n",
        basename($file), $ok, $okExpected, $hasNested, $hasSet, $hasG51, $actor);
}

if ($failures !== []) {
    fwrite(STDERR, "\nREFUSED:\n".implode("\n", $failures)."\n");

    exit(1);
}

echo "\nVERIFIED: each of the three queue classes asks whether the desk answered, and pins the student it loads to"
    ." the five identity columns the page paints.\n";
