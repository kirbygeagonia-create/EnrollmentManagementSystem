<?php
/**
 * Poll one Actions run to completion and report the run's OWN verdict.
 *
 * Three rules this file exists for, all paid for earlier: a watcher's exit code is not the run's verdict (it has
 * returned 1 on a still-running job and 0 on a job that had not started), so this polls until `status == completed`
 * and only then quotes `conclusion`; the object a poller resolved must be re-read from the server and its `headSha`
 * compared with the SHA it was asked about, because an empty prefix needle once made it report a different commit's
 * green as this one's; and stderr is never discarded when its silence is being read as a measurement.
 *
 * Usage: php watch-run.php <run-id> <full-sha>
 */
$runId = $argv[1] ?? '';
$want = $argv[2] ?? '';
$repo = 'kirbygeagonia-create/EnrollmentManagementSystem';

if ($runId === '' || ! ctype_digit($runId)) {
    fwrite(STDERR, "REFUSED: run id must be a numeric string, got [{$runId}].\n");
    exit(1);
}
if (strlen($want) < 40) {
    fwrite(STDERR, "REFUSED: the SHA needle must be a full 40-character object name, got ".strlen($want)
        ." characters. A short or empty needle matches everything, which is how a poller reports another commit's "
        ."verdict.\n");
    exit(1);
}

$gh = static function (string $api) use ($runId, $repo): ?array {
    $cmd = 'gh api "repos/'.$repo.'/actions/runs/'.$runId.'" 2>&1';
    $out = shell_exec($cmd);
    if ($out === null) {
        fwrite(STDERR, "REFUSED: gh api returned nothing for run {$runId}.\n");
        exit(1);
    }
    $j = json_decode($out, true);
    if (! is_array($j) || ! isset($j['status'])) {
        fwrite(STDERR, "REFUSED: the answer for run {$runId} is not a run object. Tail: "
            .substr($out, 0, 300)."\n");
        exit(1);
    }

    return $j;
};

$deadline = time() + 3300;
$last = '';
while (true) {
    $j = $gh('runs');
    $head = (string) ($j['head_sha'] ?? '');
    if ($head !== $want) {
        fwrite(STDERR, "REFUSED: run {$runId} carries head_sha [{$head}], not the SHA I was asked about [{$want}]. "
            ."The verdict of a different commit is not evidence about this one.\n");
        exit(1);
    }
    $status = (string) $j['status'];
    $line = 'status='.$status.' conclusion='.($j['conclusion'] ?? 'null')
        .' updated='.($j['updated_at'] ?? '?');
    if ($line !== $last) {
        echo date('H:i:s').' '.$line."\n";
        $last = $line;
    }
    if ($status === 'completed') {
        break;
    }
    if (time() > $deadline) {
        fwrite(STDERR, "REFUSED: run {$runId} had not completed within the polling window; the last status above is "
            ."a reading, not a verdict.\n");
        exit(1);
    }
    sleep(30);
}

// The verdict, from the run itself, then per job — never from this poller's exit code.
echo "\nrun {$runId}: status=".$j['status'].' conclusion='.($j['conclusion'] ?? 'null')
    .' head_sha='.$j['head_sha']."\n";
$jobs = json_decode((string) shell_exec('gh api "repos/'.$repo.'/actions/runs/'.$runId.'/jobs" 2>&1'), true);
// The key is `jobs`. Reading `workflow_jobs` (the name `gh run view --json jobs` uses in a different shape) answered
// null, the foreach iterated nothing, and the run printed its own success with NO job lines at all — a clean-looking
// verdict over a list it never read, which is the defect this repository has refused in six instruments.
$list = $jobs['jobs'] ?? null;
if (! is_array($list) || $list === []) {
    fwrite(STDERR, "REFUSED: the jobs answer for run {$runId} holds no job list (keys: "
        .implode(', ', array_keys(is_array($jobs) ? $jobs : [])).'). A run reported green without naming its jobs '
        ."is not a measured verdict.\n");
    exit(1);
}
foreach ($list as $job) {
    printf("  %-46s %-12s %s\n", $job['name'], $job['status'], $job['conclusion'] ?? 'null');
}
if (count($list) < 3) {
    fwrite(STDERR, "REFUSED: CI is three jobs by design and the answer lists ".count($list)
        .". A partial list is not the whole pipeline.\n");
    exit(1);
}
exit(($j['conclusion'] ?? '') === 'success' ? 0 : 1);
