<?php

/**
 * Falsifier for the page-batched readiness reads (ADS §P09, second half).
 *
 * `PrerequisiteGateTest` answers two promises: the batch must equal the fresh read for every record on the
 * page, and the three gate reads must leave the per-row loop. Each is pinned here by planting the defect that
 * would break it and requiring the named case to report a failure — a case that only ever sees the fixed bytes
 * measures nothing, and every one of these plants is a way this batch could go wrong while the suite stayed green.
 *
 * Files are restored by hash in a `finally`, and a leg reports BAD rather than passing when its needle does not
 * match exactly once: a plant that lands nowhere looks like a guard that survived, which is the difference
 * between a surviving defect and a harness that has aged. The filter is the method name, not the sentence Pest
 * prints for it, and a leg that selects no test is reported BAD rather than green.
 *
 * Usage: php storage/app/scratch/mutate-g-p09-readiness-batch.php
 */
$root = dirname(__DIR__, 3);
chdir($root);

$php = '"C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe"';
$testFile = 'tests/Feature/Registrar/PrerequisiteGateTest.php';
$paths = [
    'readiness' => 'app/Support/EnrollmentReadiness.php',
    'batch' => 'app/Support/ReadinessBatch.php',
    'controller' => 'app/Http/Controllers/Registrar/RegistrarController.php',
];
$pristine = [];
foreach ($paths as $k => $p) {
    $pristine[$k] = (string) file_get_contents($p);
}
$restore = static function () use ($pristine, $paths): void {
    foreach ($paths as $k => $p) {
        file_put_contents($p, $pristine[$k]);
    }
};

/** @return array{0:string,1:bool,2:bool,3:bool} text, green, exactly-one-failed, selected-nothing */
$runCase = static function (string $filter) use ($php, $testFile): array {
    $lines = [];
    $code = 0;
    exec($php.' artisan test '.escapeshellarg($testFile).' --filter='.escapeshellarg($filter).' 2>&1', $lines, $code);
    $text = preg_replace('/\e\[[0-9;]*m/', '', implode("\n", $lines)) ?? '';

    return [
        $text,
        preg_match('/Tests:\s+\d+ passed/', $text) === 1,
        str_contains($text, '1 failed'),
        (bool) preg_match('/No tests found/', $text),
    ];
};

$results = [];
$bad = 0;

$legs = [
    [
        'slug' => 'batch-ignored-in-the-gate',
        'file' => 'readiness',
        'from' => '$satisfied = $batch?->satisfiedFor($enrollment) ?? self::satisfiedSubjectIds($enrollment, $passingCeiling);',
        'to' => '$satisfied = self::satisfiedSubjectIds($enrollment, $passingCeiling);',
        'case' => 'the_page_batch_takes_the_passed_credited_and_prerequisite_reads_out_of_the_loop',
        'promise' => 'the gate asks the batch at all',
    ],
    [
        'slug' => 'prerequisite-read-never-batched',
        'file' => 'readiness',
        'from' => '$rows = $batch !== null',
        'to' => '$rows = false && $batch !== null',
        'case' => 'the_page_batch_takes_the_passed_credited_and_prerequisite_reads_out_of_the_loop',
        'promise' => 'the declared prerequisites come from the page read',
    ],
    [
        'slug' => 'passing-ceiling-dropped-in-the-batch',
        'file' => 'batch',
        'from' => 'if ((float) $row->best_grade > $passingCeiling) {',
        'to' => 'if (false) {',
        'case' => 'the_batched_gate_answer_is_the_fresh_answer_for_every_record_on_the_page',
        'promise' => 'a grade below the line is not called a pass by the batch',
    ],
    [
        'slug' => 'student-map-not-seeded',
        'file' => 'batch',
        'from' => "foreach (\$studentIds as \$studentId) {\n            \$passedByStudent[\$studentId] = [];\n        }",
        'to' => '',
        'case' => 'the_page_batch_takes_the_passed_credited_and_prerequisite_reads_out_of_the_loop',
        'promise' => 'a person with nothing graded yet is still answered from the page read',
    ],
    [
        'slug' => 'year-level-key-dropped',
        'file' => 'batch',
        'from' => "\$key = \$curriculumId.':'.\$yearLevel;",
        'to' => "\$key = \$curriculumId.':0';",
        'case' => 'the_batched_gate_answer_is_the_fresh_answer_for_every_record_on_the_page',
        'promise' => 'a prerequisite declared for another year level cannot answer this one',
    ],
    [
        'slug' => 'controller-builds-no-batch',
        'file' => 'controller',
        'from' => '$batch = ReadinessBatch::forEnrollments($enrollments->getCollection(), $passingCeiling);',
        'to' => '$batch = null;',
        'case' => 'the_queue_builds_the_batch_its_own_rows_are_answered_from',
        'promise' => 'the queue builds the batch its own rows are answered from',
    ],
];

try {
    // The control must be green before anything is planted, or every later verdict measures a broken tree.
    [$text, $green, , $noneFound] = $runCase('the_batched_gate_answer_is_the_fresh_answer_for_every_record_on_the_page');
    if (! $green || $noneFound) {
        fwrite(STDERR, "REFUSED: the control case is not green (or selected nothing), so a plant could not be told from a break.\n");
        exit(1);
    }
    $results[] = 'ok       control                the batch cases run green before any plant';

    foreach ($legs as $leg) {
        $path = $paths[$leg['file']];
        $original = $pristine[$leg['file']];
        $hits = substr_count($original, $leg['from']);
        if ($hits !== 1) {
            $results[] = 'BAD      '.$leg['slug'].'  needle matched '.$hits.' time(s), expected 1 — the harness has aged';
            $bad++;

            continue;
        }
        $mutated = str_replace($leg['from'], $leg['to'], $original);
        if ($mutated === $original) {
            $results[] = 'BAD      '.$leg['slug'].'  the replacement is identical to the needle';
            $bad++;

            continue;
        }
        file_put_contents($path, $mutated);
        if ((string) file_get_contents($path) !== $mutated) {
            $results[] = 'BAD      '.$leg['slug'].'  the plant did not reach the bytes';
            $bad++;
            $restore();

            continue;
        }
        [$text, $green, $oneFailed, $noneFound] = $runCase($leg['case']);
        if ($noneFound) {
            $results[] = 'BAD      '.$leg['slug'].'  the filter selected no test — a green over nothing is not a kill';
            $bad++;
        } elseif ($oneFailed && ! $green) {
            $results[] = 'ok       '.$leg['slug'].'  killed — '.$leg['promise'];
        } else {
            $results[] = 'BAD      '.$leg['slug'].'  SURVIVED — '.$leg['promise'].' (the named case stayed green)';
            $bad++;
        }
        $restore();
    }

    // Green again after every plant, or the run proved the plants and not the build.
    [$text, $green, , $noneFound] = $runCase('the_batched_gate_answer_is_the_fresh_answer_for_every_record_on_the_page');
    if ($green && ! $noneFound) {
        $results[] = 'ok       control-again            the restored tree runs green';
    } else {
        $results[] = 'BAD      control-again            the restored tree is not green';
        $bad++;
    }
} finally {
    $restore();
}

foreach ($results as $r) {
    echo $r."\n";
}
$total = count($results);
echo $bad === 0
    ? "ALL KILLED: {$total} leg(s), 0 bad\n"
    : "{$bad} of {$total} leg(s) BAD — read them as the harness or the guard, never as a clean run.\n";
exit($bad === 0 ? 0 : 1);
