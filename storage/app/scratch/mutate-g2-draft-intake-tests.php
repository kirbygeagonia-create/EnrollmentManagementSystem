<?php

/**
 * Prove the G-2 draft-intake tests can fail.
 *
 * Draft intake (development prompt §7.1, §14) spans four surfaces — a migration that widens an enum on BOTH
 * engines, the intake branch in AdmissionController, the status rule in AdmissionPolicy, and the props the desk
 * page reads. DraftIntakeTest going green says nothing by itself: a case that would also pass against the code
 * it guards is decoration. So each leg plants exactly one defect, runs ONLY the case that names it, and requires
 * that case to report one failed test — then restores the bytes by hash.
 *
 * The `sqlite-check-not-widened` leg is not hypothetical. It is the defect this batch actually met: the first
 * version of the migration guarded its ALTER on the mysql driver, SQLiteGrammar::typeEnum had already compiled
 * the column as `varchar CHECK ("admissionStatus" in ('pending',…))`, and every draft insert died on it — the
 * suite reporting a 500-rendered refusal while the code looked correct. A guard that only widens MySQL is a
 * half-widened column.
 *
 * What this harness cannot reach: the two desk-side halves are JSX (the Save-as-draft button and the draft
 * banner, which used to fall back through `statusBannerMap[status] || statusBannerMap.pending` and paint a
 * parked case as "Pending Review"). There is no JS test suite in this repository, so those are proven by
 * reading, and `desk-actions-forged` is the closest a PHP test can come to them — the props the page renders
 * its buttons from.
 *
 * Usage: php storage/app/scratch/mutate-g2-draft-intake-tests.php
 */
$root = dirname(__DIR__, 3);
$php = getenv('EMS_PHP') ?: 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
chdir($root);

$testFile = 'tests/Feature/Admission/DraftIntakeTest.php';

$controller = 'app/Http/Controllers/Admission/AdmissionController.php';
$policy = 'app/Policies/AdmissionPolicy.php';
$migration = 'database/migrations/2026_10_26_1000000001_add_draft_intake_state_to_admissions_table.php';

$targets = [
    $controller => (string) file_get_contents($root.'/'.$controller),
    $policy => (string) file_get_contents($root.'/'.$policy),
    $migration => (string) file_get_contents($root.'/'.$migration),
];

$hashes = [];
foreach ($targets as $path => $source) {
    if ($source === '') {
        fwrite(STDERR, "REFUSED: cannot read {$path}.\n");
        exit(1);
    }
    $hashes[$path] = hash('sha256', $source);
}

$testSource = (string) file_get_contents($root.'/'.$testFile);
if ($testSource === '') {
    fwrite(STDERR, "REFUSED: cannot read {$testFile}.\n");
    exit(1);
}

$caseCount = substr_count($testSource, '#[Test]');
if ($caseCount < 1) {
    fwrite(STDERR, "REFUSED: {$testFile} declares no #[Test] method, so there is nothing to judge.\n");
    exit(1);
}

/** [label, file, from, to, method] */
$cases = [
    [
        'draft-flag-ignored',
        $controller,
        "\$asDraft = \$request->input('intakeAction') === 'draft';",
        '$asDraft = false;',
        'a_draft_is_saved_without_the_sections_the_applicant_has_not_brought_yet',
    ],
    [
        'sqlite-check-not-widened',
        $migration,
        "        Schema::table('admissions', function (Blueprint \$table) {\n            \$table->enum('admissionStatus', self::STATUSES)->change();\n        });",
        '        // the mutate harness planted this leg: the SQLite widen is gone',
        'a_draft_is_saved_without_the_sections_the_applicant_has_not_brought_yet',
    ],
    [
        'typed-row-discarded',
        $controller,
        'return is_array($row) && collect($fields)->contains(fn (string $field): bool => filled($row[$field] ?? null));',
        'return false;',
        'a_half_written_address_is_kept_and_named_rather_than_dropped',
    ],
    [
        'draft-stat-forged',
        $controller,
        "'draft' => (int) (\$statusCounts[AdmissionStatus::Draft->value] ?? 0),",
        "'draft' => 0,",
        'a_draft_is_not_in_the_queue_but_is_counted_and_filterable_as_a_draft',
    ],
    [
        'incomplete-submit-allowed',
        $controller,
        'if ($missing !== []) {',
        'if (false) {',
        'submitting_a_draft_names_the_sections_still_missing_and_leaves_it_a_draft',
    ],
    [
        'checklist-seeding-duplicates',
        $controller,
        "return Studentrequirementsubmissions::where('admissionId', \$admission->admissionId)\n                    ->where('requirementId', \$req->requirementId)\n                    ->exists();",
        'return false; // planted: seeding no longer asks whether the case already carries the row',
        'a_completed_draft_is_submitted_and_holds_one_checklist_row_per_requirement',
    ],
    [
        'submit-stops-picking-up-the-catalogue',
        $controller,
        "// Idempotent: the draft already carries a row per requirement, and this picks up any requirement\n            // the catalogue gained while the case was parked.\n            \$this->seedRequirementSubmissions(\$admission);",
        '// Idempotent: the draft already carries a row per requirement, and this picks up any requirement\n            // the catalogue gained while the case was parked.',
        'a_requirement_added_while_the_case_sat_as_a_draft_is_picked_up_on_submit',
    ],
    [
        'verify-allowed-on-a-draft',
        $policy,
        "return \$user->hasPermissionTo('admission.requirements.verify')\n            && \$admission->admissionStatus === AdmissionStatus::Pending;",
        "return \$user->hasPermissionTo('admission.requirements.verify')\n            && \$admission->admissionStatus !== AdmissionStatus::Rejected;",
        'a_document_is_received_on_a_draft_but_the_draft_cannot_be_verified_approved_or_rejected',
    ],
    [
        'desk-actions-forged',
        $controller,
        "'verifyRequirements' => \$request->user()->can('verifyRequirements', \$admission),",
        "'verifyRequirements' => true,",
        'the_desk_is_offered_only_the_acts_the_policy_will_accept',
    ],
    [
        'draft-submit-ignores-its-own-state',
        $controller,
        'if ($admission->admissionStatus !== AdmissionStatus::Draft) {',
        'if (false) {',
        'a_submitted_case_cannot_be_pushed_back_through_the_draft_submit_act',
    ],
];

