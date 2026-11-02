<?php

/**
 * Falsifier for `lane195-count-guide-answers.php`.
 *
 * The counter reported EVERY ITEM ANSWERED on its first run. In this repository a new instrument's
 * first green is the shape that must be broken before it is believed — a checker whose population is
 * read from the wrong construct, or whose answer test compares an empty string, prints exactly the
 * verdict a complete answer file does. So each leg plants one defect the counter is supposed to catch,
 * into a **copy** of the answer file (the tree is never mutated), and requires the named complaint in
 * the output **and** exit 1. A leg whose needle matches nothing reports BAD rather than passing, and
 * the control runs first because a planted copy must never poison a later measurement.
 *
 * Three legs judge the instrument's own claims rather than its findings: the guide-side truncation
 * (a census that reads 23 problems must REFUSE, not report a clean 23-problem census), the empty answer
 * file (a run that read nothing must not say "answered"), and the unknown flag (an accepted-and-inert
 * flag is how a scoped run looks like a clean one).
 *
 * Usage: php storage/app/scratch/mutate-lane195-guide-answers.php
 */
$root = dirname(__DIR__, 3);
chdir($root);

$php = '"C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe"';
$tool = 'storage/app/scratch/lane195-count-guide-answers.php';
$answersPath = 'Documentation/GUIDE-PROBLEM-ANSWERS-ADS.md';
$guidePath = 'storage/app/scratch/ads-guide-flat.txt';
$base = file_get_contents($answersPath);
$guide = file_get_contents($guidePath);
if ($base === false || $guide === false) {
    fwrite(STDERR, "REFUSED: neither the answer file nor the guide dump could be read.\n");
    exit(1);
}

$copies = [];
register_shutdown_function(function () use (&$copies) {
    foreach ($copies as $f) {
        @unlink($f);
    }
});

$scratch = 'storage/app/scratch';
$plant = static function (string $slug, string $text) use (&$copies, $scratch): string {
    $path = $scratch.'/lane195-mutant-'.$slug.'.md';
    file_put_contents($path, $text);
    $copies[] = $path;

    return $path;
};

$run = static function (array $args) use ($php, $tool): array {
    $lines = [];
    $code = 0;
    exec($php.' '.$tool.' '.implode(' ', $args).' 2>&1', $lines, $code);

    return [implode("\n", $lines), $code];
};

/** Replace once, or the leg is a leg about nothing. */
$edit = static function (string $text, string $from, string $to, string $slug, int $expect = 1): string {
    $hits = substr_count($text, $from);
    if ($hits !== $expect) {
        throw new RuntimeException("{$slug}: needle matched {$hits} time(s), expected {$expect}: ".mb_substr($from, 0, 60));
    }

    return str_replace($from, $to, $text);
};

$results = [];
$bad = 0;

// 1. the control — byte-identical copy, must read clean.
$control = $plant('control', $base);
[$out, $code] = $run(['--answers='.$control]);
if ($code === 0 && str_contains($out, 'EVERY ITEM ANSWERED')) {
    $results[] = 'ok       control            a byte-identical copy reads clean';
} else {
    $results[] = 'BAD      control            exit '.var_export($code, true).' — '.mb_substr($out, 0, 200);
    $bad++;
}

$legs = [
    // slug, mutation closure, required complaint label, what the leg proves
    ['question-dropped', static fn ($t) => $edit($t, "**Q1.1** What should happen to the other successfully processed subjects?\n",
        '', 'question-dropped'), 'QUESTION-MISSING', 'a guide question with no Q line is a miss, not a silence'],
    // The whole paragraph must go: the counter measures the wrapped paragraph, so replacing one
    // sentence inside it leaves a long answer and the leg can never fire — which is how this leg read
    // as a survivor on the first run. A leg that cannot fail is the harness's age, not a defect.
    ['answer-short', static fn ($t) => $edit($t, "**Answer:** they are undone with the failed one. Nothing in this system commits a subject\nindividually, so there is no partially-saved load to reason about; the enrollment keeps exactly the\nlines it held before the act began, which is what T01's second case asserts.",
        '**Answer:** yes.', 'answer-short'), 'ANSWER-SHORT', 'an answer of two words is not an answer'],
    ['box-count', static fn ($t) => $edit($t, "**Concept involved:** derived state versus stored state, consistency, aggregate weighing.\n**Applicability:** ☑ Applies to our system ☐ Partially applies",
        "**Concept involved:** derived state versus stored state, consistency, aggregate weighing.\n**Applicability:** ☑ Applies to our system ☑ Partially applies", 'box-count'),
        'BOX-COUNT', 'two checked boxes fail the guide, which asks for one'],
    ['disagrees-register', static fn ($t) => $edit($t, '**Applicability:** ☐ Applies to our system ☑ Partially applies ☐ N/A',
        '**Applicability:** ☑ Applies to our system ☐ Partially applies ☐ N/A', 'disagrees-register', 3),
        'DISAGREES-REGISTER', 'a verdict that drifts from the P register is named, not averaged'],
    ['block-missing', static fn ($t) => $edit($t, '### Problem 7 — Database Deadlock',
        '### Problem 7 — Deadlocks inside the engine', 'block-missing'), 'MISSING-BLOCK', 'a block whose heading loses the guide own title is missing'],
    ['invented-word', static fn ($t) => $edit($t, '**Row 1 — Transactions.** ☑ Implemented',
        '**Row 1 — Transactions.** ☑ Automated', 'invented-word'), 'INVENTED-WORD', 'a status word the guide row does not offer is refused'],
    ['concept-unmarked', static fn ($t) => $edit($t, '**Row 5 — Locking.** ☑ Implemented',
        '**Row 5 — Locking.** ☐ Implemented', 'concept-unmarked'), 'CONCEPT-UNMARKED', 'a concept row that checks nothing is reported'],
    ['subitem-missing', static fn ($t) => $edit($t, '*Primary keys and foreign keys:*',
        '*The keys:*', 'subitem-missing'), 'SUBITEM-MISSING', 'a presentation sub-item renamed is a sub-item unanswered'],
    ['no-workflow-line', static fn ($t) => $edit($t, '**Workflow difference:** the guide says requests',
        '**Difference in the workflow, roughly:** the guide says requests', 'no-workflow-line'),
        'NO-WORKFLOW-LINE', 'the guide N/A line has to be answered under its own label'],
    ['verb-missing', static fn ($t) => $edit($t, 'Provide evidence that the solution works, such as screenshots, SQL output, logs, or query plans.',
        'Evidence.', 'verb-missing'), 'VERB-MISSING', 'one of the six task verbs dropped is a task verb unanswered'],
    ['defense-missing', static fn ($t) => $edit($t, 'Be prepared to support your answer with your database design, SQL or configuration, test cases, and',
        'Support it however you like, with things.', 'defense-missing'), 'DEFENSE-MISSING', 'the defense sentences are each quoted and each judged'],
    ['fillin-blank', static fn ($t) => $edit($t, '**Student 1:** Kirby A. Geagonia', '**Student 1:**', 'fillin-blank'),
        'FILLIN-BLANK', 'a fill-in with no value is not filled in'],
];

