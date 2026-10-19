<?php

/**
 * Lane 54 — what live `ems` would say to the four Ledger lines, read before the tool is run against it.
 *
 * The pipeline writes the demo dataset, and lane 54 changed four of the rules it applies while doing it. On
 * this box `ems` already holds a dataset that lanes 36 to 53 repaired by hand, so running the changed tool over
 * it would either confirm the repairs or quietly re-open them. This probe asks the same four questions the
 * tool's own report now asks, against the rows as they stand, and writes nothing.
 *
 * Usage: php storage/app/scratch/lane54-live-ledger-baseline.php
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Models\Enrollments;
use App\Models\Payments;
use Illuminate\Support\Facades\DB;

$term = (int) (getenv('EMS_L54_TERM') ?: 11);
$database = (string) DB::connection()->getDatabaseName();

if ($database !== 'ems') {
    fwrite(STDERR, "REFUSE: this probe reads live `ems` and is connected to '{$database}'.\n");
    exit(1);
}

$enrollments = DB::table('enrollments')->where('termId', $term)->count();
$sheets = DB::table('studentassessments')->count();
printf("Connected to `%s`, demo term %d: %d enrollment(s), %d fee sheet(s).\n\n", $database, $term, $enrollments, $sheets);

if ($enrollments === 0 || $sheets === 0) {
    fwrite(STDERR, "NO CONCLUSION: the demo term or the assessment table is empty, so every count below would read 0 by absence.\n");
    exit(1);
}

// 1 — a record claiming a returning student while holding no completed year (G-11).
$returning = Enrollments::query()
    ->where('termId', $term)
    ->where('enrollmentType', EnrollmentType::Old->value)
    ->whereNotIn('enrollmentStatus', [EnrollmentStatus::Dropped->value])
    ->get(['enrollmentId', 'studentId', 'termId', 'yearLevel'])
    ->reject(fn (Enrollments $e) => Enrollments::derivedYearLevel((int) $e->studentId, (int) $e->termId) >= 2);

echo "1. returning claim with no year behind it: ".$returning->count()."\n";
foreach ($returning as $row) {
    printf("     enrollment %d (student %d) sits year %d and derives %d\n",
        $row->enrollmentId, $row->studentId, $row->yearLevel,
        Enrollments::derivedYearLevel((int) $row->studentId, (int) $row->termId));
}

// 2 — a fee sheet that quotes a total with nothing priced under it.
$bare = DB::table('studentassessments as a')
    ->join('enrollments as e', 'e.enrollmentId', '=', 'a.enrollmentId')
    ->where('e.termId', $term)
    ->where('a.totalAssessedAmount', '>', 0)
    ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('charges as c')
        ->whereColumn('c.assessmentId', 'a.assessmentId'))
    ->get(['a.assessmentId', 'a.totalAssessedAmount']);

echo "\n2. fee sheet with a total and no lines: ".$bare->count()."\n";
foreach ($bare as $row) {
    $priceable = abs((float) $row->totalAssessedAmount - 17500.0) < 0.005;
    printf("     assessment %d totals %.2f — %s\n", $row->assessmentId, (float) $row->totalAssessedAmount,
        $priceable ? 'the demo line sum, so the tool would price it' : 'not the demo line sum, so the tool would report it');
}

// 3 — money on an account that never entered `paid`.
$noTrail = Enrollments::whereIn('enrollmentId', Payments::held()->select('enrollmentId'))
    ->where('termId', $term)
    ->whereHas('studentassessments', fn ($q) => $q->where('totalAssessedAmount', '>', 0)->where('remainingBalance', '<=', 0))
    ->whereDoesntHave('enrollmentstatushistory', fn ($q) => $q->where('toStatus', EnrollmentStatus::Paid->value))
    ->get(['enrollmentId', 'enrollmentStatus']);

echo "\n3. settled account with no `assessed -> paid` step: ".$noTrail->count()."\n";
foreach ($noTrail as $row) {
    printf("     enrollment %d stands at %s\n", $row->enrollmentId, $row->enrollmentStatus->value ?? $row->enrollmentStatus);
}

// 4 — receipts exceeding the sheet, computed the way the tool computes it: only `paid` receipts count toward
//    its recompute, while the application's own `outstandingBalance()` reads `Payments::held()`. Section 5 asks
//    whether that difference is live on this database or only latent.
$overpaid = collect();
$diverged = collect();
$heldOnly = collect();

foreach (DB::table('studentassessments as a')
    ->join('enrollments as e', 'e.enrollmentId', '=', 'a.enrollmentId')
    ->join('students as s', 's.studentId', '=', 'e.studentId')
    ->where('e.termId', $term)
    ->select('a.assessmentId', 'a.enrollmentId', 'a.remainingBalance', 'a.totalAssessedAmount',
        'a.totalScholarshipCoverage', 'a.totalWaived', 's.schoolIdNumber')->get() as $sheet) {
    $paidOnly = (float) DB::table('payments')->where('enrollmentId', $sheet->enrollmentId)
        ->where('paymentStatus', 'paid')->sum('amount');
    $held = (float) DB::table('payments')->where('enrollmentId', $sheet->enrollmentId)
        ->whereIn('paymentStatus', ['paid', 'partial'])->sum('amount');
    $due = max(0, (float) $sheet->totalAssessedAmount - (float) $sheet->totalScholarshipCoverage - (float) $sheet->totalWaived);
    $toolBalance = max(0.0, $due - $paidOnly);
    $appBalance = max(0.0, $due - $held);

    if ($paidOnly > $due + 0.009) {
        $overpaid->push((object) ['sheet' => $sheet, 'held' => $paidOnly]);
    }

    if (abs($toolBalance - $appBalance) > 0.009 || abs($toolBalance - (float) $sheet->remainingBalance) > 0.009) {
        $diverged->push((object) ['sheet' => $sheet, 'tool' => $toolBalance, 'app' => $appBalance,
            'stored' => (float) $sheet->remainingBalance]);
    }

    if ($held - $paidOnly > 0.009) {
        $heldOnly->push((object) ['sheet' => $sheet, 'partial' => $held - $paidOnly]);
    }
}

echo "\n4. receipts exceeding the assessment (the tool's own predicate): ".$overpaid->count()."\n";
foreach ($overpaid as $row) {
    printf("     assessment %d has %.2f paid against a %.2f due\n", $row->sheet->assessmentId, $row->held,
        max(0, (float) $row->sheet->totalAssessedAmount - (float) $row->sheet->totalScholarshipCoverage - (float) $row->sheet->totalWaived));
}

echo "\n5. accounts where a `partial` receipt exists, so the tool's paid-only recompute and the application's\n";
echo "   held() balance are different numbers: ".$heldOnly->count()."\n";
foreach ($heldOnly as $row) {
    printf("     assessment %d of %s carries %.2f in `partial` the tool's recompute does not see\n",
        $row->sheet->assessmentId, $row->sheet->schoolIdNumber, $row->partial);
}

echo "\n6. sheets whose stored balance disagrees with either recompute: ".$diverged->count()."\n";
foreach ($diverged as $row) {
    printf("     assessment %d of %s stores %.2f; paid-only says %.2f, held() says %.2f\n",
        $row->sheet->assessmentId, $row->sheet->schoolIdNumber, $row->stored, $row->tool, $row->app);
}

echo "\n(1) and (3) are the two this batch closes in the generator; (2) and (4) are lane 53's and lane 38's\n";
echo "counters, carried forward; (5) and (6) ask whether the tool's paid-only recompute can disagree with the\n";
echo "application's held() balance on rows that exist today. A non-zero line here says what running the changed\n";
echo "tool over live `ems` would change, not that anything is broken — this probe writes nothing.\n";
