<?php
/**
 * Why does the C-2 reconcile section fire on a row that already reads irregular?
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\AcademicStanding;
use App\Enums\EnrollmentStatus;
use App\Enums\StudentType;
use App\Models\Enrollments;
use Illuminate\Support\Facades\DB;

$rows = DB::table('enrollments')
    ->whereIn('studentType', [StudentType::Transferee->value, StudentType::Shifter->value])
    ->select('enrollmentId', 'studentId', 'studentType', 'academicStanding', 'enrollmentStatus')
    ->get();

echo "raw rows of the two types:\n";
foreach ($rows as $r) {
    printf("  #%s standing=%s type=%s status=%s\n", $r->enrollmentId, var_export($r->academicStanding, true), $r->studentType, $r->enrollmentStatus);
}

$filtered = Enrollments::whereNotIn('enrollmentStatus', [EnrollmentStatus::Dropped->value])
    ->whereIn('studentType', [StudentType::Transferee->value, StudentType::Shifter->value])
    ->where(fn ($q) => $q->whereNull('academicStanding')
        ->orWhere('academicStanding', '!=', AcademicStanding::Irregular->value))
    ->get();

printf("\nthe reconcile filter matches %d row(s): %s\n", $filtered->count(), $filtered->pluck('enrollmentId')->implode(', '));
printf("SQL: %s\n", Enrollments::whereNotIn('enrollmentStatus', [EnrollmentStatus::Dropped->value])
    ->whereIn('studentType', [StudentType::Transferee->value, StudentType::Shifter->value])
    ->where(fn ($q) => $q->whereNull('academicStanding')->orWhere('academicStanding', '!=', AcademicStanding::Irregular->value))
    ->toSql());
