<?php

/**
 * The falsifier for `lane198-probe-forced-rollback.php`, which is the instrument that lets the ADS guide's
 * §V.2 ("a failure scenario and the observed result") and the T02 / T17 rows stop saying "not reachable as
 * posed". A probe whose every branch can only print the good outcome is decoration, so each guard it carries
 * is planted here and required to refuse.
 *
 * Every leg runs a COPY of the probe (written into scratch/, deleted at the end of its own leg and swept by
 * pattern at shutdown) so the needle can be moved without editing the delivered tool. Two legs move the
 * injection point, which is the only way to show that the probe's two central claims — that the fault fired at
 * all, and that it fired AFTER a row had been written — are measured rather than asserted.
 *
 * Refusals are told apart by their own MESSAGE, never by the exit code, because all of them exit 1.
 *
 * Usage: DB_CONNECTION=mysql php storage/app/scratch/mutate-lane198-rollback-probe.php
 */
$root = dirname(__DIR__, 3);
chdir($root);

$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$tool = $root.'/storage/app/scratch/lane198-probe-forced-rollback.php';
$schema = (string) (getenv('EMS_LANE198_SCHEMA') ?: 'ems_l197_a');

if ((string) getenv('DB_CONNECTION') !== 'mysql') {
    fwrite(STDERR, "REFUSED: this harness drives the real engine, so DB_CONNECTION must be pinned to mysql before it runs.\n");
    exit(1);
}
if (! is_file($tool)) {
    fwrite(STDERR, "REFUSED: the probe under test is not at {$tool}.\n");
    exit(1);
}

$bytes = (string) file_get_contents($tool);
// The needle is built once, in a single-quoted string: `"$sql"` here would interpolate an undefined harness
// variable and match nothing, which is this project's documented trap for a mutant that cannot apply.
$injectionNeedle = 'str_contains($sql, '."'insert into enrollmentworkflow'".')';
if (substr_count($bytes, $injectionNeedle) !== 1) {
    fwrite(STDERR, 'REFUSED: the probe\'s injection needle is not the spelling this harness expects (found '
        .substr_count($bytes, $injectionNeedle).' copy/copies), so the two plant legs would report their own age as a defect.'."\n");
    exit(1);
}
$copies = [];
register_shutdown_function(function () use (&$copies): void {
    foreach ($copies as $c) {
        if (is_file($c)) {
            @unlink($c);
        }
    }
    // Sweep by pattern as well: a leg that dies between the write and its own unlink would otherwise leave a
    // copy of the probe sitting in the directory every later tool walks.
    foreach (glob(dirname(__DIR__, 3).'/storage/app/scratch/lane198-copy-*.php') ?: [] as $stray) {
        @unlink($stray);
    }
});

$run = static function (string $cmd, array $env = []): array {
    putenv('DB_CONNECTION=mysql');
    foreach ($env as $k => $v) {
        putenv($v === null ? $k : $k.'='.$v);
    }
    $lines = [];
    $status = 0;
    exec($cmd.' 2>&1', $lines, $status);

    return [$status, implode("\n", $lines)];
};

$legs = [];
$bad = 0;

$report = static function (string $name, bool $pass, string $detail) use (&$bad, &$legs): void {
    $legs[] = $name;
    echo str_pad($name, 34).' '.($pass ? 'ok' : 'BAD').' '.$detail."\n";
    if (! $pass) {
        $bad++;
    }
};

// ---------------------------------------------------------------- leg 1: the control
[$st, $out] = $run($php.' '.escapeshellarg($tool).' --schema='.$schema);
$controlOk = $st === 0
    && str_contains($out, 'COMMIT')
    && str_contains($out, 'injected 1 time(s)')
    && str_contains($out, 'YES (so the rollback had something to undo)')
    && str_contains($out, 'nothing survived')
    && str_contains($out, 'VERDICT:');
$report('control-must-be-green', $controlOk, "exit {$st}");

