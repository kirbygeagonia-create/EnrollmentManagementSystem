<?php

/**
 * Prove that lane51-reconcile-cites.php can fail.
 *
 * The checker's job is to say whether a docx pass moved every citation into a file the batch changed. A checker
 * that reports "0 broken" because it matched nothing looks exactly like one that checked everything, so each
 * case plants one defect in a COPY of the flat dump, points the checker at it with --dump=, and requires the
 * named complaint. A case whose mutation leaves the copy byte-identical refuses — that is how the lane 44
 * harness slept through two of its own cases.
 *
 *   php storage/app/scratch/mutate-lane51-cites.php
 */

$root = dirname(__DIR__, 3);
$scratch = $root.'/storage/app/scratch';
$clean = $scratch.'/docx-flat.txt';
$checker = $scratch.'/lane51-reconcile-cites.php';
$work = $scratch.'/lane51-mutant-dump.txt';

if (! is_file($clean) || ! is_file($checker)) {
    fwrite(STDERR, "REFUSE: no clean dump or no checker to mutate\n");

    exit(1);
}

$bytes = (string) file_get_contents($clean);
$run = function (string $dump) use ($root, $checker): array {
    $out = (string) shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($checker).' --dump='.escapeshellarg($dump).' 2>&1');
    // shell_exec gives no exit code, so the verdict line is read instead — and a run that prints no verdict
    // line is a failure of the harness, not a pass.
    preg_match('/(\d+) file\(s\) carry a line map; (\d+) set\(s\) broken; (\d+) settled; (\d+) citation\(s\)[^;]*; (\d+) item\(s\)/', $out, $m);

    return ['out' => $out, 'broken' => isset($m[2]) ? (int) $m[2] : -1, 'queue' => isset($m[5]) ? (int) $m[5] : -1];
};



$fail = 0;
$report = function (bool $ok, string $what) use (&$fail): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);

    if (! $ok) {
        $fail++;
    }
};

// ---- baseline: the tree as it stands has to be clean, or every case below proves nothing

$base = $run($clean);
$report($base['broken'] === 0, 'baseline on the real dump: 0 broken sets (got '.count($base).')');

// ---- case 1: put a qualified pointer back where the file no longer is --------------

$find = 'PrintSlip.jsx:89-94';
$replace = 'PrintSlip.jsx:88-93';
$mutated = str_replace($find, $replace, $bytes, $c1);

if ($c1 === 0 || $mutated === $bytes) {
    fwrite(STDERR, "REFUSE: case 1 anchored on a line that is not in the dump — the mutation planted nothing\n");

    exit(1);
}

file_put_contents($work, $mutated);
$r = $run($work);
 $report($r['broken'] >= 1 && str_contains($r['out'], 'SET MISMATCH') && str_contains($r['out'], 'PrintSlip.jsx'),
    'case 1 — a pointer reverted to HEAD\'s number is reported as a broken set');

// ---- case 2: move one too far ------------------------------------------------------

$find = 'WorkflowStepper.jsx:13';
$replace = 'WorkflowStepper.jsx:14';
$mutated = str_replace($find, $replace, $bytes, $c2);

if ($c2 === 0 || $mutated === $bytes) {
    fwrite(STDERR, "REFUSE: case 2 planted nothing\n");

    exit(1);
}

file_put_contents($work, $mutated);
$r = $run($work);
$report($r['broken'] >= 1 && str_contains($r['out'], 'WorkflowStepper.jsx'),
    'case 2 — a pointer moved past the line the file reached is reported');

// ---- case 3: leave a bare continuation behind -------------------------------------

$find = 'again at :274';
$replace = 'again at :273';
$mutated = str_replace($find, $replace, $bytes, $c3);

if ($c3 === 0 || $mutated === $bytes) {
    fwrite(STDERR, "REFUSE: case 3 planted nothing\n");

    exit(1);
}

file_put_contents($work, $mutated);
$r = $run($work);
$report(str_contains($r['out'], 'BARE UNMOVED?') && $r['queue'] >= 1,
    'case 3 — a bare continuation the pass forgot is queued for reading');

// ---- case 4: prove --dump= is honoured, not merely accepted ------------------------

file_put_contents($work, $bytes);
$asCopy = $run($work);
$asOriginal = $run($clean);
$touch = $scratch.'/lane51-mutant-flag.txt';
file_put_contents($touch, str_replace('WorkflowStepper.jsx:13', 'WorkflowStepper.jsx:99', $bytes));
$flagged = $run($touch);
$report($asCopy['broken'] === $asOriginal['broken'] && $flagged['broken'] > $asOriginal['broken'],
    'case 4 — the verdict follows the file named by --dump= (an ignored flag would hide the defect)');

// ---- case 5: a mutation that cannot match must refuse, not report clean ------------

$mutated = str_replace('WorkflowStepper.jsx:4242', 'WorkflowStepper.jsx:13', $bytes, $c5);
$report($c5 === 0 && $mutated === $bytes, 'case 5 — a needle that matches nothing leaves the copy byte-identical (the harness would refuse it)');

@unlink($work);
@unlink($touch);

printf("\n%d case(s) failed.\n", $fail);

exit($fail === 0 ? 0 : 1);
