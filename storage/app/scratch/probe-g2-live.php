<?php

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Enrollments;
use Illuminate\Support\Facades\DB;

config([
    'database.default' => 'mysql',
    'database.connections.mysql.database' => 'ems',
    'database.connections.mysql.host' => '127.0.0.1',
    'database.connections.mysql.port' => '3306',
    'database.connections.mysql.username' => 'root',
    'database.connections.mysql.password' => '',
]);
DB::purge('mysql');

echo "=== 1. How the G-2 rule reads the live dataset (nothing is written)\n";

$rows = DB::table('enrollments')
    ->join('students', 'students.studentId', '=', 'enrollments.studentId')
    ->join('academicterms', 'academicterms.termId', '=', 'enrollments.termId')
    ->orderBy('enrollments.studentId')
    ->get([
        'enrollments.enrollmentId',
        'enrollments.studentId',
        'enrollments.yearLevel',
        'enrollments.enrollmentStatus',
        'enrollments.termId',
        'students.schoolIdNumber',
        'academicterms.semester',
    ]);

$agree = 0;
$disagree = [];
foreach ($rows as $r) {
    $derived = Enrollments::derivedYearLevel((int) $r->studentId, (int) $r->termId);
    if ($derived === (int) $r->yearLevel) {
        $agree++;
    } else {
        $disagree[] = sprintf(
            '  enrollment %d (%s, term %d %s, stored %d, status %s) reads year %d',
            $r->enrollmentId,
            $r->schoolIdNumber,
            $r->termId,
            $r->semester,
            $r->yearLevel,
            $r->enrollmentStatus,
            $derived
        );
    }
}

echo "  ".count($rows)." enrollments: {$agree} already agree with the rule, ".count($disagree)." would move\n";
foreach (array_slice($disagree, 0, 12) as $line) {
    echo $line."\n";
}

echo "\n=== 2. The batch read the issue form uses agrees with the single read\n";

$ids = DB::table('students')->orderBy('studentId')->limit(400)->pluck('studentId')->map(fn ($i) => (int) $i)->all();

DB::flushQueryLog();
DB::enableQueryLog();
$batch = Enrollments::derivedYearLevels($ids);
$batchQueries = count(DB::getQueryLog());
DB::disableQueryLog();

$mismatch = 0;
foreach (array_slice($ids, 0, 60) as $id) {
    if ((Enrollments::derivedYearLevel($id)) !== ($batch[$id] ?? 1)) {
        $mismatch++;
    }
}
printf("  %d students read in %d query(ies); first 60 single-reads disagree %d times\n", count($ids), $batchQueries, $mismatch);

$distribution = array_count_values($batch);
ksort($distribution);
echo '  derived level distribution over those '.count($ids).' students: ';
foreach ($distribution as $level => $n) {
    echo "year {$level}={$n} ";
}
echo "\n";

echo "\n=== 3. Issue a returning student at the derived level, then roll back\n";

DB::beginTransaction();
try {
    // A live student whose record already carries a completed year.
    $studentId = null;
    foreach (DB::table('enrollments')->where('enrollmentStatus', 'enrolled')->pluck('studentId') as $candidate) {
        if (Enrollments::derivedYearLevel((int) $candidate) >= 2) {
            $studentId = (int) $candidate;
            break;
        }
    }

    if ($studentId === null) {
        echo "  no live student has a completed year — nothing to prove\n";
    } else {
        $courseId = (int) DB::table('enrollments')->where('studentId', $studentId)->value('courseId');
        $nextTerm = (int) DB::table('academicterms')->where('semester', '1st')->orderByDesc('termId')->value('termId');
        $derived = Enrollments::derivedYearLevel($studentId, $nextTerm);

        $issuer = DB::table('staffusers')->where('officeId', 4)->value('userId');

        $created = app(App\Services\EnrollmentIssuer::class)->issue([
            'studentId' => $studentId,
            'courseId' => $courseId,
            'termId' => $nextTerm,
            'yearLevel' => $derived,
            'studentType' => 'continuing',
        ], App\Models\Staffusers::find((int) $issuer));

        $created = DB::table('enrollments')->where('studentId', $studentId)->where('termId', $nextTerm)->latest('enrollmentId')->first();
        printf(
            "  student %d issued into term %d at year %d (curriculum pinned %s, status %s)\n",
            $studentId,
            $nextTerm,
            $created->yearLevel,
            $created->curriculumId ?? 'NULL',
            $created->enrollmentStatus
        );
        echo '  boxes built: '.DB::table('workflowsteps')->where('enrollmentId', $created->enrollmentId)->count()."\n";
    }
} finally {
    DB::rollBack();
    echo "  rolled back; enrollments now ".DB::table('enrollments')->count()." rows\n";
}
