<?php

/**
 * Lane 57 — prove the two new rules and the desk's ceiling can each be moved.
 *
 * Every rule added to `ems:audit:record-states` has to be seen refusing something, or it is decoration — the lesson
 * lane 54 wrote down after its report lines could only ever print 0. And a guard that has never been watched failing
 * is only a guess about what it stops, so this harness swaps HEAD's `AccountingController.php` over the working file,
 * runs the same desk act against the same schema, and requires the answer to flip: money enters the drawer on the
 * old bytes, is refused on these, and the batch's own bytes are restored by hash before anything is believed.
 *
 * It writes only to a scratch schema and refuses otherwise: live `ems` is not a place to plant an over-collection.
 *
 *   DB_DATABASE=ems_l57_a php storage/app/scratch/mutate-lane57-overcollect.php
 */

$root = dirname(__DIR__, 3);
$php = getenv('EMS_PHP') ?: 'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';
$database = (string) getenv('DB_DATABASE');

if ($database === '' || $database === 'ems') {
    fwrite(STDERR, "REFUSED: DB_DATABASE must name a scratch schema; this harness plants money. Live `ems` is not it.\n");
    exit(1);
}

$overLabel = 'Money held above what the sheet owes';
$paidLabel = 'An enrollment that says `paid` while its account still owes';
$failures = 0;
$note = function (bool $ok, string $what) use (&$failures): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $failures++;
    }
};

/** One artisan/tool run against the named schema, with its own exit code kept (no pipes: a pipe's status is the pipe's). */
$run = function (string $script) use ($root, $php, $database): array {
    $out = [];
    $code = 0;
    exec('cd /d '.escapeshellarg($root).' && set "DB_DATABASE='.$database.'" && set "DB_CONNECTION=mysql"'
        .' && '.escapeshellarg($php).' '.$script.' 2>&1', $out, $code);

    return [$code, implode("\n", $out)];
};
$audit = fn (): array => $run('artisan ems:audit:record-states');

/** An SQL statement through the booted app, with the rows it touched reported — a mutation that changed nothing is refused. */
$plant = function (string $mode) use ($run): array {
    [$code, $out] = $run('storage/app/scratch/lane57-plant-state.php '.$mode);
    if ($code !== 0) {
        fwrite(STDERR, "REFUSED: the planter reached no verdict (exit {$code}):\n".$out."\n");
        exit(1);
    }
    $parsed = [];
    foreach (explode("\n", $out) as $line) {
        if (preg_match('/^([a-z_]+)=(.*)$/', trim($line), $kv) === 1) {
            $parsed[$kv[1]] = trim($kv[2]);
        }
    }
    if (($parsed['changed'] ?? '') === '' || (int) $parsed['changed'] === 0) {
        fwrite(STDERR, "REFUSED: the {$mode} mutation touched no row, so the case would measure the old state.\n".$out."\n");
        exit(1);
    }

    return $parsed;
};

$ruleCount = function (string $out, string $label): int {
    return preg_match('/'.preg_quote($label, '/').'\s+(\d+)/', $out, $m) === 1 ? (int) $m[1] : -1;
};

echo "Schema this harness writes to: {$database}\n";

// ---------------------------------------------------------------- 1. the clean control
[$controlCode, $controlOut] = $audit();
$rulesRun = preg_match('/Rules run:\s*(\d+)/', $controlOut, $mm) === 1 ? (int) $mm[1] : -1;
$note($rulesRun === 19, 'nineteen rules print (read '.$rulesRun.')');
$note($controlCode === 0, 'and a schema the pipeline built passes them all (exit '.$controlCode.')');
$note(str_contains($controlOut, $overLabel) && str_contains($controlOut, $paidLabel),
    'and both new labels are actually on the report');
if ($failures > 0) {
    echo "\nThe control did not hold — every later verdict here would be measuring the wrong state.\n";
    exit(1);
}

// ---------------------------------------------------------------- 2. money above the sheet
$planted = $plant('over');
[$code, $out] = $audit();
$note($code === 1, 'a receipt above the amount owed fails the run (exit '.$code.')');
$note($ruleCount($out, $overLabel) === 1, 'and counts exactly one sheet under: '.$overLabel);
$note($ruleCount($out, $paidLabel) === 0, 'while the status rule still reads 0 — the excess is not a status lie');
$note((float) ($planted['over'] ?? -1) > 0, 'and the planter says the excess is real (₱'.($planted['over'] ?? '?').')');

$plant('undo-over');
[$afterCode, $afterOut] = $audit();
$note($afterCode === $controlCode, 'and removing the receipt makes the run answer as the control did (exit '.$afterCode.')');
$note($ruleCount($afterOut, $overLabel) === 0, 'and the count falls back to 0, not merely the exit code');

