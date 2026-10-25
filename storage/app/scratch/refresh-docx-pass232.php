<?php

/**
 * Lane 70, pass 232: the WorkflowService cluster, read against the code.
 *
 * Four rows cite `stepsFor()` and `signStep()`, and three of them are wrong in the two shapes this lane has
 * learned to expect. (a) A range whose endpoints straddle the closing brace of the construct it names:
 * `WorkflowService.php:49-80` and `app/Services/WorkflowService.php:52-83` both walk off the end of
 * `stepsFor()` (52-76) into `createWorkflow()`'s docblock, so every gate that only asks "do these lines
 * exist, and in the right file?" clears them. (b) `WorkflowService.php:208-297 (box list)` — 208-297 is
 * `signStep()`, which holds no box list; the box array and its removal are `stepsFor()` at :56-71.
 * (c) A claim, not a pointer: §13's "One workflow form per enrollment" row and §18.6's workflow row both
 * state that `createWorkflow()` is called once, from the Evaluation signature, and that the row is
 * therefore not created at enrollment creation. `app/Services/EnrollmentIssuer.php:75` calls it inside
 * `issue()`, immediately after `Enrollments::create` at :73, and `EvaluationController.php:803` calls it
 * behind `if (! $enrollment->enrollmentworkflow)` — two callers, and the one-form rule rests on that
 * existence check rather than on a single call site. The evidence cell even points at :532-535 and
 * :466-485, which are `decideStanding()`'s validation and `captureProfile()`'s address loop.
 *
 * The call-site census is measured, not recalled: the script refuses unless `app/` answers exactly two
 * `createWorkflow(` call sites, and asserts every line each new number claims before it will write.
 * The `:208-297 (box list)` cell and the `(the only createWorkflow call)` label go through the cell kind,
 * so this run only prints the runs that hold them.
 */
$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$tmp = $root.'/storage/app/scratch/pass232-backup.docx';
require $root.'/storage/app/scratch/docx-edit-engine.php';

$lines = static function (string $file): array {
    return explode("\n", (string) file_get_contents($file));
};

$wf = $lines($root.'/app/Services/WorkflowService.php');
$iss = $lines($root.'/app/Services/EnrollmentIssuer.php');
$ev = $lines($root.'/app/Http/Controllers/Evaluation/EvaluationController.php');

$assertions = [
    ['WorkflowService.php', 52, 'stepsFor', $wf],
    ['WorkflowService.php', 76, '}', $wf],
    ['WorkflowService.php', 56, '$boxes', $wf],
    ['WorkflowService.php', 71, '}', $wf],
    ['WorkflowService.php', 261, 'step order', $wf],
    ['WorkflowService.php', 255, 'maySignAcrossOffices', $wf],
    ['EnrollmentIssuer.php', 73, 'Enrollments::create', $iss],
    ['EnrollmentIssuer.php', 75, 'createWorkflow', $iss],
    ['EvaluationController.php', 801, 'Create workflow if not exists', $ev],
    ['EvaluationController.php', 802, '! $enrollment->enrollmentworkflow', $ev],
    ['EvaluationController.php', 803, 'createWorkflow', $ev],
    ['EvaluationController.php', 808, 'signStepByOffice', $ev],
    ['EvaluationController.php', 796, 'DB::transaction', $ev],
    ['EvaluationController.php', 809, '}', $ev],
];

$fails = 0;
foreach ($assertions as [$file, $no, $needle, $rows]) {
    $actual = trim((string) ($rows[$no - 1] ?? ''));
    if (! str_contains($actual, $needle)) {
        echo "ASSERT FAILED  {$file}:{$no} must carry [{$needle}] — reads [{$actual}]\n";
        $fails++;
    }
}

// The old pointers must be provably wrong targets, or this pass has no business re-pointing them.
foreach ([['EvaluationController.php', 532], ['EvaluationController.php', 535], ['EvaluationController.php', 466], ['EvaluationController.php', 485]] as [$file, $no]) {
    $actual = trim((string) ($ev[$no - 1] ?? ''));
    if (str_contains($actual, 'createWorkflow')) {
        echo "REFUSED: {$file}:{$no} does carry createWorkflow — the old pointer was right\n";
        $fails++;
    }
}

$calls = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app', FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    foreach (explode("\n", (string) file_get_contents($file->getPathname())) as $i => $line) {
        if (str_contains($line, '->createWorkflow(') || str_contains($line, '::createWorkflow(')) {
            $calls[] = $file->getFilename().':'.($i + 1);
        }
    }
}
$calls = array_values(array_unique($calls));
sort($calls);
if (count($calls) !== 2) {
    echo 'REFUSED: the createWorkflow( call-site census in app/ answers '.count($calls)." — [$calls] — and this pass's wording asserts two\n";
    exit(1);
}
echo 'call sites measured: '.implode(', ', $calls)."\n";
if ($fails > 0) {
    echo "REFUSED: {$fails} assertion(s) failed — no number written\n";
    exit(1);
}

$edits = [
    // (a) stepsFor() is 52-76; both ranges ran into createWorkflow()'s docblock.
    [':49-80', ':52-76', null, 2],
    [':52-83', ':52-76'],
    // (c) the claim, in both rows that carry it.
    ['when the evaluator signs, not at enrollment creation',
        'at the Evaluation signature only when the record has none, and at issue by the desk that creates the enrollment',
    ],
    ['exactly one caller', 'two callers'],
    ['which is called once, from the Evaluation signature',
        'which has two callers: the desk issuer, with the enrollment row, and the Evaluation signature when the record has none',
    ],
    // (c) the pointers that named the wrong block, one row at a time.
    [':532-535', ':801-804'],
    [':466-485', ':796-809'],
    // §13's step row: located by its own `box 1 signed`, because the phrase also sits in prose elsewhere.
    ['not at enrollment creation', 'only when the record has none, the desk issue having made it already', 'box 1 signed'],
];

$apply = in_array('--apply', $argv, true);
if (! copy($src, $tmp)) {
    echo "REFUSED: no backup at {$tmp}\n";
    exit(1);
}

docxApply($src, $tmp, $edits, [], $apply);

// Print the runs this pass could not reach with a text needle, for the cell-kind half.
$xml = (string) file_get_contents($tmp);
if ($apply) {
    $xml = (string) file_get_contents($src);
}
preg_match_all('/<w:t(?:\s[^>]*)?>(.*?)<\/w:t>/s', $xml, $m);
foreach ($m[1] as $i => $t) {
    $plain = html_entity_decode($t, ENT_QUOTES | ENT_XML1);
    if (preg_match('/box list|createWorkflow|WorkflowService\.php:208-297/i', $plain)) {
        printf("RUN %d: [%s]\n", $i, $plain);
    }
}

echo $apply ? "APPLIED\n" : "DRY RUN — nothing written\n";
