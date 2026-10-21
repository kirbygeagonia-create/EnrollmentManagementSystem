<?php

/**
 * Lane 57 — plant and unplant the two states the new rules count, on a scratch schema only.
 *
 * `over` writes the receipt the desk may no longer write (the balance plus ₱1,500), settles the stored balance to
 * ₱0.00 exactly as `record()` would have, and records the `paid` step so the *only* rule that complains is the
 * over-collection one — a case that fires two rules at once cannot say which one it proved.
 *
 * `paid_status` leaves an honestly-`partial` installment on a sheet and moves the enrollment's own status into
 * `paid`, which is the residue a probe of mine produced when it deleted a receipt, recomputed the balance, and never
 * walked the status back. Eighteen rules read 0 on that state; the nineteenth names it.
 *
 * Every plant stores what it found in a sidecar so `undo_*` restores the row it touched rather than guessing, and
 * both plants refuse a schema that cannot carry the state instead of reporting a clean run.
 *
 *   DB_DATABASE=ems_l57_a php storage/app/scratch/lane57-plant-state.php over|undo-over|paid-status|undo-paid-status
 */

$root = dirname(__DIR__, 3);
$mode = $argv[1] ?? '';

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\EnrollmentStatus;
use App\Models\Enrollments;
use App\Models\Enrollmentstatushistory;
use App\Models\Payments;
use App\Models\Staffusers;
use App\Models\Studentassessments;

$database = (string) config('database.connections.mysql.database');
if ($database === 'ems' || $database === '') {
    fwrite(STDERR, "REFUSED: this planter writes money; the connection names '{$database}'.\n");
    exit(1);
}

$sidecar = $root.'/storage/app/scratch/lane57-planted-'.$database.'.json';

/** The account both states are staged on: an assessed enrollment whose sheet owes. */
$sheet = function (bool $needsHeldMoney = false): ?Studentassessments {
    $q = Studentassessments::where('totalAssessedAmount', '>', 0)
        ->where('remainingBalance', '>', 0)
        ->whereHas('enrollment', fn ($e) => $e->where('enrollmentStatus', EnrollmentStatus::Assessed->value));

    if ($needsHeldMoney) {
        // The status rule is about a claim the money no longer supports, so it can only be staged where some money
        // is actually held: a `paid` status over an account with no receipts at all is §28.3 G-17's covered account,
        // a state the register says is legitimate.
        $q->whereHas('payments', fn ($p) => $p->where('paymentStatus', 'paid')
            ->orWhere('paymentStatus', 'partial'));
    }

    return $q->orderBy('enrollmentId')->first();
};

$signer = fn (): Staffusers => Staffusers::where('username', 'office2_head')->firstOrFail();

