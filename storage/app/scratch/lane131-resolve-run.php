<?php

// Which Actions run belongs to the SHA the operator just pushed, read from `gh run list` output.
// Matching is client-side on the FULL sha, and the resolved run is then asked for its own headSha — a prefix
// match on an empty needle matches everything, and a poller must re-read the object it claims to have watched.
// The runs file is named by the caller: this resolver once kept reading the previous push's capture and reported
// "no run for <sha>" about a run that had existed the whole time — an accepted-and-inert argument, met from inside a
// poller rather than a checker.
$jsonPath = $argv[2] ?? 'storage/app/scratch/lane131-runs.json';
$json = (string) file_get_contents($jsonPath);
$runs = json_decode($json, true);
if (! is_array($runs)) {
    $err = trim((string) file_get_contents('storage/app/scratch/lane131-runs.err'));
    fwrite(STDERR, "REFUSED: gh run list answered no JSON.".($err === '' ? '' : "  stderr: {$err}")."\n");

    exit(1);
}

// shell_exec cannot reach git from PHP on this box, so the SHA is handed in rather than asked for.
$want = trim((string) ($argv[1] ?? ''));
if (strlen($want) < 7) {
    fwrite(STDERR, "REFUSED: the SHA needle is empty or too short (".strlen($want)." chars), and an empty needle matches every run.\n");

    exit(1);
}

foreach ($runs as $r) {
    // A prefix of at least seven characters is matched, and the caller re-reads the run's own headSha afterwards:
    // equality on a short needle is how a poller says "no run exists" about a run that has been there all along.
    if (str_starts_with((string) ($r['headSha'] ?? ''), $want)) {
        printf("RUN=%d status=%s headSha=%s\n", (int) $r['databaseId'], (string) ($r['status'] ?? 'unknown'), $r['headSha']);

        exit(0);
    }
}

printf("no run for %s yet among %d listed\n", substr($want, 0, 12), count($runs));

exit(1);