$restore = function () use ($root, $targets, $hashes): void {
    foreach ($targets as $path => $source) {
        file_put_contents($root.'/'.$path, $source);
        if (hash('sha256', (string) file_get_contents($root.'/'.$path)) !== $hashes[$path]) {
            fwrite(STDERR, "BAD: the restore did not reproduce the original bytes of {$path}.\n");
            exit(1);
        }
    }
};

// This harness can die on its own assertion; the tree must not stay mutated.
register_shutdown_function($restore);

/**
 * @return array{state: string, word: string, count: int, line: string}
 */
$run = function (?string $filter) use ($php, $testFile): array {
    $cmd = escapeshellarg($php).' artisan test '.escapeshellarg($testFile);
    if ($filter !== null) {
        $cmd .= ' --filter '.escapeshellarg($filter);
    }

    $raw = (string) shell_exec($cmd.' 2>&1');
    $out = (string) preg_replace('/\e\[[0-9;]*m/', '', $raw);

    if (trim($out) === '') {
        return ['state' => 'NO-OUTPUT', 'word' => 'nothing', 'count' => 0, 'line' => 'the run wrote no bytes at all — the invocation is broken, not the suite'];
    }
    if (! preg_match('/Tests:\s+([0-9]+) (passed|failed|incomplete)/', $out, $m)) {
        return ['state' => 'BROKEN', 'word' => 'unread', 'count' => 0, 'line' => 'no Tests: line; tail was: '.substr(trim($out), -300)];
    }

    return [
        'state' => $m[2] === 'passed' ? 'GREEN' : ($m[2] === 'failed' ? 'RED' : 'ODD'),
        'word' => $m[2],
        'count' => (int) $m[1],
        'line' => trim($m[0]),
    ];
};

$failures = 0;

$expect = function (string $label, ?string $filter, string $wantWord, int $wantCount) use ($run, &$failures): void {
    $r = $run($filter);
    if ($r['word'] !== $wantWord || $r['count'] !== $wantCount) {
        echo "BAD  {$label}: expected {$wantCount} {$wantWord}, the run answered {$r['count']} {$r['word']} ({$r['state']}) — {$r['line']}\n";
        $failures++;

        return;
    }

    echo "ok   {$label}        {$r['line']}\n";
};

// Measured first, or every verdict after it is unanchored.
$expect('control', null, 'passed', $caseCount);

foreach ($cases as [$label, $file, $from, $to, $method]) {
    $source = $targets[$file];

    if (strpos($source, $to) !== false) {
        echo "BAD  {$label}: the replacement text is already in {$file}, so this mutation would change nothing.\n";
        $failures++;

        continue;
    }

    $occurrences = substr_count($source, $from);
    if ($occurrences !== 1) {
        echo "BAD  {$label}: the needle matched {$occurrences} place(s) in {$file}, expected exactly 1 — the defect cannot be planted.\n";
        $failures++;

        continue;
    }

    if (substr_count($testSource, $method) !== 1) {
        echo "BAD  {$label}: the case name {$method} is not spelled exactly once in the test file.\n";
        $failures++;

        continue;
    }

    file_put_contents($root.'/'.$file, str_replace($from, $to, $source));
    if (hash('sha256', (string) file_get_contents($root.'/'.$file)) === $hashes[$file]) {
        echo "BAD  {$label}: the mutation left the file byte-identical, so this run would measure the clean instrument.\n";
        $failures++;
        $restore();

        continue;
    }

    try {
        $expect($label, $method, 'failed', 1);
    } finally {
        $restore();
    }
}

// The reverse direction: a harness that only ever reports red is as useless as one that never does.
$expect('control-after', null, 'passed', $caseCount);

if ($failures > 0) {
    echo "\n{$failures} leg(s) failed to prove the draft-intake tests can fail.\n";
    exit(1);
}

echo "\nAll ".(count($cases) + 2)." legs passed: §7.1 draft intake is guarded by tests that bite.\n";
exit(0);
