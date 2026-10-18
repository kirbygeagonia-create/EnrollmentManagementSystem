<?php

/**
 * Lane 46 probe — every citation the document points into ExamController.php, and whether the +2 shift is safe.
 *
 * Two questions, answered from the files rather than remembered:
 *  1. Which ranges does the document actually cite? The bare companions matter — the text writes
 *     "ExamController.php:234 , entranceApplicants at :237-248" and "ExamController.php:234 , 309-341", and a
 *     scan for `File.php:NN` alone misses both. A bare range is the same claim with the file name dropped, and
 *     it moves exactly the same way.
 *  2. For each cited line, is the line the document saw now sitting two rows lower? The old file is the
 *     committed HEAD version; if line i there equals line i+2 now, the shift preserves the citation by content,
 *     which is a stronger statement than arithmetic. Where they differ, the citation must be re-read, not moved.
 *
 * Read-only.
 */

$old = explode("\n", (string) file_get_contents('storage/app/scratch/lane46-exam-old.txt'));
$new = file('app/Http/Controllers/Exam/ExamController.php');
$doc = (string) file_get_contents('storage/app/scratch/docx-flat.txt');

$ranges = [];
if (preg_match_all('/ExamController\.php/u', $doc, $hits, PREG_OFFSET_CAPTURE)) {
    foreach ($hits[0] as $hit) {
        $window = substr($doc, $hit[1], 420);
        // Stop at the next file name: a 420-character window also swallows the citations to
        // BlockingController.php and friends that follow in the same sentence, and their line numbers get
        // attributed to this file. Truncating at the next `.php` keeps the claim about this file only.
        $next = strpos($window, '.php', strlen('ExamController.php'));
        if ($next !== false) {
            $window = substr($window, 0, $next);
        }
        // `:NN`, `NN-NN`, and a bare `:NN-NN` after the file name — all in one pass
        if (preg_match_all('/(\d+)(?:-(\d+))?/', $window, $nm, PREG_SET_ORDER)) {
            foreach ($nm as $m) {
                $a = (int) $m[1];
                if ($a < 20 || $a > 600) {
                    continue; // page numbers, sections and years are not line numbers
                }
                $b = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : $a;
                if ($b < $a || $b - $a > 320) {
                    continue;
                }
                $ranges[$a.'-'.$b] = true;
            }
        }
    }
}
$keys = array_keys($ranges);
sort($keys);
printf("%d distinct range(s) cited into ExamController.php: %s\n\n", count($keys), implode(', ', $keys));

$bad = 0;
foreach ($keys as $r) {
    [$a, $b] = explode('-', $r);
    $a = (int) $a;
    $b = (int) $b;
    // Which shift keeps the cited text? 0 before the first edit, 2 between the two, 5 past results().
    // A range that answers to none of them is a citation to re-read, not a number to move.
    $shift = null;
    foreach ([0, 2, 5] as $d) {
        $same = true;
        for ($i = $a; $i <= $b; $i++) {
            if (trim($old[$i - 1] ?? '!!') !== trim($new[$i + $d - 1] ?? '!!')) {
                $same = false;

                break;
            }
        }
        if ($same) {
            $shift = $d;

            break;
        }
    }
    printf("%-10s -> %-10s %s\n", $r, ($a + ($shift ?? 0)).'-'.($b + ($shift ?? 0)),
        $shift === null ? 'NO SHIFT FITS — re-read this one' : "identical at +{$shift}");
    if ($shift === null) {
        $bad++;
        foreach ([$a, $b] as $i) {
            printf("    old %-4d %s\n", $i, mb_substr(trim($old[$i - 1] ?? ''), 0, 78));
        }
    }
}

printf("\n%d range(s) need re-reading rather than moving.\n", $bad);
