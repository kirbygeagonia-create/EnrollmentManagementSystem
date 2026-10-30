<?php

/**
 * Read-only: does the two-hop sort the Assessment desk now offers actually run on MySQL, and does it order?
 *
 * The suite proves the shape on SQLite; CI's MySQL job proves the test file. This asks the question of the school's
 * own server — `studentassessments → enrollments → students`, ordered by the surname on the second hop — because a
 * join that only ever ran on the suite's engine would reach a desk for the first time in production. It reads, so
 * live `ems` is safe here; it writes nothing.
 *
 * Usage: php storage/app/scratch/lane99-probe-two-hop-sort.php
 */

// The imports sit ABOVE the bootstrap on purpose: Pint's `fully_qualified_strict_types` shortens a bootstrap FQCN
// and files its own `use` below the call, which parses, formats clean, and fatals at run time — the trap this
// repository has now met four times.
use App\Enums\EnrollmentStatus;
use App\Models\Studentassessments;
use App\Support\ListSort;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$driver = DB::connection()->getDriverName();
$database = DB::connection()->getDatabaseName();
if ($driver !== 'mysql') {
    fwrite(STDERR, "REFUSED: the connection answers {$driver}; this probe exists to ask MySQL.\n");

    exit(1);
}
if ($database === 'ems' && ! str_contains((string) ($argv[1] ?? ''), '--allow-live')) {
    fwrite(STDERR, "REFUSED: pass --allow-live to read the live schema deliberately.\n");

    exit(1);
}

$allowed = [
    'name' => [
        'column' => 'lastName',
        'joins' => [
            ['join' => 'enrollments', 'foreign' => 'enrollmentId', 'local' => 'studentassessments.enrollmentId'],
            ['join' => 'students', 'foreign' => 'studentId', 'local' => 'srt0.studentId'],
        ],
    ],
    'schoolId' => [
        'column' => 'schoolIdNumber',
        'joins' => [
            ['join' => 'enrollments', 'foreign' => 'enrollmentId', 'local' => 'studentassessments.enrollmentId'],
            ['join' => 'students', 'foreign' => 'studentId', 'local' => 'srt0.studentId'],
        ],
    ],
    'amount' => ['column' => 'studentassessments.totalAssessedAmount'],
    'balance' => ['column' => 'studentassessments.remainingBalance'],
    'assessmentId' => ['column' => 'studentassessments.assessmentId'],
];

printf("schema %s on %s\n", $database, $driver);

// A list of pairs, not a map: `['name' => 'asc', 'name' => 'desc']` keeps one entry per key, and a probe that
// silently asks three questions while printing four headers is the accepted-but-inert defect in my own instrument.
foreach ([['name', 'asc'], ['name', 'desc'], ['schoolId', 'asc'], ['balance', 'asc'], ['amount', 'desc']] as [$asked, $direction]) {
    $query = Studentassessments::query()
        ->whereHas('enrollment', fn ($q) => $q->where('enrollmentStatus', EnrollmentStatus::Evaluated));

    $applied = ListSort::apply($query, $asked, $direction, $allowed, 'assessmentId', 'studentassessments.assessmentId');

    $rows = $query->limit(4)->get()->map(fn (Studentassessments $a): string => sprintf(
        '%s/%s ₱%s owe ₱%s',
        $a->enrollment?->student?->lastName ?? '—',
        $a->enrollment?->student?->schoolIdNumber ?? '—',
        number_format((float) $a->totalAssessedAmount, 2),
        number_format((float) $a->remainingBalance, 2)
    ));

    printf(
        "  sort=%s direction=%s -> applied %s/%s, rows=%d: %s\n",
        $asked,
        $direction,
        $applied['column'],
        $applied['direction'],
        $rows->count(),
        $rows->implode(' | ')
    );

    if ($rows->count() < 2) {
        printf("  NOTE: the queue holds %d row(s), so this reads as a shape check, not an order check.\n", $rows->count());
    }
}

// The same SQL the page would build with a search filter beside the order — the handler's real composition.
$query = Studentassessments::query()
    ->whereHas('enrollment', fn ($q) => $q->where('enrollmentStatus', EnrollmentStatus::Evaluated))
    ->whereHas('enrollment.student', fn ($sq) => $sq->where('lastName', 'like', '%a%'));
ListSort::apply($query, 'name', 'asc', $allowed, 'assessmentId', 'studentassessments.assessmentId');
printf("  search + order compose: %d row(s) answered\n", $query->limit(3)->get()->count());

echo "PROBE OK — MySQL accepted every hop the handler writes.\n";
