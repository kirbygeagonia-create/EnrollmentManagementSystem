<?php

/**
 * Prove the G-3 crediting-decision tests can fail.
 *
 * The panel prompt §7.3 asks for adds one decision column, one write, three readers and one
 * desk act. Fourteen green cases in `CreditEquivalencyDecisionTest` say nothing on their own: a
 * case that would also pass against the code it is meant to guard is decoration. So each leg here
 * plants exactly one defect in the working file, runs ONLY the case that names it, and requires
 * that case to report one failed test — then restores the bytes by hash before the next
 * measurement.
 *
 * The defects are the promises the batch makes, one per promise, and they are spread over the
 * five files the rule lives in — the model's one `approved()` scope, the three readers that call
 * it, the validator that demands a reason, the write that signs and stamps the row, the totals the
 * panel shows, the right it offers, the delete guard that deliberately keeps counting declined
 * rows, the evidence columns, and the two schema decisions (the vocabulary, and the default that
 * lets the backfilled rows keep working). Their number is `count($cases) + 2` legs, printed by the
 * run itself and never typed into this file or into AGENTS.md.
 *
 * Two readings this harness had to learn the hard way, both recorded in AGENTS.md: `exec()` answers
 * only the LAST line of a command, so a run whose summary line is blank looks like no run at all;
 * and Pest colours its summary, so the escape sequences sit between "Tests:" and the figure — a
 * reader that does not strip them calls a green suite a broken instrument.
 *
 * The control's expected tally is counted out of the test file itself, never typed here: a
 * hard-coded figure goes quietly stale the day a case is added, and then every leg is judged
 * against a number that describes nothing.
 *
 * Usage: php storage/app/scratch/mutate-g3-crediting-decision-tests.php
 */
$root = dirname(__DIR__, 3);
$php = getenv('EMS_PHP') ?: 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
chdir($root);

$testFile = 'tests/Feature/Evaluation/CreditEquivalencyDecisionTest.php';

$targets = [
    'controller' => $root.'/app/Http/Controllers/Evaluation/EvaluationController.php',
    'readiness' => $root.'/app/Support/EnrollmentReadiness.php',
    'reference' => $root.'/app/Http/Controllers/Admin/ReferenceDataController.php',
    'creditModel' => $root.'/app/Models/Creditedsubjects.php',
    'recordModel' => $root.'/app/Models/Transferacademicrecords.php',
    'migration' => $root.'/database/migrations/2026_10_26_1000000002_add_crediting_decision_to_creditedsubjects_table.php',
];

