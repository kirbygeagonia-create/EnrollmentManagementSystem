<?php

/**
 * Retire the hand-typed phase numbers in the three navigation surfaces.
 *
 * The desk pill in the header, the desk launcher's card chips and the sub-navigation's
 * per-desk table each spelled the same offices `Phase 0` … `Phase 8`. Those numbers came from
 * the old module list, not from the enrollment workflow: the Registrar's box is the fourth of
 * the seven the form declares, and the tracker under the header said so. Each surface now
 * names the BOX it works and takes its number from the shared `workflowPhases` list; a desk
 * with no box (Admissions, the entrance-exam lab, campus clearance) states its stage and claims
 * no phase.
 *
 * Dry run by default; `--apply` writes. Every substitution asserts its own count and the run
 * refuses while any `Phase <digit>` survives in the three files.
 */

$root = dirname(__DIR__, 3);
$apply = in_array('--apply', $argv, true);
$failures = [];
$notes = [];

/** The chrome's old number → the box it really works, or the stage word when it works none. */
$translation = [
    "phase: 'Phase 0'," => "stage: 'Admissions Intake',",
    "phase: 'Phase 0.5'," => "stage: 'Entrance Examination',",
    "phase: 'Phase 0.5 · Entrance'," => "stage: 'Entrance Examination',",
    "phase: 'Phase 1'," => "stage: 'Campus Clearance',",
    "phase: 'Phase 2'," => "phaseBox: 'Department Evaluation',",
    "phase: 'Phase 3'," => "phaseBox: 'Assessment',",
    "phase: 'Phase 4'," => "phaseBox: 'Accounting Payment',",
    "phase: 'Phase 5'," => "phaseBox: 'Registrar Approval',",
    "phase: 'Phase 6'," => "phaseBox: 'Blocking and Scheduling',",
    "phase: 'Phase 7'," => "phaseBox: 'Clinic',",
    "phase: 'Phase 8'," => "phaseBox: 'ID Validation',",
    "phase: 'Student Info'," => "stage: 'Student Info',",
    "phase: 'Admin Suite'," => "stage: 'Admin Suite',",
    "phase: 'Overview'," => "stage: 'Overview',",
    "phase: 'Full Lifecycle'," => "stage: 'Full Lifecycle',",
    "phase: 'Pipeline Integrity'," => "stage: 'Pipeline Integrity',",
    "phase: 'Admin Hub'," => "stage: 'Admin Hub',",
    "phase: 'RBAC'," => "stage: 'RBAC',",
    "phase: 'Compliance'," => "stage: 'Compliance',",
];

$files = [
    'resources/js/Layouts/AuthenticatedLayout.jsx',
    'resources/js/Components/navigation/MegaAppLauncher.jsx',
    'resources/js/Components/navigation/DeskSubNav.jsx',
];

$contents = [];

foreach ($files as $rel) {
    if (! is_file($root.'/'.$rel)) {
        $failures[] = "MISSING FILE {$rel}";
        continue;
    }

    $contents[$rel] = file_get_contents($root.'/'.$rel);
}

// 1. DeskSubNav keeps no phase field at all: the component renders tabs only, so its typed
//    numbers were a claim nothing painted and everything read.
$subNavLines = explode("\n", $contents['resources/js/Components/navigation/DeskSubNav.jsx']);
$kept = [];
$dropped = 0;

foreach ($subNavLines as $line) {
    if (preg_match("/^\s*phase: '(?:Phase [0-9.]+(?: · [A-Za-z]+)?|Student Services|Admin Suite)',\s*$/", $line)) {
        $dropped++;
        continue;
    }

    $kept[] = $line;
}

if ($dropped !== 12) {
    $failures[] = "DeskSubNav: expected 12 phase fields to drop, matched {$dropped}";
} else {
    $contents['resources/js/Components/navigation/DeskSubNav.jsx'] = implode("\n", $kept);
    $notes[] = "DeskSubNav: dropped {$dropped} unused phase fields";
}

// 2. The other two surfaces translate their typed number into a box name or a stage word.
//    Every `phase:` field the file held must be accounted for, or the run refuses: a field
//    left named `phase` would render nothing once the pill reads `stage`/`phaseBox`.
foreach (['resources/js/Layouts/AuthenticatedLayout.jsx', 'resources/js/Components/navigation/MegaAppLauncher.jsx'] as $rel) {
    preg_match_all("/phase: '([^']*)',/", $contents[$rel], $before);
    $held = count($before[1]);
    $applied = 0;

    foreach ($translation as $old => $new) {
        $found = substr_count($contents[$rel], $old);

        if ($found === 0) {
            continue;
        }

        $contents[$rel] = str_replace($old, $new, $contents[$rel]);
        $applied += $found;
        $notes[] = basename($rel).": {$found} × '{$old}' → '{$new}'";
    }

    if ($applied !== $held) {
        $failures[] = basename($rel).": held {$held} phase field(s) but translated {$applied} — a spelling the table does not know";
    }

    if (substr_count($contents[$rel], "phase: '") > 0) {
        $failures[] = basename($rel).': a phase field survived the translation';
    }
}

