<?php

/**
 * Proves the G33 admission-rejection-reason cases can fail.
 *
 * One leg per promise the batch made, each planted in the real tree, each requiring ITS OWN named case to
 * report exactly one failure — not merely "not passed", because a mutant that does not compile is not a
 * killed defect (lane 73's rule). Every touched file is restored by sha1 through a shutdown handler, and the
 * run refuses at the door when a needle does not occur exactly once or the tree does not read like the batch
 * it is supposed to prove.
 *
 * Usage: php storage/app/scratch/mutate-g33-rejection-reason-tests.php
 */
$root = dirname(__DIR__, 3);
$testFile = 'tests/Feature/Admission/AdmissionRejectionReasonTest.php';
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$caseCount = 7;

$controller = $root.'/app/Http/Controllers/Admission/AdmissionController.php';
$model = $root.'/app/Models/Admissions.php';
$targets = [$controller, $model];

$snapshots = [];
foreach ($targets as $path) {
    $bytes = file_get_contents($path);
    if ($bytes === false) {
        fwrite(STDERR, "REFUSED: cannot read {$path}.\n");

        exit(1);
    }
    $snapshots[$path] = ['bytes' => $bytes, 'sha1' => sha1($bytes)];
}

// The overlap guard: a tree that no longer carries the batch's own shapes is not a tree this harness may
// measure. Two runs of one mutating harness overlapped on 2026-10-27 and wrote a mutant in permanently.
$missing = [];
foreach ([
    $controller => [
        "'rejectionReason' => 'required|string|min:10|max:500',",
        "'evaluatedDate' => SchoolTime::today(),",
        "'remarks' => \$validated['remarks'] ?? null,",
    ],
    $model => ["'admissionStatus', 'rejectionReason', 'draftedDate'"],
] as $path => $needles) {
    foreach ($needles as $needle) {
        if (! str_contains($snapshots[$path]['bytes'], $needle)) {
            $missing[] = basename($path).' lacks ['.$needle.']';
        }
    }
}
if ($missing !== []) {
    fwrite(STDERR, 'REFUSED: the tree does not read like the batch this harness proves — '.implode('; ', $missing).".\n");

    exit(1);
}

register_shutdown_function(static function () use ($snapshots): void {
    foreach ($snapshots as $path => $s) {
        if (sha1((string) file_get_contents($path)) !== $s['sha1']) {
            file_put_contents($path, $s['bytes']);
        }
    }
});

/**
 * Runs one case (or the whole file when $name is '') and returns [verdict, output].
 * verdict is 'green' | 'one-failed' | 'N-failed' | 'unreadable'.
 */
$runCase = static function (string $name) use ($php, $root, $testFile): array {
    chdir($root);
    $cmd = escapeshellarg($php).' artisan test '.escapeshellarg($testFile)
        .($name === '' ? '' : ' --filter='.escapeshellarg($name)).' 2>&1';
    // Pest writes ANSI colour codes into captured output, and the tally line is one of the styled ones:
    // matching without stripping reads a passing run as "no verdict at all" (lane 73's lesson, met again).
    $out = (string) preg_replace('/\e\[[0-9;]*m/', '', (string) shell_exec($cmd));

    $failed = preg_match('/(\d+) failed/', $out, $fm) ? (int) $fm[1] : 0;
    $passed = preg_match('/(\d+) passed/', $out, $pm) ? (int) $pm[1] : 0;

    if (! str_contains($out, 'Tests:') || ($failed === 0 && $passed === 0)) {
        return ['unreadable', $out];
    }

    return [$failed === 0 ? 'green' : $failed.'-failed', $out];
};

