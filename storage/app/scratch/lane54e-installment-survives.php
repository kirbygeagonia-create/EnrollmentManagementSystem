<?php

/**
 * Lane 54e — an installment a desk took must survive the generator's ledger pass.
 *
 * `Studentassessments::outstandingBalance()` sums `Payments::held()` — `paid` *or* `partial` — and
 * AccountingController::record() marks a receipt `partial` whenever it leaves a balance (ruling 13). Five
 * places in `tools/seed_full_demo_pipeline.php` summed `paymentStatus = 'paid'` by hand instead, and the pass
 * that matters writes `studentassessments.remainingBalance` from that sum: the generator would recompute a
 * part-paid account as owing the whole assessment again, overwriting the number the cashier wrote, and the
 * shortfall loop beside it would then mint a receipt for money nobody handed over.
 *
 * Measured before writing this: live `ems` holds 0 `partial` receipts, so nothing is wrong today and no test
 * could have shown it — the trigger is one demo installment, which is a button on the Accounting desk. That
 * is why the proof here is a planted receipt on a scratch schema rather than a query against the live one.
 *
 * Two legs on one freshly built dataset, so the only difference between them is which tool ran:
 *   leg 1 — the defect put back into `demo_held()` in a copy of the tool, then an installment, then expect the
 *           stored balance to have been erased (the copy must reproduce the bug or this file proves nothing)
 *   leg 2 — the real tool, an installment, then expect the stored balance to still equal the model's figure
 *
 * Usage: DB_DATABASE=ems_l54_c php storage/app/scratch/lane54e-installment-survives.php
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\PaymentStatus;
use App\Models\Payments;
use App\Models\Staffusers;
use App\Models\Studentassessments;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 3);
$php = getenv('EMS_PHP') ?: 'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';
$tool = 'tools/seed_full_demo_pipeline.php';
$variant = 'storage/app/scratch/lane54e-tool-paid-only-sum.php';
$demoTerm = (int) (getenv('EMS_L54_TERM') ?: 11);
$schema = (string) DB::connection()->getDatabaseName();

if ($schema !== 'ems_l54_c') {
    fwrite(STDERR, "REFUSE: this probe runs `migrate:fresh` and the pipeline. DB_DATABASE must be ems_l54_c, not '{$schema}'.\n");
    exit(1);
}

$failures = 0;

$artisan = function (string $command) use ($php, $root, $schema): array {
    $out = [];
    $code = 0;
    exec('cd /d '.escapeshellarg($root).' && set "DB_DATABASE='.$schema.'" && set "DB_CONNECTION=mysql"'
        .' && set "DB_HOST=127.0.0.1" && set "DB_PORT=3306" && set "DB_USERNAME=root" && set "DB_PASSWORD="'
        .' && '.escapeshellarg($php).' artisan '.$command.' --force 2>&1', $out, $code);

    return [$code, implode("\n", $out)];
};

$pipeline = function (string $script) use ($php, $root, $schema): array {
    $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open([$php, $script], $desc, $pipes, $root, array_merge((array) getenv(), ['DB_DATABASE' => $schema]));
    if (! is_resource($proc)) {
        return [255, 'could not start '.$script];
    }
    $out = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($proc), preg_replace('/\e\[[0-9;]*m/', '', $out)];
};

echo "Building {$schema} the way CI's seed step builds it, then seeding the demo once.\n";
foreach (['migrate:fresh', 'db:seed', 'db:seed --class=DevReferenceDataSeeder'] as $command) {
    [$code, $out] = $artisan($command);
    if ($code !== 0) {
        printf("REFUSE: `artisan %s` exited %d\n%s\n", $command, $code, substr($out, 0, 400));
        exit(1);
    }
}
[$code, $out] = $pipeline($tool);
if ($code !== 0) {
    printf("REFUSE: the pipeline did not build a demo on %s (exit %d)\n%s\n", $schema, $code, substr($out, -1200));
    exit(1);
}

$cashier = Staffusers::where('username', 'office2_head')->first();
if ($cashier === null) {
    echo "REFUSE: no office2_head account on this schema — RbacSeeder never ran here.\n";
    exit(1);
}

$targets = DB::table('studentassessments as a')
    ->join('enrollments as e', 'e.enrollmentId', '=', 'a.enrollmentId')
    ->join('students as s', 's.studentId', '=', 'e.studentId')
    ->where('e.termId', $demoTerm)
    ->where('e.enrollmentStatus', 'assessed')
    ->where('a.remainingBalance', '>', 0)
    ->orderBy('a.assessmentId')
    ->limit(2)
    ->get(['a.assessmentId', 'e.enrollmentId', 's.schoolIdNumber']);

if ($targets->count() < 2) {
    printf("REFUSE: only %d assessed account(s) with a balance — the two legs need one each.\n", $targets->count());
    exit(1);
}

/**
 * The desk's own write, copied from AccountingController::record(): create the receipt as `paid`, read the
 * balance back through the model (which sums held() and already includes the row just written), mark the
 * receipt `partial` if a balance is left, and store that balance. Anything hand-written here would be testing
 * the probe instead of the desk.
 */
