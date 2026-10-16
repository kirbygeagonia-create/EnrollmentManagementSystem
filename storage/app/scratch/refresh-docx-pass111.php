<?php

/**
 * Pass 111 — the citations the global sweep found drifted from earlier batches, re-measured by meaning.
 *
 * Every pass before this one re-measured only the files the *current batch* touched, so citations into
 * AuthServiceProvider, the policies and the Assessment/Registrar controllers had quietly fallen behind the
 * code they describe — `:97-101` for a Gate::before that is now at 106, `:144-146` for an evaluatedBy gate
 * now at 180, `web.php:108` for a route that moved to 128. The sweep (sweep-docx-citations.php) found the
 * ones that landed on a blank line or past the end of a file; a citation that lands on a *wrong but
 * non-blank* line is invisible to it, which is why each row below was read by sentence: the document names
 * a construct, and the new number is that construct's own bounds in the file as it stands.
 *
 * The guard is the same reading, done again on the written bytes: for every pair, the cited range is
 * re-opened in the real file and must contain the exact statement the sentence talks about. A wrong
 * number therefore cannot be written, and a mutant proves it.
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

$provider = 'app/Providers/AuthServiceProvider.php';

/** each: [anchor text in the document, replacement, file, first line, last line, statement inside, occurrences] */
$plan = [
    ['AuthServiceProvider.php:97-101', 'AuthServiceProvider.php:106-110', $provider,
        'Gate::before(function ($user, $ability) {', '});', "\$user->hasRole('SysAdmin')", 2],
    ['AuthServiceProvider.php:112-114', 'AuthServiceProvider.php:121-123', $provider,
        "Gate::define('blocking.manageBlocks', function (\$user) {", '});', 'BlockingPolicy::class', 1],
    ['AuthServiceProvider.php:149-151', 'AuthServiceProvider.php:160-162', $provider,
        "Gate::define('assessment.computeAtDesk', function (\$user, \$enrollment) {", '});', 'AssessmentPolicy::class', 1],
    ['AuthServiceProvider.php:155-157', 'AuthServiceProvider.php:167-169', $provider,
        "Gate::define('payment.recordAtDesk', function (\$user, \$assessment) {", '});', 'PaymentPolicy::class', 1],
    ['AuthServiceProvider.php:160-162', 'AuthServiceProvider.php:180-182', $provider,
        "Gate::define('clinic.viewAtDesk', function (\$user) {", '});', "hasPermissionTo('clinic.view')", 1],
    ['AuthServiceProvider.php:163-168', 'AuthServiceProvider.php:183-191', $provider,
        '// NOTE: the ability is intentionally named `clinic.recordAssessment`,', '});',
        "Gate::define('clinic.recordAssessment'", 1],
    [':173-177', ':207-215', $provider,
        "Gate::define('id.validateRequest', function (\$user, \$idRequest) {", '});',
        "Gate::define('id.remarkRequest'", 1],
    ['AuthServiceProvider.php:178-180', 'AuthServiceProvider.php:198-200', $provider,
        "Gate::define('id.viewAtDesk', function (\$user) {", '});', "hasPermissionTo('id.view')", 1],
    ['AuthServiceProvider.php:124', 'AuthServiceProvider.php:134', $provider,
        "Gate::define('exam.record', function (\$user, \$course, ExamStage \$stage, ExamType \$type) {",
        "Gate::define('exam.record', function (\$user, \$course, ExamStage \$stage, ExamType \$type) {",
        'ExamStage', 1],
    ['AuthServiceProvider.php:60-87', 'AuthServiceProvider.php:62-96', $provider,
        'protected $policies = [', '];', 'Enrollments::class => EvaluationPolicy::class', 1],
    [':106-199', ':106-299', $provider,
        'Gate::before(function ($user, $ability) {', '});', 'userManagement.manageSettings', 1],
    ['web.php:108', 'web.php:128', 'routes/web.php',
        "Route::get('/clearance/{clearance}/print', [ClearanceController::class, 'printSlip'])->name('clearance.print-slip');",
        "Route::get('/clearance/{clearance}/print', [ClearanceController::class, 'printSlip'])->name('clearance.print-slip');",
        "name('clearance.print-slip')", 1],
    ['PaymentPolicy.php:44-48', 'PaymentPolicy.php:45-48', 'app/Policies/PaymentPolicy.php',
        '// Assessment must exist and have remaining balance', '}', 'remainingBalance <= 0', 1],
    ['AssessmentPolicy.php:41-43', 'AssessmentPolicy.php:42-45', 'app/Policies/AssessmentPolicy.php',
        '// Enrollment must be evaluated (after dept evaluation)', '}',
        'EnrollmentStatus::Evaluated', 1],
    ['RegistrarController.php:144-146', 'RegistrarController.php:180-182',
        'app/Http/Controllers/Registrar/RegistrarController.php',
        'if (! $enrollment->evaluatedBy) {', '}', 'has not signed this load', 1],
    ['AssessmentController.php:409-429', 'AssessmentController.php:420-426',
        'app/Http/Controllers/Assessment/AssessmentController.php',
        '// Idempotency guard (mirrors compute): finalize is only valid from', '}',
        'EnrollmentStatus::Evaluated', 2],
    ['AssessmentController.php:245', 'AssessmentController.php:252-268',
        'app/Http/Controllers/Assessment/AssessmentController.php',
        'Studentscholarships::create([', ']);', 'totalScholarshipCoverage', 1],
];

