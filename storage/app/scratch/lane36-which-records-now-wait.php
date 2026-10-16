<?php

/**
 * Lane 36 follow-up: which enrollments sit behind the two box rules, and when did they get there?
 *
 * The record-state register read 0 and 1 on live `ems` right after the retire and the prune; the same
 * command read 5 and 6 after `tools/seed_full_demo_pipeline.php` ran again ten minutes later. This names
 * the rows so the difference can be read rather than argued — created, or already there and merely
 * re-signed — and it touches nothing.
 */

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\OfficeId;
use Illuminate\Support\Facades\DB;

foreach (['Clinic' => OfficeId::Clinic->value, 'ID' => OfficeId::IdOffice->value] as $name => $office) {
    $rows = DB::table('enrollments as e')
        ->join('enrollmentworkflow as w', 'w.enrollmentId', '=', 'e.enrollmentId')
        ->join('workflowsteps as ws', 'ws.workflowId', '=', 'w.workflowId')
        ->where('ws.officeId', $office)
        ->where('ws.stepStatus', 'completed')
        ->where('e.enrollmentStatus', '!=', 'dropped')
        ->when($office === OfficeId::Clinic->value, fn ($q) => $q->whereNotExists(fn ($x) => $x
            ->select(DB::raw(1))->from('clinicrecords as cr')->whereColumn('cr.enrollmentId', 'e.enrollmentId')))
        ->when($office === OfficeId::IdOffice->value, fn ($q) => $q->whereNotExists(fn ($x) => $x
            ->select(DB::raw(1))->from('idrequests as ir')->whereColumn('ir.enrollmentId', 'e.enrollmentId')
            ->where('ir.status', 'validated')))
        ->groupBy('e.enrollmentId')->orderBy('e.enrollmentId')
        ->get(['e.enrollmentId', 'e.studentId', 'e.termId', 'e.enrollmentStatus', 'e.created_at',
            DB::raw('MAX(ws.signedDate) signed_at'), DB::raw('COUNT(*) boxes')]);

    echo "=== {$name}: {$rows->count()} enrollment(s) ===\n";
    foreach ($rows as $r) {
        printf("  enroll %-5s student %-5s term %-3s %-12s created %s  box signed %s (%d box)\n",
            $r->enrollmentId, $r->studentId, $r->termId, $r->enrollmentStatus, $r->created_at, $r->signed_at, $r->boxes);
    }
}

echo "\n=== how many of these rows the pipeline wrote today ===\n";
$today = DB::table('enrollments')->whereDate('created_at', now()->toDateString())->count();
printf("enrollments created today: %d | total: %d\n", $today, DB::table('enrollments')->count());
printf("clinicrecords on file: %d | validated idrequests: %d\n",
    DB::table('clinicrecords')->count(), DB::table('idrequests')->where('status', 'validated')->count());
