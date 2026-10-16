<?php

// Read-only measure of the four warning classes the demo pipeline's own report counts, so the question
// "would a non-zero exit on warnings fail today's database?" is answered by measurement rather than by
// assuming the rehearsal schema and the live one agree.
//
// Usage: php storage/app/scratch/lane38-warning-measure.php          (live ems, the .env default)
//        DB_DATABASE=ems_lane38_ci php storage/app/scratch/lane38-warning-measure.php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\WorkflowStepStatus;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$termId = 11;

echo 'Database: '.DB::connection()->getDatabaseName()."\n\n";

$unattributedSlips = DB::table('studentclearances as sc')
    ->join('clearanceperiods as cp', 'cp.clearancePeriodId', '=', 'sc.clearancePeriodId')
    ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('enrollments as e')
        ->whereColumn('e.studentId', 'sc.studentId')
        ->whereColumn('e.termId', 'cp.termId'))
    ->count();

$noOfferings = DB::table('enrollments as e')
    ->join('academicterms as t', 't.termId', '=', 'e.termId')
    ->join('curriculums as cu', 'cu.courseId', '=', 'e.courseId')
    ->whereNotIn('e.enrollmentStatus', ['dropped', 'cancelled'])
    ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('curriculumsubjects as cs')
        ->whereColumn('cs.curriculumId', 'cu.curriculumId')
        ->whereColumn('cs.yearLevel', 'e.yearLevel')
        ->whereColumn('cs.semesterOffered', 't.semester'))
    ->count();

$foreignSigners = DB::table('workflowsteps as ws')
    ->join('staffusers as s', 's.userId', '=', 'ws.signedBy')
    ->where('ws.stepStatus', WorkflowStepStatus::Completed->value)
    ->whereRaw('s.officeId <> ws.officeId')
    ->count();

// The pipeline folds the overpaid accounts into the same printed list, so they gate the same way.
$overpaid = 0;

foreach (DB::table('studentassessments as a')
    ->join('enrollments as e', 'e.enrollmentId', '=', 'a.enrollmentId')
    ->where('e.termId', $termId)
    ->select('a.enrollmentId', 'a.totalAssessedAmount', 'a.totalScholarshipCoverage', 'a.totalWaived')
    ->get() as $sheet) {
    $paid = (float) DB::table('payments')->where('enrollmentId', $sheet->enrollmentId)
        ->where('paymentStatus', 'paid')->sum('amount');
    $due = (float) $sheet->totalAssessedAmount - (float) $sheet->totalScholarshipCoverage - (float) $sheet->totalWaived;

    if ($paid > $due + 0.009) {
        $overpaid++;
    }
}

$foreignApprovals = DB::table('clearanceapprovals as ca')
    ->join('clearancerequirements as cr', 'cr.clearanceRequirementId', '=', 'ca.clearanceRequirementId')
    ->join('staffusers as s', 's.userId', '=', 'ca.approvedBy')
    ->where('ca.status', 'approved')
    ->whereRaw('s.officeId <> cr.officeId')
    ->get(['ca.clearanceApprovalId', 'cr.officeId', 's.username', 's.officeId as signerOffice']);

foreach ([
    'clearance slips naming a student with no enrollment in the period term' => $unattributedSlips,
    'active enrollments at a level/term their curriculum offers nothing for' => $noOfferings,
    'completed boxes signed by an officer from another office' => $foreignSigners,
    'approved clearance lines signed by an officer from another office' => $foreignApprovals->count(),
    'receipts exceeding the assessment on the demo term' => $overpaid,
] as $label => $count) {
    printf("  %s  %s\n", $count === 0 ? 'ok  ' : sprintf('n=%d', $count), $label);
}

if ($foreignApprovals->isNotEmpty()) {
    foreach ($foreignApprovals as $row) {
        printf(
            "       approval %-6s requirement office %-3d signed by %-14s (office %d)\n",
            $row->clearanceApprovalId,
            $row->officeId,
            $row->username,
            $row->signerOffice
        );
    }
}
