<?php
/**
 * Does a notice reach the bell now that this install runs the queue synchronously? Measured on live
 * `ems` inside a transaction that is rolled back, so the answer costs no rows.
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Events\WorkflowStepSigned;
use App\Models\Enrollments;
use App\Models\Staffusers;
use Illuminate\Support\Facades\DB;

printf('queue connection: %s%s', config('queue.default'), PHP_EOL);

$enrollment = Enrollments::whereHas('enrollmentworkflow')->with('enrollmentworkflow')->first();
$workflow = $enrollment?->enrollmentworkflow;
$step = $workflow?->workflowsteps()->first();
$signer = Staffusers::where('username', 'office1_head')->first();

if ($workflow === null || $step === null || $signer === null) {
    fwrite(STDERR, "no workflow / step / signer to work with\n");
    exit(1);
}

DB::beginTransaction();
try {
    $before = DB::table('notifications')->count();
    $pendingBefore = DB::table('jobs')->count();

    event(new WorkflowStepSigned($workflow, $step, $signer));

    $after = DB::table('notifications')->count();
    $pendingAfter = DB::table('jobs')->count();

    printf("notifications: %d → %d   (expected +1 when the listener runs inline)%s", $before, $after, PHP_EOL);
    printf("pending jobs : %d → %d   (expected no change: nothing is queued)%s", $pendingBefore, $pendingAfter, PHP_EOL);

    $row = DB::table('notifications')->latest('id')->first();
    printf("last row     : type %s, addressed to %s#%s, taken %s%s",
        $row->type, $row->notifiable_type, $row->notifiable_id, $row->created_at, PHP_EOL);

    $ok = $after === $before + 1 && $pendingAfter === $pendingBefore;
    echo $ok ? "\nDELIVERED SYNCHRONOUSLY\n" : "\nNOT delivered inline\n";
} finally {
    DB::rollBack();
    $rolled = DB::table('notifications')->count();
    printf("after rollback: notifications %d, jobs %d%s", $rolled, DB::table('jobs')->count(), PHP_EOL);
}
