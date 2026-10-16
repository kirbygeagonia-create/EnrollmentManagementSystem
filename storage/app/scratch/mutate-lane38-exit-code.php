<?php

// Mutation probe for the demo pipeline's own exit status.
//
// tools/seed_full_demo_pipeline.php prints its desk report and used to exit 0 whatever that report said. The
// file's own header records the consequence, measured 2026-10-12: run against a database missing its base
// rows it printed an exception and still returned success, so a gate reading the status — CI, or DEPLOYMENT.md
// §8 followed by hand — recorded a pass on half a demo. The banner is now conditional, and this file feeds it
// a finding to prove the refusal is real: an inflated receipt on a demo-term account, which the pipeline
// flags as "receipts exceeding the assessment" and deliberately does NOT repair.
//
// Usage: DB_DATABASE=ems_lane38_ci php storage/app/scratch/mutate-lane38-exit-code.php
// Run without that prefix it reports the refusal and stops — the same guard the record-state probe carries,
// measured by the plain invocation that a careless operator types.

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

const SCRATCH = 'ems_lane38_ci';

$php = 'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';
$root = dirname(__DIR__, 3);

if (DB::connection()->getDatabaseName() !== SCRATCH) {
    fwrite(STDERR, 'REFUSE: this probe rewrites a payment row and only belongs in \''
        .SCRATCH.'\', not in \''.DB::connection()->getDatabaseName().'\'.'."\n");
    exit(1);
}

// A receipt on an enrollment that already carries a fee sheet, so the overpayment is the only thing wrong
// with the account and the ledger pass has nothing else to do.
$sheet = DB::table('studentassessments as a')
    ->join('enrollments as e', 'e.enrollmentId', '=', 'a.enrollmentId')
    ->where('e.termId', 11)
    ->where('a.totalAssessedAmount', '>', 0)
    ->orderByDesc('a.assessmentId')
    ->first();

if ($sheet === null) {
    fwrite(STDERR, "REFUSE: no assessed enrollment on term 11 — the demo pipeline never ran here.\n");
    exit(1);
}

$receipt = DB::table('payments')->where('enrollmentId', $sheet->enrollmentId)->orderByDesc('amount')->first();
$original = (array) $receipt;

$run = function () use ($php, $root): array {
    $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open(
        [$php, 'tools/seed_full_demo_pipeline.php'],
        $desc,
        $pipes,
        $root,
        array_merge((array) getenv(), ['DB_DATABASE' => SCRATCH])
    );
    $out = preg_replace('/\e\[[0-9;]*m/', '', stream_get_contents($pipes[1]).stream_get_contents($pipes[2]));

    foreach ($pipes as $p) {
        fclose($p);
    }

    return ['status' => proc_close($proc), 'out' => $out];
};

$verdicts = [];

$check = function (string $what, int $want, bool $wantBanner, array $result) use (&$verdicts): void {
    $hasBanner = str_contains($result['out'], 'DEMO-DAY DATASET FULLY SEEDED');
    $ok = $result['status'] === $want && $hasBanner === $wantBanner;
    $verdicts[] = $ok;

    printf(
        "%s  %-56s exit %-2d banner %-5s\n",
        $ok ? 'PASS' : 'FAIL',
        $what,
        $result['status'],
        $hasBanner ? 'printed' : 'absent'
    );

    if (! $ok) {
        echo "     expected exit {$want} with banner ".($wantBanner ? 'printed' : 'absent')."; output follows.\n"
            .$result['out']."\n";
    }
};

// Control: the schema as the last pipeline run left it.
$check('control — nothing planted', 0, true, $run());

// Plant: a receipt larger than the assessment it belongs to, which the report counts and does not repair.
DB::table('payments')->where('paymentId', $receipt->paymentId)
    ->update(['amount' => (float) $sheet->totalAssessedAmount + 5000]);

$planted = $run();
$check('receipt inflated past its assessment', 1, false, $planted);
$verdicts[] = str_contains($planted['out'], 'receipts exceeding')
    || str_contains($planted['out'], 'Ledger — receipts exceeding the assessment');
printf(
    "%s  %-56s\n",
    $verdicts[count($verdicts) - 1] ? 'PASS' : 'FAIL',
    'the finding names the ledger, not something else'
);

DB::table('payments')->where('paymentId', $receipt->paymentId)->update($original);
$check('receipt restored', 0, true, $run());

$left = (float) DB::table('payments')->where('paymentId', $receipt->paymentId)->value('amount');
$verdicts[] = abs($left - (float) $original['amount']) < 0.005;
printf("%s  %-56s ₱%s\n", $verdicts[count($verdicts) - 1] ? 'PASS' : 'FAIL',
    'restore returns the receipt to its amount', number_format($left, 2));

$failed = count(array_filter($verdicts, fn (bool $ok): bool => ! $ok));

echo "\n".($failed === 0
    ? "The pipeline refuses the success it used to print, and only that.\n"
    : "{$failed} verdict(s) failed — the exit status is not yet a gate anyone can trust.\n");

exit($failed === 0 ? 0 : 1);
