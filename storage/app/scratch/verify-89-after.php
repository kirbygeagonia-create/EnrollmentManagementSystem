<?php
/** The three post-write checks from amend-89-evaluatedby.php, run on their own so the corrected queries
 *  are actually executed — the amendment script now aborts at its count guard on an already-applied
 *  database, which is right but leaves those lines untested. */
$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
use App\Models\Enrollments;
use App\Models\Staffusers;
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$office = Staffusers::where('username', 'office4_head')->firstOrFail();
$left = Enrollments::where('evaluatedBy', $office->userId)->count();
$perDesk = Enrollments::whereHas('evaluatedByUser', fn ($q) => $q->where('username', 'like', 'dept.%'))->count();
$mismatched = Enrollments::with('course')
    ->whereHas('evaluatedByUser', fn ($q) => $q->where('username', 'like', 'dept.%'))
    ->get()
    ->filter(function ($e) {
        $desk = $e->evaluatedByUser;
        if ($desk === null || $e->course === null) { return true; }
        return $e->course->unitId === null || (int) $desk->unitId !== (int) $e->course->unitId;
    });
printf("still crediting office4_head: %d (must be 0)\n", $left);
printf("now crediting a dept.* desk:  %d (must be 29)\n", $perDesk);
printf("rows whose desk is the WRONG college: %d (must be 0)\n", $mismatched->count());
exit($left === 0 && $perDesk === 29 && $mismatched->count() === 0 ? 0 : 1);
