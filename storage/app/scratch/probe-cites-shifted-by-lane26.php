<?php

/**
 * Which docx line citations did THIS batch move, computed rather than guessed.
 *
 * A citation is a promise about text, so the test is mechanical: take the file as it stood before the
 * batch (`HEAD~1`), read out the exact span the citation named, and look for that same text in the file as
 * it stands now. Found at the same lines — nothing moved. Found elsewhere — the citation is stale and this
 * prints where it now lives. Not found at all — the batch edited inside the cited span itself, so the
 * sentence has to be re-read by a human, and this says so rather than inventing a number.
 *
 * Read-only. Prints nothing about files this batch did not touch.
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';

$files = [
    'EvaluationController.php' => 'app/Http/Controllers/Evaluation/EvaluationController.php',
    'ShiftRequestController.php' => 'app/Http/Controllers/Evaluation/ShiftRequestController.php',
    'AdmissionController.php' => 'app/Http/Controllers/Admission/AdmissionController.php',
    'ExamController.php' => 'app/Http/Controllers/Exam/ExamController.php',
    'StudentController.php' => 'app/Http/Controllers/StudentController.php',
    'EvaluationPolicy.php' => 'app/Policies/EvaluationPolicy.php',
    'StudentPolicy.php' => 'app/Policies/StudentPolicy.php',
];

$flat = file_get_contents($root.'/storage/app/scratch/docx-flat.txt');

$totals = ['ok' => 0, 'moved' => 0, 'unreadable' => 0, 'past_end' => 0];

foreach ($files as $name => $rel) {
    $before = explode("\n", shell_exec(sprintf('cd %s && git show HEAD~1:%s', escapeshellarg($root), escapeshellarg($rel))) ?: '');
    $after = explode("\n", file_get_contents($root.'/'.$rel));

    // Positive control: compared against itself, this probe must find nothing moved. If it reports a move
    // here, its matching is wrong and every number below it is worthless.
    if (in_array('--selftest', $argv, true)) {
        $after = $before;
    }

    // Positive control: with --selftest the "new" file is the old one, so every citation must be reported
    // unmoved. If this run ever finds a move, the matcher — not the document — is broken, and no verdict
    // below it can be believed.
    if (in_array('--selftest', $argv, true)) {
        $after = $before;
    }

    preg_match_all('/'.preg_quote($name, '/').':(\d+)(?:-(\d+))?/', $flat, $m, PREG_SET_ORDER);
    if ($m === []) {
        continue;
    }

    $seen = [];
    echo "\n### {$name}  (".(count($before) - 1)." lines before, ".(count($after) - 1)." now)\n";

    foreach ($m as $x) {
        $from = (int) $x[1];
        $to = ($x[2] ?? '') === '' ? $from : (int) $x[2];
        $key = $from.'-'.$to;
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;

        $span = implode("\n", array_slice($before, $from - 1, $to - $from + 1));
        $trimmed = trim(preg_replace('/\s+/', ' ', $span));

        if ($trimmed === '') {
            printf("  %-12s EMPTY in the old file — the citation never pointed at anything\n", $key);
            $totals['unreadable']++;

            continue;
        }

        if ($to > count($before) - 1) {
            printf("  %-12s *** past the end of the pre-batch file (%d lines) — stale before this batch ***\n",
                $key, count($before) - 1);
            $totals['past_end']++;

            continue;
        }

        // Where does the WHOLE span sit now, matched as a run of normalised lines rather than by its first
        // line: a span that opens with `/**` or `}` would otherwise "match" the first such line in the file
        // and report a confident, wrong number. This is the positive-control rule applied to my own tool —
        // a first version of this probe did exactly that on EvaluationPolicy.php:32-57, answering 17-42.
        $linesAfter = array_map(fn ($l) => trim(preg_replace('/\s+/', ' ', $l)), $after);
        $spanLines = [];
        foreach (range($from, $to) as $ln) {
            $spanLines[] = trim(preg_replace('/\s+/', ' ', $before[$ln - 1] ?? ''));
        }

        // Ask the original position first. A search that always takes the *earliest* match reports a moved
        // citation for any span whose text also appears elsewhere in the file — two seeders' identical
        // blocks, two roles' identical grant lists — and a self-test run (before == after) caught exactly
        // that: it claimed two citations had moved in a file that had not changed at all.
        $at = false;
        $needleLen = count($spanLines);

        if (array_slice($linesAfter, $from - 1, $needleLen) === $spanLines) {
            $at = $from - 1;
        } else {
            for ($i = 0; $i + $needleLen <= count($linesAfter); $i++) {
                if (array_slice($linesAfter, $i, $needleLen) === $spanLines) {
                    $at = $i;

                    break;
                }
            }
        }

        if ($at === false) {
            // Fall back to the non-blank lines only: a citation whose span is intact but re-wrapped by the
            // formatter (blank lines added or removed inside it) is still the same construct.
            $want = array_values(array_filter($spanLines, fn ($l) => $l !== ''));
            $have = array_values(array_filter($linesAfter, fn ($l) => $l !== ''));
            $at = false;
            if ($want !== []) {
                $joined = "\n".implode("\n", $have)."\n";
                $probe = "\n".implode("\n", $want)."\n";
                $pos = strpos($joined, $probe);
                if ($pos !== false) {
                    // Count back to the real line by walking the filtered prefix.
                    $beforeLines = substr_count($joined, "\n", 0, $pos === 0 ? 0 : $pos);
                    $counted = 0;
                    foreach ($linesAfter as $idx => $l) {
                        if ($l !== '') {
                            $counted++;
                        }
                        if ($counted === $beforeLines + 1) {
                            $at = $idx;

                            break;
                        }
                    }
                }
            }
        }

        if ($at === false) {
            printf("  %-12s SPAN NOT FOUND — the batch edited inside it; re-read the sentence\n              was: %s\n",
                $key, mb_substr($trimmed, 0, 90));
            $totals['unreadable']++;

            continue;
        }

        $newFrom = $at + 1;
        $newTo = $newFrom + ($to - $from);

        if ($newFrom === $from) {
            $totals['ok']++;

            continue;
        }

        printf("  %-12s → %d-%d\n              was: %s\n              now: %s\n",
            $key, $newFrom, $newTo,
            mb_substr($trimmed, 0, 90),
            mb_substr(trim(preg_replace('/\s+/', ' ', implode(' ', array_slice($after, $at, $to - $from + 1)))), 0, 90));
        $totals['moved']++;
    }
}

printf("\ntotals: %s\n", json_encode($totals));
