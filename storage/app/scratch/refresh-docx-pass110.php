<?php

/**
 * Pass 110 — the five citations pass 109 refused to guess at.
 *
 * This batch edited *inside* those spans, so no run of identical lines could be found and pass 109 left
 * them alone rather than invent numbers. Each is now re-measured the way AGENTS.md asks: by meaning. The
 * sentence names a construct — a method, a refusal, a policy arm — so the new number is where that
 * construct begins and ends in the file as it stands, read off the structure (declaration line through the
 * closing brace, or docblock through the last line the sentence describes), not shifted by an arithmetic
 * delta.
 *
 * Verified before writing: every target line is printed with the statement the sentence talks about, and
 * the guard below re-reads each new range and requires the construct still to be inside it.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open source\n");
    exit(1);
}
$original = $xml = $zip->getFromName('word/document.xml');
$zip->close();

/**
 * old token => [new token, file, first line must contain, last line must contain, occurrences expected]
 */
$plan = [
    'EvaluationController.php:68-103' => [
        'to' => 'EvaluationController.php:70-116',
        'file' => 'app/Http/Controllers/Evaluation/EvaluationController.php',
        'first' => 'public function index(Request $request): Response',
        'last' => '*/',
        'n' => 2,
        'claim' => 'the Evaluation queue and the filter it applies',
    ],
    'ExamController.php:309-341' => [
        'to' => 'ExamController.php:335-389',
        'file' => 'app/Http/Controllers/Exam/ExamController.php',
        'first' => "\$course = Courses::findOrFail(\$validated['courseId']);",
        'last' => '    }',
        'n' => 2,
        'claim' => 'refuses the record twice over',
    ],
    'ExamController.php:309-312' => [
        'to' => 'ExamController.php:338-340',
        'file' => 'app/Http/Controllers/Exam/ExamController.php',
        'first' => 'if (! $course->requiresEntranceExam && ! $course->requiresCourseSpecificExam) {',
        'last' => '    }',
        'n' => 1,
        'claim' => 'refuses only a program that administers neither paper',
    ],
    'ExamController.php:139-360' => [
        'to' => 'ExamController.php:144-389',
        'file' => 'app/Http/Controllers/Exam/ExamController.php',
        'first' => 'public function students(Request $request): JsonResponse',
        'last' => '    }',
        'n' => 1,
        'claim' => 'the whole examination-recording region, from the roster to the course-specific paper',
    ],
    'EvaluationPolicy.php:32-57' => [
        'to' => 'EvaluationPolicy.php:40-67',
        'file' => 'app/Policies/EvaluationPolicy.php',
        'first' => '/**',
        'last' => '    }',
        'n' => 1,
        'claim' => 'every student at Department Evaluation passes this gate',
    ],
];

$fails = 0;
$edits = [];

foreach ($plan as $from => $p) {
    $count = substr_count($xml, $from);
    if ($count !== $p['n']) {
        fwrite(STDERR, "REFUSE: 「{$from}」 appears {$count} time(s), expected {$p['n']}\n");
        $fails++;

        continue;
    }

    // The new range must actually hold the construct the sentence describes. Read the file, not the plan.
    $lines = file($root.'/'.$p['file']);
    [, $range] = explode(':', $p['to'], 2);
    $nums = array_map('intval', explode('-', $range));
    [$a, $b] = [$nums[0], $nums[1] ?? $nums[0]];

    $firstLine = trim($lines[$a - 1] ?? '');
    $lastLine = trim($lines[$b - 1] ?? '');
    $span = trim(preg_replace('/\s+/', ' ', implode(' ', array_slice($lines, $a - 1, $b - $a + 1))));

    if ($firstLine !== trim($p['first'])) {
        fwrite(STDERR, sprintf("REFUSE: %s line %d reads 「%s」, expected 「%s」\n",
            $p['to'], $a, $firstLine, trim($p['first'])));
        $fails++;

        continue;
    }
    if ($lastLine !== trim($p['last'])) {
        fwrite(STDERR, sprintf("REFUSE: %s line %d reads 「%s」, expected the closing 「%s」\n",
            $p['to'], $b, $lastLine, trim($p['last'])));
        $fails++;

        continue;
    }

    // And the sentence's own subject has to be inside the span it now cites.
    $must = match (true) {
        str_contains($p['claim'], 'queue') => "\$this->authorize('viewAny', Enrollments::class)",
        str_contains($p['claim'], 'twice over') => 'This course administers no entrance examination',
        str_contains($p['claim'], 'neither paper') => 'This course administers no entrance examination',
        str_contains($p['claim'], 'roster') => 'entranceApplicants(',
        default => 'CollegeScope::holds($user, $course->unitId)',
    };
    if (! str_contains($span, $must)) {
        fwrite(STDERR, sprintf("REFUSE: %s does not contain 「%s」 — the sentence would cite the wrong code\n",
            $p['to'], $must));
        $fails++;

        continue;
    }

    $at = 0;
    for ($i = 0; $i < $p['n']; $i++) {
        $at = strpos($xml, $from, $at);
        $edits[] = [$at, strlen($from), $p['to'], $from];
        $at += strlen($from);
    }
    printf("planned %-34s → %-34s (%d)  holds 「%s」\n", $from, $p['to'], $p['n'], $must);
}

if ($fails > 0 || $edits === []) {
    printf("\n%d problem(s) — nothing was written\n", $fails);
    exit(1);
}

usort($edits, fn ($x, $y) => $y[0] <=> $x[0]);
$previousStart = PHP_INT_MAX;
foreach ($edits as $e) {
    if ($e[0] + $e[1] > $previousStart) {
        fwrite(STDERR, sprintf("REFUSE: edits overlap at %d and %d\n", $e[0], $previousStart));
        exit(1);
    }
    $previousStart = $e[0];
}

foreach ($edits as [$off, $len, $to, $label]) {
    $xml = substr_replace($xml, $to, $off, $len);
    printf("applied  %s\n", $label);
}

$guards = [
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'no run gained or lost' => substr_count($original, '<w:r>') === substr_count($xml, '<w:r>'),
    'no table row gained or lost' => substr_count($original, '<w:tr>') === substr_count($xml, '<w:tr>'),
    'root start tag untouched' => substr($original, 0, strpos($original, '<w:body>')) === substr($xml, 0, strpos($xml, '<w:body>')),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'only digits changed' => preg_replace('/\d+/', '', $original) === preg_replace('/\d+/', '', $xml),
    'the document still parses' => simplexml_load_string($xml) !== false,
];
foreach ($plan as $from => $p) {
    $guards['old gone: '.$from] = substr_count($xml, $from) === 0;
    $guards['new present: '.$p['to']] = substr_count($xml, $p['to']) === $p['n'];
}

foreach ($guards as $name => $ok) {
    if (! $ok) {
        fwrite(STDERR, "REFUSE guard: {$name}\n");
        $fails++;
    } else {
        printf("guard ok %s\n", $name);
    }
}

if ($fails > 0) {
    printf("\n%d problem(s) — nothing was written\n", $fails);
    exit(1);
}

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-13-pass110.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