$takeInstallment = function (int $assessmentId, int $enrollmentId, float $installment) use ($cashier): array {
    $assessment = Studentassessments::find($assessmentId);

    $receipt = Payments::create([
        'enrollmentId' => $enrollmentId,
        'orNumber' => 'L54E-OR-'.substr((string) microtime(true), -7).'/'.$enrollmentId,
        'amount' => $installment,
        'paymentDate' => now(),
        'paymentMode' => 'cash',
        'processedBy' => $cashier->userId,
        'paymentStatus' => PaymentStatus::Paid,
    ]);

    $balance = $assessment->outstandingBalance();
    if ($balance > 0) {
        $receipt->update(['paymentStatus' => PaymentStatus::Partial]);
    }
    $assessment->update(['remainingBalance' => $balance]);

    return [
        'receipt' => $receipt->paymentStatus->value,
        'stored' => (float) $assessment->fresh()->remainingBalance,
        'model' => (float) $assessment->fresh()->outstandingBalance(),
    ];
};

// leg 1 — put the paid-only sum back into a copy of the tool.
$src = (string) file_get_contents($root.'/'.$tool);
$eol = str_contains($src, "\r\n") ? "\r\n" : "\n";
$from = str_replace("\n", $eol, "    return (float) Payments::held()->where('enrollmentId', \$enrollmentId)->sum('amount');");
$to = str_replace("\n", $eol, "    return (float) Payments::where('enrollmentId', \$enrollmentId)->where('paymentStatus', 'paid')->sum('amount');");

if (substr_count($src, $from) !== 1) {
    printf("REFUSE: the held() body matched %d times, expected 1 — this mutant would sleep.\n", substr_count($src, $from));
    exit(1);
}
$mutant = str_replace($from, $to, $src);
if ($mutant === $src) {
    echo "REFUSE: the planted copy is byte-identical to the real tool.\n";
    exit(1);
}
file_put_contents($root.'/'.$variant, $mutant);
printf("Planted copy written (%s): the helper now counts `paid` only.\n", $variant);

[$aId, $aEnr, $aStudent] = [(int) $targets[0]->assessmentId, (int) $targets[0]->enrollmentId, $targets[0]->schoolIdNumber];
$before = $takeInstallment($aId, $aEnr, 2000.00);
printf("\nLeg 1 — %s owes an installment of ₱2,000.00: receipt marked `%s`, balance stored %.2f, the desk's own figure %.2f\n",
    $aStudent, $before['receipt'], $before['stored'], $before['model']);
if ($before['receipt'] !== 'partial') {
    printf("  BAD  the desk did not mark the receipt `partial` — the probe's emulation is not the desk's write\n");
    exit(1);
}

[$code] = $pipeline($variant);
$after = (float) DB::table('studentassessments')->where('assessmentId', $aId)->value('remainingBalance');
$held = (float) Payments::held()->where('enrollmentId', $aEnr)->sum('amount');
printf("  after the paid-only tool: stored balance %.2f, receipts held %.2f%s\n", $after, $held,
    $after > $before['stored'] + 0.009 ? '  ← the installment was erased' : '  (unchanged — the defect did not reproduce)');

if ($after <= $before['stored'] + 0.009) {
    echo "  FAIL leg 1 did not reproduce the defect, so leg 2's pass would prove nothing\n";
    $failures++;
} else {
    echo "  ok   the paid-only sum overwrites what the cashier wrote — the defect is real and this is what the fix removes\n";
}

// leg 2 — the real tool, on the second account.
[$bId, $bEnr, $bStudent] = [(int) $targets[1]->assessmentId, (int) $targets[1]->enrollmentId, $targets[1]->schoolIdNumber];
$beforeB = $takeInstallment($bId, $bEnr, 2000.00);
printf("\nLeg 2 — %s owes an installment of ₱2,000.00: receipt marked `%s`, balance stored %.2f, the desk's own figure %.2f\n",
    $bStudent, $beforeB['receipt'], $beforeB['stored'], $beforeB['model']);

[$code, $out] = $pipeline($tool);
$afterB = (float) DB::table('studentassessments')->where('assessmentId', $bId)->value('remainingBalance');
$stillHeld = (float) Payments::held()->where('enrollmentId', $bEnr)->sum('amount');
printf("  after the real tool: stored balance %.2f, receipts held %.2f (exit %d)\n", $afterB, $stillHeld, $code);

if (abs($afterB - $beforeB['stored']) > 0.009) {
    printf("  FAIL the real tool still moved the balance the desk wrote: %.2f → %.2f\n", $beforeB['stored'], $afterB);
    echo substr($out, -1200), "\n";
    $failures++;
} else {
    echo "  ok   the installment survives the generator's ledger pass, and the column still matches outstandingBalance()\n";
}

// The tool counts overpayment against the same money, so an erased installment must not survive as a finding.
$overpaidLine = null;
foreach (explode("\n", $out) as $line) {
    if (str_starts_with(trim($line), 'Ledger — receipts exceeding the assessment')) {
        $overpaidLine = trim($line);
    }
}
printf("  %s\n", $overpaidLine ?? 'BAD  the report printed no `receipts exceeding` line — nothing was measured');

echo "\n", $failures === 0
    ? "Leg 1 reproduced the erasure and leg 2 does not: `demo_held()` is what keeps a desk's installment.\n"
    : "{$failures} leg(s) failed — do not report the fix as proved.\n";

exit($failures === 0 ? 0 : 1);
