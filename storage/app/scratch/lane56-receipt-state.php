<?php

/**
 * Lane 56 — read, or plant, the demo installment the ledger harness needs, inside the booted application.
 *
 * A probe that shells out to `tinker --execute` cannot be trusted to say what it did (quoting on this box eats the
 * statement), and `shell_exec` cannot reach `git` from PHP here either, so the harness asks this file instead. It
 * prints key=value lines the harness parses, and prints `status=MISSING` rather than throwing, so a harness pointed
 * at an unseeded schema refuses instead of reporting a clean control.
 *
 * Usage: DB_DATABASE=<scratch> php storage/app/scratch/lane56-receipt-state.php [read|plant]
 */

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$mode = $argv[1] ?? 'read';
$receipt = DB::table('payments')->where('orNumber', 'DEMO-OR-0002')->first();

if ($receipt === null) {
    echo "status=MISSING\nbalance=NONE\n";
    exit(0);
}

$sheet = DB::table('studentassessments')->where('enrollmentId', $receipt->enrollmentId)->first();
$changed = 0;

if ($mode === 'plant') {
    $changed = DB::table('payments')->where('paymentId', $receipt->paymentId)
        ->update(['paymentStatus' => 'paid']);
    $receipt = DB::table('payments')->where('paymentId', $receipt->paymentId)->first();
}

printf("status=%s\nbalance=%s\nchanged=%d\n", $receipt->paymentStatus,
    $sheet ? $sheet->remainingBalance : 'NO-SHEET', $changed);
