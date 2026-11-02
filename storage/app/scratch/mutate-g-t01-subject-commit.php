<?php

/**
 * Falsifies the two T01 cases in `tests/Feature/Evaluation/RetakeAttemptTest.php`.
 *
 * A case nobody has watched fail is decoration, and these two hold a property no earlier case covered: a subject
 * proposal writes the WHOLE line set inside one transaction, and a payload the server refuses leaves the existing
 * set alone. Each mutant attacks one of those and must make its named case go red; a non-compiling file or an
 * error page is reported BAD rather than counted as a kill. The target is restored by hash after every leg and the
 * class is re-run on the clean tree, so a harness that leaves a mutant behind cannot report success.
 *
 * Pest prints case names in human prose and colours the output, so the needles are written against the collapsed,
 * ANSI-stripped text rather than a remembered line.
 *
 * Usage: php storage/app/scratch/mutate-g-t01-subject-commit.php
 */
$root = dirname(__DIR__, 3);
chdir($root);

$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$target = 'app/Http/Controllers/Evaluation/EvaluationController.php';
$class = 'tests/Feature/Evaluation/RetakeAttemptTest.php';

foreach ([$php, $target, $class] as $f) {
    if (! is_file($f)) {
        fwrite(STDERR, "REFUSED: {$f} is not on disk.\n");
        exit(1);
    }
}

$before = (string) file_get_contents($target);
$hashBefore = sha1($before);

$legs = [
    [
        'name' => 'commit-inserts-only-the-first-line',
        'case' => 'a_multi_subject_commit_writes_every_line_it_names',
        'from' => "foreach (\$validated['subjects'] as \$subj) {",
        'to' => "foreach (array_slice(\$validated['subjects'], 0, 1) as \$subj) {",
        'needle' => 'must leave two proposed lines',
    ],
    [
        'name' => 'duplicate-payload-guard-disabled',
        'case' => 'a_refused_proposal_leaves_the_existing_load_in_place',
        'from' => "if (count(\$subjectIds) !== count(array_unique(\$subjectIds))) {",
        'to' => "if (false && count(\$subjectIds) !== count(array_unique(\$subjectIds))) {",
        'needle' => 'refused proposal leaves the existing load',
    ],
];

$collapse = static fn (string $s): string => trim(preg_replace('/\s+/u', ' ', (string) preg_replace('/\e\[[0-9;]*m/u', '', $s)));

$bad = 0;
foreach ($legs as $leg) {
    $found = substr_count($before, $leg['from']);
    if ($found !== 1) {
        printf("BAD  %-34s anchor occurs %d time(s) — the harness has aged, not the guard\n", $leg['name'], $found);
        $bad++;

        continue;
    }
    file_put_contents($target, str_replace($leg['from'], $leg['to'], $before));

    exec(escapeshellarg($php).' artisan test '.escapeshellarg($class).' --filter='.escapeshellarg($leg['case']).' 2>&1', $out, $code);
    $text = $collapse(implode("\n", $out));
    $out = [];

    $failed = (bool) preg_match('/Tests:.*?\d+ failed/iu', $text) || (bool) preg_match('/\bFAIL\b/i', $text);
    $named = str_contains($text, $leg['needle']);
    $ok = $failed && $named;
    printf("%s %-34s %s\n", $ok ? 'ok  ' : 'BAD ', $leg['name'], $ok
        ? 'named case went red on the mutant'
        : sprintf('failed=%s named=%s :: %s', $failed ? 'y' : 'n', $named ? 'y' : 'n', substr($text, -190)));
    if (! $ok) {
        $bad++;
    }

    file_put_contents($target, $before);
    if (sha1((string) file_get_contents($target)) !== $hashBefore) {
        fwrite(STDERR, "REFUSED: the restore did not reproduce the original bytes — repair {$target} by hand from this script's \$before.\n");
        exit(1);
    }
}

exec(escapeshellarg($php).' artisan test '.escapeshellarg($class).' 2>&1', $out2, $c2);
$clean = $collapse(implode("\n", $out2));
$green = str_contains($clean, '10 passed') && $c2 === 0;
printf("%s %-34s %s\n", $green ? 'ok  ' : 'BAD ', 'control-green-after-restore', $green ? 'class answers 10 passed again' : substr($clean, -190));
if (! $green) {
    $bad++;
}

printf("\n%s\n", $bad === 0 ? 'ALL KILLED: 2 leg(s), 0 bad, tree restored by hash.'
    : $bad.' leg(s) BAD — read them as the harness or the guard, never as a clean run.');
exit($bad === 0 ? 0 : 1);
