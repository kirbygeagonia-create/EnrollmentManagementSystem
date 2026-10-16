<?php

// Read-only probe: name the enrollments whose year level differs from what their own record
// supports, the one counter in the demo pipeline's desk report that is not an inventory.
//
// Usage: DB_DATABASE=ems_lane38_ci php storage/app/scratch/lane38-why-level-mismatch.php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Enrollments;
use App\Models\Students;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$students = Students::pluck('schoolIdNumber', 'studentId');

foreach (Enrollments::whereNotIn('enrollmentStatus', ['dropped'])
    ->orderBy('enrollmentId')
    ->get() as $enrollment) {
    $derived = Enrollments::derivedYearLevel((int) $enrollment->studentId, (int) $enrollment->termId);

    if ($derived === (int) $enrollment->yearLevel) {
        continue;
    }

    echo sprintf(
        "enrollment %-4d %-14s course %-2d term %-3d level %-2d derived %-2d  %s\n",
        $enrollment->enrollmentId,
        (string) $students[$enrollment->studentId],
        $enrollment->courseId,
        $enrollment->termId,
        $enrollment->yearLevel,
        $derived,
        $enrollment->enrollmentStatus->value
    );
}

echo "\n-- every record this student holds, for the ids above --\n";

foreach (Enrollments::select('studentId', 'enrollmentId', 'termId', 'yearLevel', 'enrollmentStatus', 'enrollmentType', 'enrolledDate')
    ->orderBy('studentId')->orderBy('termId')->get() as $row) {
    echo sprintf(
        "  %-14s enrollment %-4d term %-3d level %d %-10s %-8s %s\n",
        (string) $students[$row->studentId],
        $row->enrollmentId,
        $row->termId,
        $row->yearLevel,
        (string) $row->enrollmentType?->value,
        (string) $row->enrollmentStatus?->value,
        (string) $row->enrolledDate
    );
}
