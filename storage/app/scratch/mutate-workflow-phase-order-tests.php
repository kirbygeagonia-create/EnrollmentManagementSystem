<?php

/**
 * Falsifier for the workflow phase vocabulary.
 *
 * One planted defect per promise in `tests/Feature/Workflow/WorkflowPhaseIndicatorTest.php`,
 * each of which must make ITS OWN named case report a failure. A leg that cannot fail is
 * decoration, so every plant is verified on disk before the run is read, and every file is
 * restored by hash in a `finally` — a half-restored tree makes every later verdict a lie.
 *
 *   php storage/app/scratch/mutate-workflow-phase-order-tests.php
 */

$root = dirname(__DIR__, 3);
chdir($root);

$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$test = 'tests/Feature/Workflow/WorkflowPhaseIndicatorTest.php';

$targets = [
    $test,
    'app/Http/Middleware/HandleInertiaRequests.php',
    'resources/js/Components/ui/WorkflowStepper.jsx',
    'resources/js/Components/ui/StepProgress.jsx',
    'resources/js/Pages/Registrar/Index.jsx',
    'resources/js/Pages/Clinic/Index.jsx',
    'resources/js/Pages/Clinic/Show.jsx',
    'resources/js/Layouts/AuthenticatedLayout.jsx',
    'resources/js/Components/navigation/MegaAppLauncher.jsx',
];

$originals = [];

foreach ($targets as $rel) {
    $path = $root.'/'.$rel;

    if (! is_file($path)) {
        fwrite(STDERR, "REFUSED: {$rel} is not a file this harness can read.\n");
        exit(1);
    }

    $originals[$rel] = file_get_contents($path);
}

$restore = static function () use ($originals, $root): void {
    foreach ($originals as $rel => $bytes) {
        file_put_contents($root.'/'.$rel, $bytes);
    }
};

register_shutdown_function($restore);

/** Run one case and report whether it failed, with the count the runner printed. */
$runCase = static function (string $case) use ($php, $root): array {
    $cmd = escapeshellarg($php).' artisan test '.escapeshellarg($root.'/tests/Feature/Workflow/WorkflowPhaseIndicatorTest.php')
        .' --filter '.escapeshellarg($case).' 2>&1';

    $out = shell_exec($cmd);

    if ($out === null || $out === '') {
        return ['status' => 'NO OUTPUT', 'text' => ''];
    }

    if (str_contains($out, 'No tests found')) {
        return ['status' => 'NO TEST SELECTED', 'text' => ''];
    }

    // Pest writes ANSI colour codes into captured output, and the tally line is styled — so
    // the codes come out before anything is read, or the harness's verdict would depend on
    // whether the caller's shell had a TERM.
    $out = preg_replace('/\e\[[0-9;]*m/', '', $out);
    preg_match('/Tests:\s+([^\n]+)/', $out, $tally);

    return [
        'status' => str_contains($out, '1 failed') ? 'FAILED' : 'PASSED',
        'text' => $tally[1] ?? 'no Tests: line',
        'full' => $out,
    ];
};

$cases = [
    'order' => 'every_desk_page_is_given_the_phase_vocabulary_the_stepper_prints',
    'stepper' => 'the_stepper_numbers_the_boxes_the_record_holds_not_the_seven_the_form_declares',
    'typed' => 'no_surface_spells_a_workflow_phase_number_by_hand',
    'unknown' => 'every_phase_box_word_a_surface_names_is_a_box_the_workflow_declares',
];

$legs = [];

// Control: the delivered tree must be green before anything is planted.
foreach ($cases as $name => $case) {
    $result = $runCase($case);

    if ($result['status'] !== 'PASSED') {
        fwrite(STDERR, "REFUSED: the control leg '{$name}' did not pass ({$result['status']} — {$result['text']}). Nothing is safe to plant.\n");
        if ($name === 'typed') {
            echo substr($result['full'] ?? '', 0, 2000)."\n";
        }
        exit(1);
    }
}

echo "CONTROL: all four cases green.\n";

/**
 * Plant one defect, assert every part of it landed, assert the named case goes red, restore.
 *
 * A leg may need more than one file: a claim spread over two desk pages cannot be retired by
 * editing one of them, and a leg that reports "the guard slept" for a defect it never put on
 * the disk is the harness's own age, not a finding.
 *
 * @param  list<array{0: string, 1: string, 2: string, 3?: int}>  $edits
 */