if ($mode === 'over') {
    $assessment = $sheet();
    if ($assessment === null) {
        fwrite(STDERR, "REFUSED: no assessed enrollment holds a balance on {$database}, so an over-collection cannot be staged.\n");
        exit(1);
    }

    $enrollment = $assessment->enrollment;
    $balance = (float) $assessment->remainingBalance;
    $amount = round($balance + 1500.00, 2);

    file_put_contents($sidecar, json_encode([
        'assessmentId' => $assessment->assessmentId,
        'enrollmentId' => $enrollment->enrollmentId,
        'orNumber' => 'L57-OVER-'.$enrollment->enrollmentId,
        'prior_status' => $enrollment->enrollmentStatus->value,
        'prior_balance' => $balance,
    ]));

    Payments::insert([
        'enrollmentId' => $enrollment->enrollmentId,
        'orNumber' => 'L57-OVER-'.$enrollment->enrollmentId,
        'amount' => $amount,
        'paymentDate' => now(),
        'paymentMode' => 'cash',
        'processedBy' => $signer()->userId,
        'paymentStatus' => 'paid',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // What the drawer would have stored: the clamp in `outstandingBalance()` makes the excess invisible to the column.
    $assessment->update(['remainingBalance' => 0.00]);
    Enrollmentstatushistory::insert([
        'enrollmentId' => $enrollment->enrollmentId,
        'fromStatus' => $enrollment->enrollmentStatus->value,
        'toStatus' => EnrollmentStatus::Paid->value,
        'changedBy' => $signer()->userId,
        'remarks' => 'Planted by storage/app/scratch/lane57-plant-state.php',
        'changedAt' => now(),
    ]);
    $enrollment->update(['enrollmentStatus' => EnrollmentStatus::Paid]);

    $held = (float) Payments::held()->where('enrollmentId', $enrollment->enrollmentId)->sum('amount');
    $due = round((float) $assessment->totalAssessedAmount - (float) $assessment->totalScholarshipCoverage - (float) $assessment->totalWaived, 2);

    printf("changed=1\nenrollment=%d\nstored_balance=%.2f\nheld=%.2f\ndue=%.2f\nover=%.2f\n",
        $enrollment->enrollmentId, 0.00, $held, $due, $held - $due);
    exit(0);
}

if ($mode === 'undo-over') {
    $state = json_decode((string) file_get_contents($sidecar), true);
    if (! is_array($state)) {
        fwrite(STDERR, "REFUSED: no sidecar for {$database}; nothing was planted by this harness.\n");
        exit(1);
    }

    $n = Payments::where('orNumber', $state['orNumber'])->delete();
    $assessment = Studentassessments::find($state['assessmentId']);
    $assessment->update(['remainingBalance' => round((float) $assessment->outstandingBalance(), 2)]);
    Enrollments::where('enrollmentId', $state['enrollmentId'])->update(['enrollmentStatus' => $state['prior_status']]);
    Enrollmentstatushistory::where('enrollmentId', $state['enrollmentId'])
        ->where('remarks', 'Planted by storage/app/scratch/lane57-plant-state.php')->delete();
    unlink($sidecar);

    printf("changed=%d\nrestored_status=%s\nbalance=%.2f\n", max(1, (int) $n), $state['prior_status'],
        (float) $assessment->fresh()->remainingBalance);
    exit(0);
}

if ($mode === 'paid-status') {
    $assessment = $sheet(true);
    if ($assessment === null) {
        fwrite(STDERR, "REFUSED: no assessed enrollment holds a balance on {$database}, so the status cannot disagree with money.\n");
        exit(1);
    }

    $enrollment = $assessment->enrollment;
    if ((float) Payments::held()->where('enrollmentId', $enrollment->enrollmentId)->sum('amount') <= 0) {
        fwrite(STDERR, "REFUSED: that account holds no receipt at all, and a `paid` status over nothing is a different state (a grant covered it).\n");
        exit(1);
    }

    file_put_contents($sidecar, json_encode([
        'assessmentId' => $assessment->assessmentId,
        'enrollmentId' => $enrollment->enrollmentId,
        'prior_status' => $enrollment->enrollmentStatus->value,
    ]));

    Enrollmentstatushistory::insert([
        'enrollmentId' => $enrollment->enrollmentId,
        'fromStatus' => $enrollment->enrollmentStatus->value,
        'toStatus' => EnrollmentStatus::Paid->value,
        'changedBy' => $signer()->userId,
        'remarks' => 'Planted by storage/app/scratch/lane57-plant-state.php',
        'changedAt' => now(),
    ]);
    $enrollment->update(['enrollmentStatus' => EnrollmentStatus::Paid]);

    printf("changed=1 enrollment=%d status=paid while balance=%.2f is owed\n",
        $enrollment->enrollmentId, (float) $assessment->remainingBalance);
    exit(0);
}

if ($mode === 'undo-paid-status') {
    $state = json_decode((string) file_get_contents($sidecar), true);
    if (! is_array($state)) {
        fwrite(STDERR, "REFUSED: no sidecar for {$database}; nothing was planted by this harness.\n");
        exit(1);
    }

    Enrollments::where('enrollmentId', $state['enrollmentId'])->update(['enrollmentStatus' => $state['prior_status']]);
    Enrollmentstatushistory::where('enrollmentId', $state['enrollmentId'])
        ->where('remarks', 'Planted by storage/app/scratch/lane57-plant-state.php')->delete();
    unlink($sidecar);

    printf("changed=1 restored_status=%s\n", $state['prior_status']);
    exit(0);
}

fwrite(STDERR, "REFUSED: unknown mode '{$mode}'.\n");
exit(1);