// ---------------------------------------------------------------- 3. a status that claims a settlement
$planted = $plant('paid-status');
[$code, $out] = $audit();
$note($code === 1, 'an enrollment left `paid` while its account owes fails the run (exit '.$code.')');
$note($ruleCount($out, $paidLabel) === 1, 'and counts exactly one enrollment under: '.$paidLabel);
$note($ruleCount($out, $overLabel) === 0, 'while the over-collection rule reads 0 on the same schema — two claims, two counts');

$plant('undo-paid-status');
[$afterCode, $afterOut] = $audit();
$note($afterCode === $controlCode, 'and walking the status back makes the run green again (exit '.$afterCode.')');
$note($ruleCount($afterOut, $paidLabel) === 0, 'and that rule falls back to 0 as well');

// ---------------------------------------------------------------- 4. the desk act, on HEAD's bytes and on these
$headCopy = $root.'/storage/app/scratch/lane57-head-AccountingController.php';
$working = $root.'/app/Http/Controllers/Accounting/AccountingController.php';
if (! is_file($headCopy)) {
    fwrite(STDERR, "REFUSED: no HEAD export at {$headCopy}. Take it with\n"
        ."  git show HEAD:app/Http/Controllers/Accounting/AccountingController.php > storage/app/scratch/lane57-head-AccountingController.php\n");
    exit(1);
}
$workingBytes = (string) file_get_contents($working);
$headBytes = (string) file_get_contents($headCopy);
if ($headBytes === $workingBytes) {
    fwrite(STDERR, "REFUSED: HEAD's controller is byte-identical to the working file, so there is no flip to measure."
        ." (This is the state right after the batch is committed — re-take the export from the previous commit.)\n");
    exit(1);
}

$askTheDesk = fn (): array => $run('storage/app/scratch/lane57-overcollect-state.php plant');
$undoTheDesk = fn (): array => $run('storage/app/scratch/lane57-overcollect-state.php revert');

// A `finally` does not run when the script exits, and `$plant()` and the refusal paths call `exit()` — so the first
// version of this leg died with HEAD's controller still in the working tree and reported nothing about it. The
// restore is therefore also registered as a shutdown handler: it writes the bytes back only if they are not already
// there, so a completed run and an aborted one both end with the batch's own file.
register_shutdown_function(static function () use ($working, $workingBytes): void {
    if (hash('sha256', (string) file_get_contents($working)) !== hash('sha256', $workingBytes)) {
        file_put_contents($working, $workingBytes);
        fwrite(STDERR, "restored {$working} from the shutdown handler — a case exited inside the swap\n");
    }
});

try {
    // HEAD's bytes first: the same POST that this batch refuses has to be accepted by the file the defect shipped in,
    // or the guard is not the thing that changed the answer. The probe cleans up after itself with its own `revert`,
    // not the planter's undo — they write different rows, and the planter correctly refuses to undo what it did not
    // plant rather than reporting a clean-up it never performed.
    copy($headCopy, $working);
    [$hCode, $hOut] = $askTheDesk();
    $headAccepted = str_contains($hOut, 'accepted=1');
    $note($headAccepted, "HEAD's controller lets the over-tender into the drawer (accepted=".($headAccepted ? '1' : '0').')');
    if (! $headAccepted) {
        echo '       what the probe printed instead: '.trim(preg_replace('/\s+/', ' ', substr($hOut, 0, 400)))."\n";
    }
    $undoTheDesk();

    file_put_contents($working, $workingBytes);

    [$wCode, $wOut] = $askTheDesk();
    $refused = str_contains($wOut, 'accepted=0') && str_contains($wOut, 'refused_reason=');
    $note($refused, 'this batch’s refuses it and says why, naming the figure it may not exceed');
    if (preg_match('/refused_reason=(.*)/s', $wOut, $m) === 1) {
        echo '       the desk reads: '.trim(preg_replace('/\s+/', ' ', $m[1]))."\n";
    }
    $note($wCode === 0, 'and the probe itself ran to a verdict rather than dying on the refusal (exit '.$wCode.')');
    $undoTheDesk();
} finally {
    // Restored unconditionally, whatever the cases concluded — a half-swapped tree makes every later verdict a lie.
    file_put_contents($working, $workingBytes);
    if (hash('sha256', (string) file_get_contents($working)) !== hash('sha256', $workingBytes)) {
        fwrite(STDERR, "FAIL: the controller could not be restored to the batch's bytes\n");
        $failures++;
    }
}

if ($failures > 0) {
    printf("\n%d case(s) did not behave — do not report this rule or that guard as proved.\n", $failures);
    exit(1);
}

echo "\nBoth rules refuse the state they name, the guard stops the desk act HEAD allowed, and the tree is restored.\n";
exit(0);