// 3. The header's desk pill resolves its phase from the shared list.
$pill = <<<'JS'
    // The pill names this desk's box on the enrollment form, numbered from the shared
    // `workflowPhases` list. A desk that holds no box shows its own stage word instead,
    // because a number invented here is how the header and the tracker came to disagree.
    const deskPhase = workflowPhaseLabel(useWorkflowBoxes(), currentDesk.phaseBox) ?? currentDesk.stage;
JS;

$pillOld = "    const currentDesk = deskInfoMap[activeKey] || deskInfoMap.dashboard;";

if (substr_count($contents['resources/js/Layouts/AuthenticatedLayout.jsx'], $pillOld) !== 1) {
    $failures[] = 'AuthenticatedLayout: could not locate the currentDesk line to anchor the phase resolution';
} else {
    $contents['resources/js/Layouts/AuthenticatedLayout.jsx'] = str_replace(
        $pillOld,
        $pillOld."\n".$pill,
        $contents['resources/js/Layouts/AuthenticatedLayout.jsx']
    );
    $contents['resources/js/Layouts/AuthenticatedLayout.jsx'] = str_replace(
        '                                    {currentDesk.phase}',
        '                                    {deskPhase}',
        $contents['resources/js/Layouts/AuthenticatedLayout.jsx']
    );
    $contents['resources/js/Layouts/AuthenticatedLayout.jsx'] = str_replace(
        "import { Toast, CauseEffectModal } from '@/Components/ui';",
        "import { Toast, CauseEffectModal, useWorkflowBoxes, workflowPhaseLabel } from '@/Components/ui';",
        $contents['resources/js/Layouts/AuthenticatedLayout.jsx']
    );
}

// 4. The launcher resolves each card's chip once, before the filter and the render both read it.
$launcher = 'resources/js/Components/navigation/MegaAppLauncher.jsx';
$launcherOld = '    const [filterQuery, setFilterQuery] = useState(\'\');';

if (substr_count($contents[$launcher], $launcherOld) !== 1) {
    $failures[] = 'MegaAppLauncher: could not locate the filter state to anchor the chip resolution';
} else {
    $contents[$launcher] = str_replace(
        $launcherOld,
        $launcherOld."\n    // Every card's chip, resolved once: a card that names a box takes the phase from the"
        ."\n    // shared list, and a card that names none keeps its own stage word. The filter below and the"
        ."\n    // badge beside each name read this one answer, so neither can paint a different number.\n"
        ."    const boxes = useWorkflowBoxes();\n"
        ."    const sections = subSystems.map((section) => ({\n"
        ."        ...section,\n"
        ."        items: section.items.map((item) => ({\n"
        ."            ...item,\n"
        ."            phase: workflowPhaseLabel(boxes, item.phaseBox) ?? item.phase ?? item.name,\n"
        ."        })),\n"
        ."    }));",
        $contents[$launcher]
    );
    $contents[$launcher] = str_replace('subSystems.map((section, sIdx)', 'sections.map((section, sIdx)', $contents[$launcher]);
    $contents[$launcher] = str_replace(
        "import { Link, usePage } from '@inertiajs/react';",
        "import { Link, usePage } from '@inertiajs/react';\nimport { useWorkflowBoxes, workflowPhaseLabel } from '@/Components/ui';",
        $contents[$launcher]
    );
    $contents[$launcher] = str_replace(
        '        // Journey order across categories: 0 → 0.5 → 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8.'
        ."\n        // Blocking (Phase 6) sits here after Registrar (Phase 5) so the launcher"
        ."\n        // reads in enrollment-journey order, not FDD module order (m4).",
        '        // The cards read in enrollment-journey order, not FDD module order (m4):'
        ."\n        // the Registrar box closes before Blocking schedules, and the clinic and ID"
        ."\n        // desks sign last. Their phase chips are numbered from the workflow's own list.",
        $contents[$launcher]
    );
}

// 5. Nothing in these three files may still type a phase number.
foreach ($contents as $rel => $text) {
    preg_match_all('/Phase [0-9]+(?:\.[0-9]+)?/', $text, $hits);

    foreach (array_unique($hits[0]) as $hit) {
        $failures[] = "{$rel}: still spells {$hit}";
    }
}

printf("%s: %d file(s); %d note(s); %d failure(s).\n", $apply ? 'APPLY' : 'DRY RUN', count($contents), count($notes), count($failures));

foreach ($notes as $line) {
    echo "  ok  {$line}\n";
}

foreach ($failures as $line) {
    echo "  FAIL  {$line}\n";
}

if ($failures !== []) {
    echo "NOTHING WRITTEN.\n";
    exit(1);
}

if (! $apply) {
    echo "Dry run only — pass --apply to write.\n";
    exit(0);
}

foreach ($contents as $rel => $text) {
    if (file_put_contents($root.'/'.$rel, $text) !== strlen($text)) {
        echo "  FAIL  could not write {$rel}\n";
        exit(1);
    }
}

$survivors = 0;

foreach (array_keys($contents) as $rel) {
    preg_match_all('/Phase [0-9]+(?:\.[0-9]+)?/', file_get_contents($root.'/'.$rel), $hits);
    $survivors += count(array_unique($hits[0]));
}

if ($survivors > 0) {
    echo "  FAIL  a phase number survived the write\n";
    exit(1);
}

echo "WROTE 3 file(s); the navigation surfaces spell no phase number.\n";
exit(0);
