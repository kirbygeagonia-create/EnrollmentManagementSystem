<?php

/**
 * Proves the G34 clearance-reason cases can fail.
 *
 * Four legs, one per edge of the rule the batch wrote — requiredness, the ten-character floor, the DIRECTION
 * of the requirement, and persistence of what the officer typed. The direction leg is the one that matters
 * most: it re-points `required_if` at `approved`, which is the shape that would silently break the common act
 * while every refusal case stayed green.
 *
 * Not covered here, and stated rather than implied: the cross-office 403 case belongs to the policy, not to
 * these rules, so no leg in this file would notice a policy widening.
 *
 * Usage: php storage/app/scratch/mutate-g34-clearance-reason-tests.php
 */

$root = dirname(__DIR__, 3);
$testFile = 'tests/Feature/Clearance/ClearanceRequirementRejectionTest.php';
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$caseCount = 5;

$controller = $root.'/app/Http/Controllers/Clearance/ClearanceController.php';
$snapshots = [$controller => ['bytes' => (string) file_get_contents($controller)]];
$snapshots[$controller]['sha1'] = sha1($snapshots[$controller]['bytes']);

$rule = "'remarks' => 'required_if:status,rejected|nullable|string|min:10|max:500',";
$statusRule = "'status' => 'required|in:approved,waived,rejected',";
$writeStatus = "'status' => \$validated['status'],";

foreach ([$rule, $statusRule, $writeStatus] as $needle) {
    if (substr_count($snapshots[$controller]['bytes'], $needle) !== 1) {
        fwrite(STDERR, 'REFUSED: ['.mb_substr($needle, 0, 46).'] occurs '
            .substr_count($snapshots[$controller]['bytes'], $needle)." time(s); the tree is not the batch this harness proves.\n");

        exit(1);
    }
}

register_shutdown_function(static function () use ($snapshots): void {
    foreach ($snapshots as $path => $s) {
        if (sha1((string) file_get_contents($path)) !== $s['sha1']) {
            file_put_contents($path, $s['bytes']);
        }
    }
});

$runCase = static function (string $name) use ($php, $root, $testFile): array {
    chdir($root);
    $cmd = escapeshellarg($php).' artisan test '.escapeshellarg($testFile)
        .($name === '' ? '' : ' --filter='.escapeshellarg($name)).' 2>&1';
    // Pest writes ANSI codes into captured output; the tally line is one of the styled ones.
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
        'name' => 'reason-not-required-at-all',
        'case' => 'a_refused_line_is_refused_again_when_it_carries_no_reason',
        'from' => $rule,
        'to' => "'remarks' => 'nullable|string|min:10|max:500',",
        'promise' => 'a refused clearance line owes a reason',
    ],
    [
        'name' => 'length-floor-dropped',
        'case' => 'a_short_reason_is_refused_rather_than_accepted_as_a_placeholder',
        'from' => $rule,
        'to' => "'remarks' => 'required_if:status,rejected|nullable|string|max:500',",
        'promise' => 'ten characters is the floor, so a placeholder is not accepted',
    ],
    [
        'name' => 'direction-reversed',
        'case' => 'an_approved_line_still_needs_nothing_typed_in_the_remark_box',
        'from' => $rule,
        'to' => "'remarks' => 'required_if:status,approved|nullable|string|min:10|max:500',",
        'promise' => 'the requirement bites on a refusal, never on an approval',
    ],
    [
        'name' => 'officers-word-not-persisted',
        'case' => 'a_refused_line_with_a_reason_is_recorded_and_the_clearance_goes_rejected',
        'from' => $writeStatus,
        'to' => "'status' => \$request->boolean('approved') ? 'approved' : 'rejected',",
        'promise' => 'the decision the desk chose is the decision the row records',
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
    $src = $snapshots[$controller]['bytes'];
    $hits = substr_count($src, $leg['from']);
    if ($hits !== 1) {
        echo "BAD  {$leg['name']}: the needle occurs {$hits} time(s).\n";
        $bad++;

        continue;
    }
    file_put_contents($controller, str_replace($leg['from'], $leg['to'], $src));
    $verdict = $runCase($leg['case']);
    file_put_contents($controller, $snapshots[$controller]['bytes']);

    if ($verdict[0] === '1-failed') {
        echo "ok   {$leg['name']}: {$leg['promise']} — its case reports one failure\n";

        continue;
    }
    echo "BAD  {$leg['name']}: verdict {$verdict[0]} (want 1-failed)\n".mb_substr($verdict[1], 0, 300)."\n";
    $bad++;
}

echo "control again: the file must be green after every restore\n";
if ($runCase('')[0] !== 'green') {
    echo "BAD final control\n";
    $bad++;
}
if (sha1((string) file_get_contents($controller)) !== $snapshots[$controller]['sha1']) {
    echo "BAD restore: the controller is not the bytes it was handed.\n";
    $bad++;
}

printf("\n%s: %d leg(s), %d bad\n", $bad === 0 ? 'ALL KILLED' : 'REFUSE', count($legs), $bad);

exit($bad === 0 ? 0 : 1);