foreach ($legs as [$slug, $mutate, $label, $promise]) {
    try {
        $text = $mutate($base);
    } catch (RuntimeException $e) {
        $results[] = 'BAD      '.$slug.'  '.$e->getMessage();
        $bad++;

        continue;
    }
    if ($text === $base) {
        $results[] = "BAD      {$slug}  the mutation left the bytes identical";
        $bad++;

        continue;
    }
    $path = $plant($slug, $text);
    [$out, $code] = $run(['--answers='.$path]);
    if ($code === 1 && str_contains($out, $label)) {
        $results[] = "ok       {$slug}  {$label} — {$promise}";
    } else {
        $results[] = 'BAD      '.$slug.'  wanted '.$label.' with exit 1, got exit '.var_export($code, true)
            .' and: '.mb_substr(str_replace("\n", ' / ', $out), 0, 180);
        $bad++;
    }
}

// guide-side: a truncated guide must REFUSE rather than judge 23 problems.
$trunc = $guide;
foreach (preg_split('/\n/', $guide) ?: [] as $l) {
    if (str_starts_with(trim($l), 'Problem 24 ')) {
        break;
    }
}
$cutPos = strpos($guide, 'Problem 24 —');
if ($cutPos === false) {
    $results[] = 'BAD      guide-truncated  the guide dump no longer holds Problem 24';
    $bad++;
} else {
    $gpath = $scratch.'/lane195-mutant-guide.txt';
    file_put_contents($gpath, substr($guide, 0, $cutPos));
    $copies[] = $gpath;
    [$out, $code] = $run(['--guide='.$gpath]);
    if ($code === 1 && str_contains($out, 'REFUSED') && str_contains($out, 'not 24')) {
        $results[] = 'ok       guide-truncated  REFUSED — a census of 23 problems is a refusal, not a verdict';
    } else {
        $results[] = 'BAD      guide-truncated  exit '.var_export($code, true).' — '.mb_substr($out, 0, 160);
        $bad++;
    }
}

// an answer file that holds nothing must not read as answered.
$empty = $plant('empty', '');
[$out, $code] = $run(['--answers='.$empty]);
if ($code === 1 && str_contains($out, 'MISSING-BLOCK') && ! str_contains($out, 'EVERY ITEM ANSWERED')) {
    $results[] = 'ok       answers-empty    an empty answer file reports 24 missing blocks';
} else {
    $results[] = 'BAD      answers-empty  exit '.var_export($code, true).' — '.mb_substr($out, 0, 160);
    $bad++;
}

// an unknown flag is refused by name, not accepted and ignored.
[$out, $code] = $run(['--nonsense=1']);
if ($code === 1 && str_contains($out, 'not read by this tool') && str_contains($out, '--nonsense')) {
    $results[] = 'ok       unknown-flag     refused by name';
} else {
    $results[] = 'BAD      unknown-flag  exit '.var_export($code, true).' — '.mb_substr($out, 0, 160);
    $bad++;
}

// a guide path that does not exist is refused with the path quoted.
[$out, $code] = $run(['--guide=storage/app/scratch/no-such-guide-dump.txt']);
if ($code === 1 && str_contains($out, 'REFUSED') && str_contains($out, 'no-such-guide-dump.txt')) {
    $results[] = 'ok       missing-guide    refused, naming the path';
} else {
    $results[] = 'BAD      missing-guide  exit '.var_export($code, true).' — '.mb_substr($out, 0, 160);
    $bad++;
}

// the control again, after everything planted, over a clean tree.
[$out, $code] = $run(['--answers='.$control]);
if ($code === 0 && str_contains($out, 'EVERY ITEM ANSWERED')) {
    $results[] = 'ok       control-again    the tree reads clean after every plant';
} else {
    $results[] = 'BAD      control-again  exit '.var_export($code, true);
    $bad++;
}

foreach ($results as $r) {
    echo $r."\n";
}
$legs = count($results);
echo $bad === 0
    ? "ALL KILLED: {$legs} leg(s), 0 bad\n"
    : "{$bad} of {$legs} leg(s) BAD — the counter cannot fail the way this harness expects.\n";
exit($bad === 0 ? 0 : 1);
