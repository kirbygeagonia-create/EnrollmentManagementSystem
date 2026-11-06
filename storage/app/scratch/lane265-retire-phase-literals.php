<?php

/**
 * Retire every hand-typed `Phase <digit>` literal in the React surface.
 *
 * The enrollment workflow's boxes are declared once, in `WorkflowService::boxOrder()`, and
 * shared to the browser as an ordered list. A desk page that typed its own phase number was a
 * second arithmetic for the same box: the Registrar's header read "Phase 5" while the tracker
 * under it numbered the Registrar's box fourth of seven (and third of six for a continuing
 * student). Each pair below replaces a typed number with the box's own WORDS, which
 * `PageHeader` numbers from the shared list, or with a desk word that claims no phase at all
 * for the three desks that hold no box (Admissions, the entrance-exam lab, campus clearance).
 *
 * Dry run by default; `--apply` writes. Every pair asserts its own match count before the
 * write and asserts the retired literal is gone afterwards, and nothing is written while any
 * pair fails — a half-applied vocabulary is the defect this repository refuses elsewhere.
 */

$pairs = [
    ['resources/js/Pages/Registrar/Show.jsx', 'phaseBadge="Phase 5 · Official Enrollment"', 'phaseBox="Registrar Approval" phaseBadge="Official Enrollment"', 1],
    ['resources/js/Pages/Registrar/Show.jsx', 'subtitle="Office of the College Registrar — Final Enrollment Gate (Phase 5)"', 'subtitle="Office of the College Registrar — Final Enrollment Gate"', 1],
    ['resources/js/Pages/Registrar/Show.jsx', "'Sends automated clearance to the Student ID Processing Center (Phase 8) and School Health Clinic (Phase 7).'", "'Sends automated clearance to the School Health Clinic box and the Student ID Processing Center box.'", 1],
    ['resources/js/Pages/Registrar/Index.jsx', 'phaseBadge="Phase 5 · Registrar Central Desk"', 'phaseBox="Registrar Approval" phaseBadge="Registrar Central Desk"', 1],
    ['resources/js/Pages/Registrar/Index.jsx', 'value="Phase 5"', "value={useDeskPhaseChip('Registrar Approval')}", 1],
    ['resources/js/Pages/Assessment/Show.jsx', 'phaseBadge="Phase 3 · Fee Assessment"', 'phaseBox="Assessment" phaseBadge="Fee Assessment"', 1],
    ['resources/js/Pages/Assessment/Index.jsx', 'phaseBadge="Phase 3 · Assessment Desk"', 'phaseBox="Assessment" phaseBadge="Assessment Desk"', 1],
    ['resources/js/Pages/ID/Show.jsx', 'phaseBadge="Phase 8 · ID Office"', 'phaseBox="ID Validation" phaseBadge="ID Office"', 1],
    ['resources/js/Pages/ID/Index.jsx', 'phaseBadge="Phase 8 · ID Office"', 'phaseBox="ID Validation" phaseBadge="ID Office"', 1],
    ['resources/js/Pages/Evaluation/Show.jsx', 'phaseBadge="Phase 2 · Department Evaluation"', 'phaseBox="Department Evaluation"', 1],
    ['resources/js/Pages/Evaluation/Show.jsx', "'Immediately advances the student to Phase 3 (Scholarship & Assessment Desk) for automated tuition billing calculation.'", "'Immediately advances the student to the Assessment box (Scholarship & Assessment Desk) for automated tuition billing calculation.'", 1],
    ['resources/js/Pages/Evaluation/ShiftRequests.jsx', 'phaseBadge="Phase 2 · Department Evaluation"', 'phaseBox="Department Evaluation"', 1],
    ['resources/js/Pages/Evaluation/Index.jsx', 'phaseBadge="Phase 2 · Department Evaluation"', 'phaseBox="Department Evaluation"', 1],
    ['resources/js/Pages/Clinic/Show.jsx', 'subtitle={`Phase 7 — ${getStudentName()} (${student?.schoolIdNumber})`}', 'subtitle={`Clinic Examination — ${getStudentName()} (${student?.schoolIdNumber})`}', 1],
    ['resources/js/Pages/Clinic/Show.jsx', 'phaseBadge="Phase 7 · Clinic Examination"', 'phaseBox="Clinic" phaseBadge="Clinic Examination"', 1],
    ['resources/js/Pages/Clinic/Show.jsx', 'Measure the student\'s height, weight, and blood pressure to complete Phase 7.', "Measure the student's height, weight, and blood pressure to complete the Clinic box.", 1],
    ['resources/js/Pages/Clinic/Index.jsx', 'phaseBadge="Phase 7 · Clinic Desk"', 'phaseBox="Clinic" phaseBadge="Clinic Desk"', 1],
    ['resources/js/Pages/Blocking/Show.jsx', 'phaseBadge="Phase 6 · Section Scheduling"', 'phaseBox="Blocking and Scheduling" phaseBadge="Section Scheduling"', 1],
    ['resources/js/Pages/Blocking/Index.jsx', 'phaseBadge="Phase 6 · Section Blocking"', 'phaseBox="Blocking and Scheduling" phaseBadge="Section Blocking"', 1],
    ['resources/js/Pages/Accounting/Show.jsx', 'phaseBadge="Phase 4 · Cashier Collections"', 'phaseBox="Accounting Payment" phaseBadge="Cashier Collections"', 1],
    ['resources/js/Pages/Accounting/Index.jsx', 'phaseBadge="Phase 4 · Cashier Desk"', 'phaseBox="Accounting Payment" phaseBadge="Cashier Desk"', 1],
    ['resources/js/Pages/Accounting/DailyReport.jsx', 'phaseBadge="Phase 4 · Financial Audit"', 'phaseBox="Accounting Payment" phaseBadge="Financial Audit"', 1],
    ['resources/js/Pages/Admission/Show.jsx', 'phaseBadge="Phase 0 · Document Verification"', 'phaseBadge="Document Verification"', 1],
    ['resources/js/Pages/Admission/Show.jsx', 'subtitle="Admissions Office (Phase 0) — Official Intake Endorsement"', 'subtitle="Admissions Office — Official Intake Endorsement"', 1],
    ['resources/js/Pages/Admission/Index.jsx', 'phaseBadge="Phase 0 · Admissions Desk"', 'phaseBadge="Admissions Intake"', 1],
    ['resources/js/Pages/Exam/Index.jsx', 'phaseBadge="Phase 0.5 · BR9"', 'phaseBadge="Entrance Examination"', 1],
    ['resources/js/Pages/Exam/Create.jsx', 'phaseBadge="Phase 0.5 · BR9"', 'phaseBadge="Entrance Examination"', 1],
    ['resources/js/Pages/Exam/Results.jsx', 'phaseBadge="Phase 0.5 · BR9"', 'phaseBadge="Entrance Examination"', 1],
    ['resources/js/Pages/Clearance/Periods.jsx', 'phaseBadge="Phase 1 · Clearance Setup"', 'phaseBadge="Clearance Setup"', 1],
    ['resources/js/Pages/Clearance/Index.jsx', 'phaseBadge="Phase 1 · Campus Clearance"', 'phaseBadge="Campus Clearance"', 1],
    ['resources/js/Pages/Clearance/Index.jsx', "'Permits the student to proceed to Phase 2 (Curriculum Evaluation) once all requirements are signed.'", "'Permits the student to proceed to Curriculum Evaluation once all requirements are signed.'", 1],
    ['resources/js/Pages/Clearance/Index.jsx', "'Unlocks Phase 5 (Registrar Certification) — the Registrar can now approve official enrollment for this student.'", "'Unlocks Registrar Certification — the Registrar can now approve official enrollment for this student.'", 1],
    ['resources/js/Pages/Students/Index.jsx', 'end-to-end lifecycle tracking across all 8 phases', 'end-to-end lifecycle tracking across every office of the enrollment workflow', 1],
];

