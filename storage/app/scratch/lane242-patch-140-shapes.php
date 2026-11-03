<?php

/**
 * Give `lane140-measure-wired-screens.php` the shape split, read from `lane215`'s export.
 *
 * lane140 counts PAGES that offer no order; lane215 decides which of those pages could carry one at all, because it
 * is the tool that resolves a page to the action that renders it and to whether that action paginates. The figure is
 * CONSUMED, never re-derived: a second implementation of "does this action paginate" is the defect this repository has
 * refused for year levels, sort keys and narrowed payloads.
 *
 * When no --shapes= file is supplied the run NAMES the unjudged surface rather than implying it was weighed — the
 * same discipline `lane71 --ci` uses for the rows surface it cannot reach on a runner.
 *
 * Usage: php storage/app/scratch/lane242-patch-140-shapes.php
 */
$root = 'C:/Users/ADMIN/OneDrive/Documents/3rd_Year/EnrollmentManagementSystem';
$f = $root.'/storage/app/scratch/lane140-measure-wired-screens.php';
$s = (string) file_get_contents($f);

$owedPrint = "printf(\"\\nowed pages (%d):\\n\", count(\$owedPages));";
if (substr_count($s, $owedPrint) !== 1) {
    fwrite(STDERR, "REFUSED: the owed-pages print is not found exactly once; re-read the tool before patching.\n");
    exit(1);
}

$block = <<<'PHP'

// ---------------------------------------------------------------- the shape of the owed queue
// "Eight pages offer no order" is true for the unit this tool counts. It is not a queue of eight work items: a page
// whose action hands its rows over whole has no server order to ask for. That predicate belongs to lane215, which
// resolves each page to its action, so the split is read from its export rather than re-implemented here.
$shapesArg = null;
foreach (array_slice($GLOBALS['argv'], 1) as $a) {
    if (str_starts_with($a, '--shapes=')) {
        $shapesArg = substr($a, 9);
    }
}
if ($shapesArg === null) {
    echo "\nSHAPES of the owed queue: NOT JUDGED — pass --shapes=<lane215 --json= output> to weigh which owed pages "
        ."could carry a header at all.\n";
} else {
    $shapesPath = preg_match('#^[A-Za-z]:|^/#', $shapesArg) === 1 ? $shapesArg : $root.'/'.$shapesArg;
    if (! is_file($shapesPath)) {
        fwrite(STDERR, "REFUSED: --shapes= target {$shapesPath} is not a file (run lane215 with --json= first).\n");
        exit(1);
    }
    $shapeRows = json_decode((string) file_get_contents($shapesPath), true);
    if (! is_array($shapeRows) || $shapeRows === []) {
        fwrite(STDERR, "REFUSED: --shapes= read no records, so the split would be a story about nothing.\n");
        exit(1);
    }
    $byShort = [];
    foreach ($shapeRows as $r) {
        $byShort[(string) $r['short']] = $r;
    }
    $wireable = [];
    $bounded = [];
    $unknown = [];
    foreach ($owedPages as $p) {
        $short = (string) preg_replace('#^resources/js/Pages/#', '', $p);
        if (! isset($byShort[$short])) {
            $unknown[] = $short;
        } elseif (($byShort[$short]['paginates'] ?? null) === true) {
            $wireable[] = $short;
        } else {
            $bounded[] = $short;
        }
    }
    // The arithmetic has to close in front of the reader: an owed page the export does not name is not evidence that
    // it cannot carry a header, and a summary that silently drops it would report a smaller queue than the tree holds.
    printf("\nSHAPES of the owed queue: %d wireable + %d bounded + %d unread = %d owed\n",
        count($wireable), count($bounded), count($unknown), count($owedPages));
    foreach ($wireable as $w) {
        echo '  WIREABLE  '.$w.'  action '.($byShort[$w]['action'] ?? '?').'  offer '.($byShort[$w]['offer'] ?? '?')."\n";
    }
    foreach ($bounded as $b) {
        echo '  bounded   '.$b.'  action '.($byShort[$b]['action'] ?? '?')."\n";
    }
    foreach ($unknown as $u) {
        echo '  UNREAD    '.$u."  (lane215 named no record for it — re-take both, do not assume the export is current)\n";
    }
    if ($unknown !== []) {
        fwrite(STDERR, "REFUSED: ".$unknown[0]." is owed here and unread in the shape export; the split cannot be quoted.\n");
        exit(1);
    }
}
PHP;

$s = str_replace($owedPrint, $owedPrint.'__SHAPE_MARK__', $s);
// Place the block after the owed-list loop rather than mid-printf: the loop closes on the line after the print.
$loop = "foreach (\$owedPages as \$p) {\n    echo '  '.\$p.\"\\n\";\n}\n";
if (substr_count($s, $loop) !== 1) {
    fwrite(STDERR, "REFUSED: the owed-pages loop is not found exactly once.\n");
    exit(1);
}
$s = str_replace($loop, $loop.$block, $s);
$s = str_replace($owedPrint.'__SHAPE_MARK__', $owedPrint, $s);

if (file_put_contents($f, $s) === false) {
    fwrite(STDERR, "REFUSED: the patch could not be written.\n");
    exit(1);
}
echo "patched lane140: --shapes= reads lane215's export, names an unjudged surface, and refuses an unread page\n";