$fails = 0;
$edits = [];

foreach ($plan as [$from, $to, $file, $firstMust, $lastMust, $insideMust, $expect]) {
    $count = substr_count($xml, $from);
    if ($count !== $expect) {
        fwrite(STDERR, "REFUSE: 「{$from}」 appears {$count} time(s), expected {$expect}\n");
        $fails++;

        continue;
    }

    $lines = $lines ??= [];
    $lines[$file] ??= file($root.'/'.$file);

    [, $range] = explode(':', $to, 2);
    $nums = array_map('intval', explode('-', $range));
    $a = $nums[0];
    $b = $nums[1] ?? $nums[0];

    $first = trim($lines[$file][$a - 1] ?? '');
    $last = trim($lines[$file][$b - 1] ?? '');
    $span = implode("\n", array_map('trim', array_slice($lines[$file], $a - 1, $b - $a + 1)));

    if ($first !== trim($firstMust)) {
        fwrite(STDERR, sprintf("REFUSE: %s line %d reads 「%s」, expected 「%s」\n", $to, $a, $first, trim($firstMust)));
        $fails++;

        continue;
    }
    if ($last !== trim($lastMust)) {
        fwrite(STDERR, sprintf("REFUSE: %s line %d reads 「%s」, expected 「%s」\n", $to, $b, $last, trim($lastMust)));
        $fails++;

        continue;
    }
    if (! str_contains($span, $insideMust)) {
        fwrite(STDERR, sprintf("REFUSE: %s does not contain 「%s」 — the sentence would cite the wrong code\n", $to, $insideMust));
        $fails++;

        continue;
    }

    $at = 0;
    for ($i = 0; $i < $expect; $i++) {
        $at = strpos($xml, $from, $at);
        $edits[] = [$at, strlen($from), $to, $from.' → '.$to];
        $at += strlen($from);
    }
    printf("proven  %-36s → %-38s contains 「%s」\n", $from, $to, $insideMust);
}

if ($fails > 0 || $edits === []) {
    printf("\n%d problem(s) — nothing was written\n", $fails);
    exit(1);
}

usort($edits, fn ($x, $y) => $y[0] <=> $x[0]);
$previousStart = PHP_INT_MAX;
foreach ($edits as $e) {
    if ($e[0] + $e[1] > $previousStart) {
        fwrite(STDERR, sprintf("REFUSE: edits overlap at %d and %d — one offset cannot carry two edits\n", $e[0], $previousStart));
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
    'the document still parses' => simplexml_load_string($xml) !== false,
];
// A rewritten number can be identical to a number this pass is *removing* elsewhere (`:149-151` becomes
// `:160-162`, which is also an old cite of the clinic row), so "the old string is gone" is only true up to
// the occurrences this pass legitimately introduces. Counting those is the difference between a guard and a
// coincidence.
$introduced = [];
foreach ($plan as [, $to, , , , , $expect]) {
    $introduced[$to] = ($introduced[$to] ?? 0) + $expect;
}

foreach ($plan as [$from, $to, , , , , $expect]) {
    $guards['old gone: '.$from] = substr_count($xml, $from) === ($introduced[$from] ?? 0);
    $guards['new present: '.$to] = substr_count($xml, $to) >= $expect;
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

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-13-pass111.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
