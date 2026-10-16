<?php

/**
 * Lane 36 follow-up: are the five rows the pipeline made today the same population the retire dropped,
 * and does anything in the pipeline stand between them and being re-made on the next run?
 *
 * Read-only. Prints the retired manifest beside the live Dropped rows and the new ones, and the two acts
 * each signed box would need (a clinic record, a validated ID request) for every prior-term record.
 */

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$manifest = glob($root.'/storage/app/scratch/manifest-retire-superseded-*.json')[0] ?? null;
echo "retire manifest: ", $manifest ? basename($manifest) : 'MISSING', "\n";
if ($manifest) {
    $m = json_decode(file_get_contents($manifest), true);
    echo "  keys: ", implode(',', array_keys($m)), "\n";
    $rows = $m['enrollments'] ?? $m['rows'] ?? $m['dropped'] ?? [];
    foreach ($rows as $r) {
        $r = (array) $r;
        printf("  retired enroll %-5s student %-5s term %-3s\n",
            $r['enrollmentId'] ?? '?', $r['studentId'] ?? '?', $r['termId'] ?? '?');
    }
}

echo "\n-- every Dropped enrollment on live ems --\n";
foreach (DB::table('enrollments')->where('enrollmentStatus', 'dropped')->orderBy('enrollmentId')
    ->get(['enrollmentId', 'studentId', 'termId', 'yearLevel', 'created_at', 'dropReason']) as $d) {
    printf("  %-5s stu %-4s term %-3s yr %-2s created %s  reason: %s\n",
        $d->enrollmentId, $d->studentId, $d->termId, $d->yearLevel, $d->created_at, substr((string) $d->dropReason, 0, 46));
}

echo "\n-- the five the pipeline created in this run (term 2) --\n";
$new = DB::table('enrollments')->whereIn('enrollmentId', [1063, 1064, 1065, 1066, 1067])
    ->orderBy('enrollmentId')->get(['enrollmentId', 'studentId', 'termId', 'yearLevel', 'studentType', 'enrollmentStatus', 'created_at']);
foreach ($new as $e) {
    $clinic = DB::table('clinicrecords')->where('enrollmentId', $e->enrollmentId)->count();
    $idreq = DB::table('idrequests')->where('enrollmentId', $e->enrollmentId)->count();
    $boxes = DB::table('workflowsteps as ws')
        ->join('enrollmentworkflow as w', 'w.workflowId', '=', 'ws.workflowId')
        ->where('w.enrollmentId', $e->enrollmentId)->where('ws.stepStatus', 'completed')
        ->pluck('ws.officeId')->implode(',');
    printf("  %-5s stu %-4s term %-3s yr %-2s %-10s %-9s clinic=%d idreq=%d  completed boxes: %s\n",
        $e->enrollmentId, $e->studentId, $e->termId, $e->yearLevel, $e->studentType, $e->created_at,
        $clinic, $idreq, $boxes);
}

echo "\n-- does the same student still hold a record on that prior term? (the guard that would stop a re-make) --\n";
foreach ($new as $e) {
    $holders = DB::table('enrollments')
        ->where('studentId', $e->studentId)->where('termId', $e->termId)
        ->where('enrollmentId', '!=', $e->enrollmentId)
        ->pluck('enrollmentStatus')->implode(',') ?: 'none';
    printf("  student %-4s term %-3s : other records on that term = %s\n", $e->studentId, $e->termId, $holders);
}
