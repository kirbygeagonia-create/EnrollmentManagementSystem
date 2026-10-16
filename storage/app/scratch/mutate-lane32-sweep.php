<?php

/**
 * Mutation-proof for lane32-cite-meaning-sweep.php.
 *
 * The tool's whole claim is that it sees a citation whose range moved to a different construct while the
 * sentence still names the old one. That is only worth anything if the tool fails when the drift exists, so
 * each mutant below edits a COPY of the generated dump (never the docx) to move a real, currently-correct
 * citation onto a neighbouring method, and the sweep must name it. The unmutated control must print 0 for
 * that citation, or the mutant proves nothing about the mutation.
 *
 * usage: php storage/app/scratch/mutate-lane32-sweep.php
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$tmp = $root.'/storage/app/scratch/mut-lane32';
@mkdir($tmp, 0777, true);

$dump = file_get_contents($root.'/storage/app/scratch/docx-flat.txt');
$sweep = $root.'/storage/app/scratch/lane32-cite-meaning-sweep.php';

$run = function (string $label, string $text) use ($tmp, $php, $sweep): array {
    $file = $tmp.'/'.$label.'.txt';
    file_put_contents($file, $text);
    $out = [];
    $rc = 0;
    exec(escapeshellarg($php).' '.escapeshellarg($sweep).' '.escapeshellarg($file).' --verbose 2>&1', $out, $rc);

    return [implode("\n", $out), $rc];
};

/**
 * Each mutant: a literal piece of the dump to rewrite, and the symbol + file the sweep must then flag.
 * `stepsFor() (WorkflowService.php:49-80` is a citation that is correct today — the range carries the method
 * it names — so moving it onto `signStep`'s lines is exactly the drift class, with the sentence left saying
 * `stepsFor()`.
 */
$mutants = [
    'range-moved-to-other-method' => [
        'stepsFor() (WorkflowService.php:49-80',
        'stepsFor() (WorkflowService.php:182-215',
        ['stepsFor', 'WorkflowService.php:182-215'],
    ],
    'class-qualified-symbol-moved' => [
        'stepsFor() (WorkflowService.php:49-80',
        'WorkflowService::stepsFor() (WorkflowService.php:182-215',
        ['stepsFor', 'WorkflowService.php:182-215'],
    ],
    // The rule added after pass 120: a name may reach FORWARD through a parenthesis it opens —
    // `confirmClearance() records … ( :958-999 , permission … )`. Move that range and the sweep must still
    // name the drift, which it could not see before the rule existed.
    'parenthetical-label-moved' => [
        'last clause ( :958-999',
        'last clause ( :30-40',
        ['confirmClearance', 'EvaluationController.php:30-40'],
    ],
    'single-line-range-moved' => [
        'stepsFor() (WorkflowService.php:49-80',
        'stepsFor() (WorkflowService.php:244',
        ['stepsFor', 'WorkflowService.php:244-244'],
    ],
];

$fails = 0;
// The control is the PRISTINE DUMP run through the sweep — the anchor has to be looked for in the dump, not
// in the tool's output. Getting this backwards is what made the first version of this harness report
// "anchor not in the dump" three times while the anchor sat there in plain sight.
[$controlOut, $rc] = $run('control', $dump);

foreach ($mutants as $name => [$from, $to, $expect]) {
    if (substr_count($dump, $from) === 0) {
        printf("SKIP-FAIL %-28s the anchor `%s` is not in the dump, so this mutant tests nothing\n", $name, $from);
        $fails++;

        continue;
    }
    $mutated = str_replace($from, $to, $dump);
    [$out,] = $run($name, $mutated);

    $lines = explode("\n", $out);
    $hit = false;
    foreach ($lines as $i => $l) {
        if (str_contains($l, trim($expect[0])) && str_contains(implode(' ', array_slice($lines, $i, 4)), $expect[1])) {
            $hit = true;
            break;
        }
    }
    if ($hit) {
        printf("caught    %-28s %s → flagged at %s\n", $name, $expect[0], $expect[1]);
    } else {
        $fails++;
        printf("SURVIVED  %-28s expected a flag for %s at %s\n%s\n", $name, $expect[0], $expect[1],
            str_replace("\n", "\n          ", substr($out, 0, 500)));
    }
}

// And the control must not flag the citation the mutants only moved: same symbol, same file, different range.
$stillClean = true;
foreach (explode("\n", $controlOut) as $i => $l) {
    if (str_contains($l, 'stepsFor') && str_contains(implode(' ', array_slice(explode("\n", $controlOut), $i, 4)), 'WorkflowService.php:182-215')) {
        $stillClean = false;
    }
    // And it must not flag the citation pass 120 corrected by reaching FORWARD through a parenthesis:
    // `confirmClearance() records … ( :958-999 , permission … )`. If the pristine document flags it, the
    // attribution rule that makes that citation readable is not holding and no "caught" above means anything.
    if (str_contains($l, 'confirmClearance')) {
        $stillClean = false;
        echo "   (control flags confirmClearance — the parenthetical attribution rule is not holding)\n";
    }
}

printf("\ncontrol prints %s\n", (preg_match('/(\d+) citation range\(s\) checked against a named construct, (\d+) pair\(s\) flagged/', $controlOut, $m)
    ? $m[1].' pairs checked, '.$m[2].' flags' : 'no verdict line — the tool changed shape'));

if (! $stillClean) {
    $fails++;
    echo "CONTROL WRONG: the untouched dump already flags the range the mutants only moved, so a 'caught' above means nothing\n";
}

printf("%s\n", $fails === 0 ? 'ALL MUTANTS CAUGHT — the meaning sweep is live' : $fails.' mutant(s) survived — do not trust its shortlist');
exit($fails === 0 ? 0 : 1);
