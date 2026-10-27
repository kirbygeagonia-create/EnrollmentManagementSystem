<?php

/**
 * The two EvaluationController pointers the byte shift withheld, taken by reading.
 *
 * `lane76-shift-cites.php` re-pointed 61 number tokens into `EvaluationController.php` for this batch's
 * +15-line growth, then refused two items rather than guess:
 *
 *   - `( :550 )` — a bare continuation. Its HEAD text appears at two live lines, 565 and 608, both
 *     `evaluatedBy => Auth::user()->userId`. The sentence says "evaluatedBy written by profile
 *     capture", and 565 is inside `captureProfile()` while 608 is inside `decideStanding()`, so the
 *     uniform +15 answer is 565 and the other is a live line belonging to something else — the exact
 *     shape only a read can settle.
 *   - `1120-1161` — cited twice, once prefixed and once as `( :1120-1161 under
 *     evaluation.clearance.confirm )`, which the attribution window gave to `EnrollmentReadiness.php`.
 *     Both copies mean `confirmClearance()`, and the shift's own range list resolved it to
 *     1135-1176; live line 1135 is that method's signature and 1176 its closing brace.
 *
 * Each rewrite is asserted against the line it will land on before anything is written.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass257.php [--apply]
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/pass257-backup-'.date('Ymd-His').'.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

$live = file_get_contents($root.'/app/Http/Controllers/Evaluation/EvaluationController.php');
$lines = explode("\n", $live);

$assertions = [
    565 => ['evaluatedBy', "captureProfile writes the evaluator on the enrollment"],
    550 => ['Addresses::updateOrCreate', 'the OLD number now lands on the address write, not the evaluatedBy line the sentence claims'],
    608 => ['evaluatedBy', 'the other HEAD-window match: decideStanding also writes evaluatedBy, and this pointer does not name it'],
    1135 => ['public function confirmClearance', 'the range both copies claim'],
    1176 => ['}', 'the method close'],
];

foreach ($assertions as $number => [$mustContain, $why]) {
    $text = trim($lines[$number - 1] ?? '');

    if (! str_contains($text, $mustContain)) {
        fwrite(STDERR, "REFUSED: line {$number} reads '{$text}', expected to contain {$mustContain} — {$why}\n");

        exit(1);
    }

    printf("ok   EvaluationController.php:%-5d %s\n", $number, $text);
}

docxApply(
    $docx,
    $backup,
    [
        [':550', ':565', 'written by profile capture', 1],
        ['1120-1161', '1135-1176', null, 2],
    ],
    [],
    $apply
);