$plant = static function (string $leg, array $edits, string $expect) use ($root, $originals, $restore, $runCase, &$legs): void {
    $mutants = [];

    foreach ($edits as [$rel, $old, $new, $occurrences]) {
        $occurrences ??= 1;
        $source = $originals[$rel] ?? null;

        if ($source === null) {
            $legs[] = "BAD   {$leg}: {$rel} is not a file this harness snapshotted";

            return;
        }

        $found = substr_count($source, $old);

        if ($found !== $occurrences) {
            $legs[] = "BAD   {$leg}: the anchor in ".basename($rel)." matched {$found} time(s), expected {$occurrences} — the harness has aged, not the guard";

            return;
        }

        $mutants[$rel] = str_replace($old, $new, $source);

        if ($mutants[$rel] === $source) {
            $legs[] = "BAD   {$leg}: the plant in ".basename($rel).' changed no bytes';

            return;
        }
    }

    foreach ($mutants as $rel => $bytes) {
        file_put_contents($root.'/'.$rel, $bytes);

        if (file_get_contents($root.'/'.$rel) !== $bytes) {
            $legs[] = "BAD   {$leg}: the plant did not reach ".basename($rel);
            $restore();

            return;
        }
    }

    $result = $runCase($expect);

    if ($result['status'] === 'FAILED') {
        $legs[] = "killed {$leg} → ".implode(' + ', array_map('basename', array_keys($mutants)))." trips {$expect} ({$result['text']})";
    } else {
        $legs[] = "SURVIVED {$leg}: the planted bytes are on disk and the case answered {$result['status']} ({$result['text']})";
    }

    $restore();
};

// 1. The shared prop goes back to a map keyed by office id. This is the defect the owner
//    reported: JavaScript re-sorts those keys ascending, so Registrar's box paints first.
$plant(
    'prop-returned-to-an-office-keyed-map',
    [['app/Http/Middleware/HandleInertiaRequests.php', "'workflowPhases' => WorkflowService::boxOrder(),", "'workflowPhases' => WorkflowService::stepLabels(),", 1]],
    $cases['order']
);

// 2. The tracker numbers the canonical list instead of the record's own boxes, and a
//    continuing student's third box would print a 4.
$plant(
    'stepper-numbers-the-canonical-list',
    [['resources/js/Components/ui/WorkflowStepper.jsx', 'position: positions.get(officeId) ?? null,', 'position: index + 1,', 1]],
    $cases['stepper']
);
$plant(
    'stepper-enumerates-a-map-again',
    [['resources/js/Components/ui/WorkflowStepper.jsx', 'const steps = boxes.map((box) => {', 'const steps = Object.entries(workflowPhases).map((box) => {', 1]],
    $cases['stepper']
);
$plant(
    'progress-circle-reprints-its-own-index',
    [['resources/js/Components/ui/StepProgress.jsx', 'const number = step.number ?? index + 1;', 'const number = index + 1;', 1]],
    $cases['stepper']
);

// 3. A desk page spells its phase number again, disagreeing with the tracker under it.
$plant(
    'desk-page-types-its-phase-again',
    [['resources/js/Pages/Registrar/Index.jsx', 'phaseBox="Registrar Approval" phaseBadge="Registrar Central Desk"', 'phaseBadge="Phase 5 · Registrar Central Desk"', 1]],
    $cases['typed']
);

// 4. A chip names a box the workflow does not declare, so it prints no phase at all.
$plant(
    'chip-names-a-box-that-does-not-exist',
    [['resources/js/Pages/Clinic/Index.jsx', 'phaseBox="Clinic"', "phaseBox='Clini'", 1]],
    $cases['unknown']
);

// 5. A box loses every chip that names it: the workflow still declares seven, the chrome
//    numbers six. Both clinic surfaces carry the word, so one edit cannot retire the claim —
//    this is the leg that fails when the plant is smaller than the promise.
$plant(
    'one-box-loses-its-desk-chip',
    [
        ['resources/js/Pages/Clinic/Index.jsx', 'phaseBox="Clinic" phaseBadge="Clinic Desk"', 'phaseBadge="Clinic Desk"', 1],
        ['resources/js/Pages/Clinic/Show.jsx', 'phaseBox="Clinic" phaseBadge="Clinic Examination"', 'phaseBadge="Clinic Examination"', 1],
        ['resources/js/Layouts/AuthenticatedLayout.jsx', "phaseBox: 'Clinic',", "stage: 'Clinic Examination',", 1],
        ['resources/js/Components/navigation/MegaAppLauncher.jsx', "phaseBox: 'Clinic',", "stage: 'School Clinic',", 1],
    ],
    $cases['unknown']
);

$bad = 0;

foreach ($legs as $line) {
    echo $line."\n";

    if (! str_starts_with($line, 'killed')) {
        $bad++;
    }
}

// The tree must be back exactly as it was before the verdict is worth anything.
$drifted = [];

foreach ($originals as $rel => $bytes) {
    if (file_get_contents($root.'/'.$rel) !== $bytes) {
        $drifted[] = $rel;
    }
}

if ($drifted !== []) {
    echo 'BAD   the tree did not restore: '.implode(', ', $drifted)."\n";
    $bad++;
}

printf("%s: %d leg(s), %d bad.\n", $bad === 0 ? 'ALL KILLED' : 'NOT PROVABLE', count($legs), $bad);

$restore();
// The shutdown restore is the last writer, so the exit code is decided here.
exit($bad === 0 ? 0 : 1);