$original = [];
$hashes = [];
foreach ($targets as $key => $path) {
    $original[$key] = (string) file_get_contents($path);
    $hashes[$key] = hash('sha256', $original[$key]);
    if ($original[$key] === '') {
        fwrite(STDERR, "REFUSED: cannot read {$path}.\n");
        exit(1);
    }
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

$cases = [
    [
        'scope-not-filtering',
        'creditModel',
        '        return $query->where(\'creditDecision\', CreditDecision::Approved->value);',
        '        return $query;',
        'a_declined_equivalency_stops_covering_the_prerequisite_it_was_mapped_to',
    ],
    [
        'prerequisite-filter-ignored',
        'controller',
        '        $credited = Creditedsubjects::approved()',
        '        $credited = Creditedsubjects::query()',
        'a_declined_equivalency_stops_covering_the_prerequisite_it_was_mapped_to',
    ],
    [
        'mandatory-filter-ignored',
        'controller',
        "        \$creditedSubjectIds = Creditedsubjects::approved()\n            ->where('enrollmentId', \$enrollment->enrollmentId)\n            ->pluck('creditedToSubjectId')\n            ->toArray();",
        "        \$creditedSubjectIds = Creditedsubjects::query()\n            ->where('enrollmentId', \$enrollment->enrollmentId)\n            ->pluck('creditedToSubjectId')\n            ->toArray();",
        'a_declined_equivalency_puts_its_subject_back_inside_the_mandatory_block',
    ],
    [
        'readiness-filter-ignored',
        'readiness',
        'Creditedsubjects::approved()',
        'Creditedsubjects::query()',
        'the_readiness_gate_the_registrar_reads_follows_the_same_decision',
    ],
    [
        'reason-not-required',
        'controller',
        "'decisionReason' => 'required_if:creditDecision,rejected|nullable|string|max:500',",
        "'decisionReason' => 'nullable|string|max:500',",
        'a_decline_without_a_reason_is_refused_and_names_the_field',
    ],
    [
        'reason-kept-on-approval',
        'controller',
        "'decisionReason' => \$decision === CreditDecision::Rejected ? \$validated['decisionReason'] : null,",
        "'decisionReason' => \$validated['decisionReason'] ?? \$creditedSubject->decisionReason,",
        'crediting_a_declined_row_again_clears_the_reason_and_restamps_the_hand',
    ],
    [
        'intake-decision-anonymous',
        'controller',
        "                    'decidedBy' => \$request->user()->userId,\n                    'decidedDate' => SchoolTime::today(),",
        "                    'decidedBy' => null,\n                    'decidedDate' => null,",
        'a_credit_the_desk_records_arrives_as_its_own_signed_decision',
    ],
    [
        'right-handed-out-in-the-panel',
        'controller',
        "'decideCredits' => \$request->user()->can('processCredits', \$enrollment),",
        "'decideCredits' => true,",
        'the_panel_states_its_totals_from_the_server_and_offers_the_decision_to_the_desk_that_holds_the_right',
    ],
    [
        'totals-count-everything-as-approved',
        'controller',
        '        $approved = $rows->where(\'creditDecision\', CreditDecision::Approved);',
        '        $approved = $rows;',
        'the_panel_states_its_totals_from_the_server_and_offers_the_decision_to_the_desk_that_holds_the_right',
    ],
    [
        'delete-guard-counts-approved-only',
        'reference',
        "'credited subject' => DB::table('creditedsubjects')->where('creditedToSubjectId', \$subject->subjectId)->count(),",
        "'credited subject' => DB::table('creditedsubjects')->where('creditedToSubjectId', \$subject->subjectId)->where('creditDecision', 'approved')->count(),",
        'a_subject_only_a_declined_credit_refers_to_still_cannot_be_deleted',
    ],
    [
        'evidence-columns-not-fillable',
        'recordModel',
        "'subjectNameAtOldSchool', 'previousSubjectCode', 'termTaken', 'syllabusReference',",
        "'subjectNameAtOldSchool',",
        'the_evidence_the_review_argues_from_is_stored_on_the_prior_record',
    ],
    [
        'decision-vocabulary-narrowed',
        'migration',
        "    private const DECISIONS = ['approved', 'rejected'];",
        "    private const DECISIONS = ['approved'];",
        'a_declined_equivalency_stops_covering_the_prerequisite_it_was_mapped_to',
    ],
    [
        'backfilled-rows-have-no-default',
        'migration',
        "                \$table->enum('creditDecision', self::DECISIONS)\n                    ->default('approved')\n                    ->after('remarks');",
        "                \$table->enum('creditDecision', self::DECISIONS)\n                    ->after('remarks');",
        'a_row_backfilled_before_the_decision_column_existed_still_counts_and_says_it_was_never_signed',
    ],
];

$restore = function () use ($targets, $original, $hashes): void {
    foreach ($targets as $key => $path) {
        file_put_contents($path, $original[$key]);
        if (hash('sha256', (string) file_get_contents($path)) !== $hashes[$key]) {
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

    echo "ok   {$label}  {$r['line']}\n";
};

// Measured first, or every verdict after it is unanchored.
$expect('control', null, 'passed', $caseCount);

foreach ($cases as [$label, $target, $from, $to, $method]) {
    $clean = $original[$target];

    if ($from === $to) {
        echo "BAD  {$label}: the needle and its replacement are the same text, so this mutation would change nothing.\n";
        $failures++;

        continue;
    }

    // Not a substring test on purpose. A SHRINK mutation — dropping three names out of a
    // fillable list — necessarily leaves a remainder that already occurs in the clean file, and
    // a guard that reads "the replacement is present" would refuse a mutation that is real. The
    // precise falsification is the byte-hash below: a mutation that touches nothing cannot pass it.
    $occurrences = substr_count($clean, $from);
    if ($occurrences !== 1) {
        echo "BAD  {$label}: the needle matched {$occurrences} place(s) in ".basename($targets[$target]).', expected exactly 1 — the defect cannot be planted.'."\n";
        $failures++;

        continue;
    }

    if (substr_count($testSource, $method) !== 1) {
        echo "BAD  {$label}: the case name {$method} is not spelled exactly once in the test file.\n";
        $failures++;

        continue;
    }

    file_put_contents($targets[$target], str_replace($from, $to, $clean));
    if (hash('sha256', (string) file_get_contents($targets[$target])) === $hashes[$target]) {
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
    echo "\n{$failures} leg(s) failed to prove the crediting-decision tests can fail.\n";
    exit(1);
}

echo "\nAll ".(count($cases) + 2).' legs passed: the G-3 decision is guarded by tests that bite.'."\n";
exit(0);
