<?php

/**
 * Lane 59 — measure what each instrument prints, once, into a readings file.
 *
 * The checker needs readings and the checker is run a dozen times by its mutant harness; running fourteen tools each
 * time is not measurement, it is a timeout waiting to happen. This collector runs each instrument once and writes the
 * numbers it printed, with the day it ran, so a stale reading is visible in the file rather than silently reused.
 *
 * The demo pipeline is NOT run: it writes live `ems`. Its Ledger-line count is read from the log the last gated run left
 * behind, and the file says so — a reading copied from a log is a different kind of fact from one measured now, and
 * the difference belongs in the artifact.
 *
 *   php storage/app/scratch/lane59-collect-readings.php [--only=a,b]
 */

$root = dirname(__DIR__, 3);
$scratch = $root.'/storage/app/scratch';
$check = escapeshellarg($root.'/storage/app/scratch/lane59-instrument-counts.php');
$php = escapeshellarg(PHP_BINARY);

// The checker owns the instrument table; this script asks it to run the set and dump the readings, so the two can never
// disagree about what a summary line means. --dump-readings is the only reason that mode exists.
$only = null;
foreach ($argv as $a) {
    if (preg_match('/^--only=(.*)$/', $a, $m) === 1) {
        $only = ' --only='.escapeshellarg($m[1]);
    }
}

$out = (string) shell_exec('cd '.escapeshellarg($root).' && '.$php.' '.$check.' --run --dump-readings'.$only.' 2>&1');
preg_match('/READINGS_JSON:(\{.*\})/s', $out, $j);
if ($j === []) {
    echo "REFUSED: the checker ran no instrument to completion, so there is nothing to write.\n";
    echo substr($out, 0, 1200)."\n";
    exit(1);
}
$file = $scratch.'/lane59-readings.json';
file_put_contents($file, json_encode(json_decode($j[1], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "wrote ".basename($file)."\n";
echo substr($out, 0, 1500)."\n";
exit(0);