// ---------------------------------------------------------------- leg 2: the fault never fires
// The needle moves to a statement this act never issues. If the probe still reported a rollback, the whole
// failure half is a story about a transaction that was never interrupted.
$copy = $root.'/storage/app/scratch/lane198-copy-no-fault.php';
$planted = str_replace($injectionNeedle, 'str_contains($sql, '."'insert into payments'".')', $bytes);
if (substr_count($planted, 'insert into payments') !== 1) {
    $report('needle-moved-fault-never-fires', false, 'MUTANT DID NOT APPLY (the plant left no payment needle)');
} else {
    file_put_contents($copy, $planted);
    $copies[] = $copy;
    [$st, $out] = $run($php.' '.escapeshellarg($copy).' --schema='.$schema);
    $pass = $st === 1
        && str_contains($out, 'injected 0 time(s)')
        && str_contains($out, 'NOTHING (BAD)')
        && ! str_contains($out, 'VERDICT:');
    $report('needle-moved-fault-never-fires', $pass, "exit {$st}");
    @unlink($copy);
    $copies = array_values(array_filter($copies, fn ($c) => $c !== $copy));
}

// ---------------------------------------------------------------- leg 3: the fault fires BEFORE any write
// Firing on the act's first SELECT means no row was ever on the connection when the fault landed, so the
// ORDER line must say NO and the run must refuse. This is the leg that proves "a row had already been
// written" is measured, not assumed from the statement order.
$copy = $root.'/storage/app/scratch/lane198-copy-early-fault.php';
$planted = str_replace($injectionNeedle, 'str_contains($sql, '."'select '".')', $bytes);
if ($planted === $bytes) {
    $report('fault-before-any-write-refuses', false, 'MUTANT DID NOT APPLY');
} else {
    file_put_contents($copy, $planted);
    $copies[] = $copy;
    [$st, $out] = $run($php.' '.escapeshellarg($copy).' --schema='.$schema);
    $pass = $st === 1
        && str_contains($out, 'injected 1 time(s)')
        && str_contains($out, 'NO (BAD — the fault fired before any write)')
        && ! str_contains($out, 'VERDICT:');
    $report('fault-before-any-write-refuses', $pass, "exit {$st}");
    @unlink($copy);
    $copies = array_values(array_filter($copies, fn ($c) => $c !== $copy));
}

// ---------------------------------------------------------------- leg 4: the live database is refused by name
[$st, $out] = $run($php.' '.escapeshellarg($tool).' --schema=ems');
$report('live-schema-refused', $st === 1 && str_contains($out, 'REFUSED:') && str_contains($out, 'ems') && str_contains($out, 'never a rehearsal target'), "exit {$st}");

// ---------------------------------------------------------------- leg 5: an unknown flag is refused by name
[$st, $out] = $run($php.' '.escapeshellarg($tool).' --schema='.$schema.' --nope=1');
$report('unknown-flag-refused', $st === 1 && str_contains($out, 'not read by this tool') && str_contains($out, '--nope'), "exit {$st}");

// ---------------------------------------------------------------- leg 6: an unpinned connection is refused
[$st, $out] = $run($php.' '.escapeshellarg($tool).' --schema='.$schema, ['DB_CONNECTION' => null]);
$report('unpinned-connection-refused', $st === 1 && str_contains($out, "DB_CONNECTION is not pinned to mysql"), "exit {$st}");

// ---------------------------------------------------------------- leg 7: a throw outside the guards exits 1
// A schema that is not there. The first run of the probe met this file's own defect the other way round: an
// uncaught QueryException rendered by the framework while the process still exited 0, which a wrapper reads as
// a verdict about a run that observed nothing.
[$st, $out] = $run($php.' '.escapeshellarg($tool).' --schema=ems_zzz_a');
$report('escape-the-guards-still-refuses', $st === 1 && str_contains($out, 'REFUSED: the probe threw outside its own guards'), "exit {$st}");

// A harness that ran no leg is not a clean harness, so the population is asserted rather than tallied.
$expectedLegs = 7;
if (count($legs) !== $expectedLegs) {
    fwrite(STDERR, 'REFUSED: this harness reported '.count($legs)." leg(s) where its own source declares {$expectedLegs}; a run that skipped a leg is not a pass.\n");
    exit(1);
}
echo "\n".($bad > 0 ? "{$bad} BAD leg(s)" : 'ALL KILLED')." over ".count($legs)." leg(s).\n";
exit($bad > 0 ? 1 : 0);