$legs = [
    [
        'name' => 'no-reason-act-unvalidated',
        'case' => 'a_rejection_with_no_reason_written_is_refused_and_the_application_stays_pending',
        'file' => $controller,
        'from' => "        \$validated = \$request->validate([\n            'rejectionReason' => 'required|string|min:10|max:500',\n        ]);",
        'to' => "        \$validated = ['rejectionReason' => (string) \$request->input('rejectionReason', '')];",
        'promise' => 'the controller refuses a rejection that names no reason',
    ],
    [
        'name' => 'length-floor-dropped',
        'case' => 'a_reason_shorter_than_the_rule_names_the_field_it_refused',
        'file' => $controller,
        'from' => "'rejectionReason' => 'required|string|min:10|max:500',",
        'to' => "'rejectionReason' => 'required|string|max:500',",
        'promise' => 'ten characters is the floor the desk is held to',
    ],
    [
        'name' => 'day-back-to-the-machine',
        'case' => 'a_rejection_stores_the_officers_own_words_and_files_them_on_the_schools_day',
        'file' => $controller,
        'from' => "                'evaluatedDate' => SchoolTime::today(),\n            ]);\n        });\n\n        return back()->with('success', 'Admission rejected",
        'to' => "                'evaluatedDate' => now(),\n            ]);\n        });\n\n        return back()->with('success', 'Admission rejected",
        'promise' => "a DATE column is filed on the school's day, not UTC's",
    ],
    [
        'name' => 'reason-not-persisted',
        'case' => 'a_rejection_stores_the_officers_own_words_and_files_them_on_the_schools_day',
        'file' => $model,
        'from' => "'admissionStatus', 'rejectionReason', 'draftedDate'",
        'to' => "'admissionStatus', 'draftedDate'",
        'promise' => 'the reason reaches the row rather than being dropped by mass assignment',
    ],
    [
        'name' => 'document-remark-not-required',
        'case' => 'a_rejected_document_row_without_a_remark_is_refused',
        'file' => $controller,
        'from' => "'remarks' => [Rule::requiredIf(fn () => ! \$request->boolean('approved')), 'nullable', 'string', 'min:10', 'max:500'],",
        'to' => "'remarks' => 'nullable|string|max:500',",
        'promise' => 'a refused document row owes a reason',
    ],
    [
        'name' => 'canned-sentence-returns',
        // The fallback only fires when remarks arrive null, and the only act that sends null is an approval.
        // Pointed first at the rejection case it survived — the desk's own words were there, so `??` never ran.
        'case' => 'an_approved_document_row_needs_no_remark_because_there_is_nothing_to_explain',
        'file' => $controller,
        'from' => "'submissionStatus' => \$approved ? 'verified' : 'rejected',\n                'remarks' => \$validated['remarks'] ?? null,",
        'to' => "'submissionStatus' => \$approved ? 'verified' : 'rejected',\n                'remarks' => \$validated['remarks'] ?? 'Requirement rejected / needs resubmission.',",
        'promise' => 'the server never writes words the officer did not type',
    ],
];

$bad = 0;

echo "control: all {$caseCount} cases green before anything is planted\n";
$control = $runCase('');
if ($control[0] !== 'green') {
    echo "BAD control — {$control[0]}\n".mb_substr($control[1], 0, 500)."\n";
    fwrite(STDERR, "REFUSED: the control is not green, so no later verdict can be read.\n");

    exit(1);
}

foreach ($legs as $leg) {
    $src = $snapshots[$leg['file']]['bytes'];
    $hits = substr_count($src, $leg['from']);

    if ($hits !== 1) {
        echo "BAD  {$leg['name']}: the needle occurs {$hits} time(s), so the leg cannot land.\n";
        $bad++;

        continue;
    }

    file_put_contents($leg['file'], str_replace($leg['from'], $leg['to'], $src));
    $verdict = $runCase($leg['case']);
    file_put_contents($leg['file'], $snapshots[$leg['file']]['bytes']);

    if ($verdict[0] === '1-failed') {
        echo "ok   {$leg['name']}: {$leg['promise']} — its case reports one failure\n";

        continue;
    }

    echo "BAD  {$leg['name']}: verdict {$verdict[0]} (want 1-failed)\n".mb_substr($verdict[1], 0, 400)."\n";
    $bad++;
}

echo "control again: the file must be green after every restore\n";
$after = $runCase('');
if ($after[0] !== 'green') {
    echo "BAD final control — {$after[0]}\n";
    $bad++;
}

foreach ($snapshots as $path => $s) {
    if (sha1((string) file_get_contents($path)) !== $s['sha1']) {
        echo 'BAD restore: '.basename($path)." is not the bytes it was handed.\n";
        $bad++;
    }
}

printf("\n%s: %d leg(s), %d bad\n", $bad === 0 ? 'ALL KILLED' : 'REFUSE', count($legs), $bad);

exit($bad === 0 ? 0 : 1);