$root = dirname(__DIR__, 3);
$apply = in_array('--apply', $argv, true);
$failures = [];
$contents = [];

foreach ($pairs as [$rel, $old, $new, $expect]) {
    $path = $root.'/'.$rel;

    if (! isset($contents[$rel])) {
        if (! is_file($path)) {
            $failures[] = "MISSING FILE {$rel}";
            continue;
        }

        $contents[$rel] = file_get_contents($path);
    }

    $found = substr_count($contents[$rel], $old);

    if ($found !== $expect) {
        $failures[] = "{$rel}: expected {$expect} copy(ies) of the literal, found {$found}: {$old}";
        continue;
    }

    $replaced = str_replace($old, $new, $contents[$rel]);

    if (substr_count($replaced, $old) !== 0) {
        $failures[] = "{$rel}: the literal survives its own replacement: {$old}";
        continue;
    }

    if ($replaced === $contents[$rel]) {
        $failures[] = "{$rel}: the replacement is identical to the literal, so nothing would change: {$old}";
        continue;
    }

    $contents[$rel] = $replaced;
}

// Every `Phase <digit>` the React surface still spells by hand, after the pairs above.
$typed = [];

foreach ($contents as $rel => $text) {
    preg_match_all('/Phase [0-9]+(?:\.[0-9]+)?/', $text, $hits);

    foreach (array_unique($hits[0]) as $hit) {
        $typed[] = "{$rel}: {$hit}";
    }
}

$counts = array_count_values(array_column($pairs, 0));
printf(
    "%s: %d pair(s) over %d file(s); %d failure(s); %d hand-typed phase number(s) still in the touched files.\n",
    $apply ? 'APPLY' : 'DRY RUN',
    count($pairs),
    count($counts),
    count($failures),
    count($typed)
);

foreach ($typed as $line) {
    echo "  STILL TYPED  {$line}\n";
}

foreach ($failures as $line) {
    echo "  FAIL  {$line}\n";
}

if ($failures !== []) {
    echo "NOTHING WRITTEN.\n";
    exit(1);
}

if ($typed !== []) {
    echo "NOTHING WRITTEN: a pair above leaves a phase number behind, so the vocabulary is still two answers.\n";
    exit(1);
}

if (! $apply) {
    echo "Dry run only — pass --apply to write.\n";
    exit(0);
}

foreach ($contents as $rel => $text) {
    $written = file_put_contents($root.'/'.$rel, $text);

    if ($written === false || $written !== strlen($text)) {
        echo "  FAIL  could not write {$rel}\n";
        exit(1);
    }
}

$remaining = [];

foreach ($contents as $rel => $text) {
    preg_match_all('/Phase [0-9]+(?:\.[0-9]+)?/', file_get_contents($root.'/'.$rel), $hits);
    $remaining[$rel] = count(array_unique($hits[0]));
}

if (array_sum($remaining) > 0) {
    echo "  FAIL  a phase number survived the write\n";
    exit(1);
}

echo 'WROTE '.count($contents)." file(s); every touched file now spells no phase number.\n";
exit(0);
