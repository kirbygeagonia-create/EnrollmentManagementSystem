<?php

/**
 * Read-only: run IDController::index()'s two predicates and print what each set holds today.
 *
 * Pass 244 corrected the Issued-tab figure in §20 from "seven" to the number the server answers, and left the
 * other half of the same sentence — "against the two the queue holds" — standing because it had been written on
 * 2026-10-17. A sentence that states two counts must be measured on both, or the pass has repaired one number and
 * re-certified the other without looking.
 *
 * The predicates are copied from app/Http/Controllers/ID/IDController.php index() (queue = the enrollments whose
 * EARLIEST pending ID-office step is this office's; issued = any COMPLETED ID-office step) rather than recalled.
 *
 *   php storage/app/scratch/lane72-probe-id-tabs.php
 */
// The imports sit ABOVE the bootstrap on purpose: Pint shortens `Illuminate\Contracts\Console\Kernel::class` at the
// call to `Kernel::class` and files its own `use` block below, which parses, formats clean, and then fatals with
// "Class Kernel does not exist" at runtime (lane 65 paid for this, and the trap bit this probe on its first
// formatting pass — run the script, not just `pint --test`, after any format over a scratch file that boots the app).
use App\Enums\EnrollmentStatus;
use App\Enums\OfficeId;
use App\Enums\WorkflowStepStatus;
use App\Models\Enrollments;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../../vendor/autoload.php';
$app = require_once __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$queue = (Enrollments::query()
    ->where('enrollmentStatus', EnrollmentStatus::Enrolled)
    ->whereHas('enrollmentworkflow.workflowsteps', fn ($q) => $q
        ->where('stepStatus', WorkflowStepStatus::Pending->value)
        ->where('officeId', OfficeId::IdOffice->value)
        ->whereRaw('stepOrder = (SELECT MIN(ws.stepOrder) FROM workflowsteps ws WHERE ws.workflowId = workflowsteps.workflowId AND ws.stepStatus = ?)', ['pending'])
    ))->count();

$issued = (Enrollments::query()
    ->where('enrollmentStatus', EnrollmentStatus::Enrolled)
    ->whereHas('enrollmentworkflow.workflowsteps', fn ($q) => $q
        ->where('stepStatus', WorkflowStepStatus::Completed->value)
        ->where('officeId', OfficeId::IdOffice->value)
    ))->count();

printf("ID desk, live %s: queue tab = %d enrollment(s), issued tab = %d enrollment(s)\n",
    config('database.connections.mysql.database'), $queue, $issued);
printf("§20's sentence asserts nine on the Issued tab against two in the queue.\n");
printf("  issued: %s   queue: %s\n", $issued === 9 ? 'AGREES' : "DISAGREES (answers $issued)",
    $queue === 2 ? 'AGREES' : "DISAGREES (answers $queue)");
