<?php

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

config([
    'database.default' => 'mysql',
    'database.connections.mysql.database' => 'ems',
    'database.connections.mysql.host' => '127.0.0.1',
    'database.connections.mysql.username' => 'root',
    'database.connections.mysql.password' => '',
]);
DB::purge('mysql');

$rows = DB::table('enrollments as e')
    ->join('students as s', 's.studentId', '=', 'e.studentId')
    ->join('academicterms as t', 't.termId', '=', 'e.termId')
    ->join('courses as c', 'c.courseId', '=', 'e.courseId')
    ->leftJoin('studentassessments as a', 'a.enrollmentId', '=', 'e.enrollmentId')
    ->orderBy('e.enrollmentStatus')
    ->orderBy('s.schoolIdNumber')
    ->get([
        'e.enrollmentId', 's.schoolIdNumber', 's.firstName', 's.lastName', 'c.courseCode',
        'e.yearLevel', 'e.studentType', 'e.academicStanding', 'e.enrollmentStatus', 't.termId', 't.semester',
        'e.clearanceConfirmedBy', 'e.dropReason', 'a.remainingBalance', 'a.totalAssessedAmount',
        DB::raw('(select count(*) from enrolledsubjects es where es.enrollmentId = e.enrollmentId and es.status = \'confirmed\') as confirmedSubjects'),
        DB::raw('(select count(*) from clinicrecords cr where cr.enrollmentId = e.enrollmentId) as clinicRows'),
        DB::raw('(select count(*) from idrequests ir where ir.enrollmentId = e.enrollmentId) as idRows'),
        DB::raw('(select count(*) from enrolledsubjects es2 join schedules sc on sc.scheduleId = es2.scheduleId where es2.enrollmentId = e.enrollmentId and es2.blockId is not null) as seated'),
    ]);

printf("%-6s %-14s %-22s %-8s %-11s %-10s %-16s %-6s %-7s %-9s %-7s %-7s %-6s %-6s\n",
    'enr', 'schoolId', 'name', 'course', 'type', 'standing', 'status', 'term', 'lvl', 'bal', 'clr', 'subj', 'clinic', 'id');

foreach ($rows as $r) {
    printf("%-6s %-14s %-22s %-8s %-11s %-10s %-16s %-6s %-7s %-9s %-7s %-7s %-6s %-6s\n",
        $r->enrollmentId, $r->schoolIdNumber, mb_substr($r->firstName.' '.$r->lastName, 0, 22), $r->courseCode,
        $r->studentType, $r->academicStanding ?? '—', $r->enrollmentStatus, $r->termId.' '.$r->semester,
        $r->yearLevel, $r->remainingBalance ?? '—',
        $r->clearanceConfirmedBy ? 'yes' : 'no', $r->confirmedSubjects, $r->clinicRows, $r->idRows);
}

echo "\n--- clearance slips in the accepting window\n";
foreach (DB::table('studentclearances as sc')
    ->join('clearanceperiods as p', 'p.clearancePeriodId', '=', 'sc.clearancePeriodId')
    ->join('students as s2', 's2.studentId', '=', 'sc.studentId')
    ->get(['sc.studentClearanceId', 'sc.overallStatus', 'sc.receivedBy', 'p.termId', 'p.periodStatus', 's2.schoolIdNumber']) as $s) {
    printf("  slip %d  %s  %s  receipt %s  window term %d (%s)\n",
        $s->studentClearanceId, $s->schoolIdNumber, $s->overallStatus, $s->receivedBy ?: 'none', $s->termId, $s->periodStatus);
}

echo "\n--- pending admissions and their requirements\n";
foreach (DB::table('admissions as ad')->join('students as s3', 's3.studentId', '=', 'ad.studentId')
    ->get(['ad.admissionId', 'ad.admissionStatus', 'ad.applicantType', 's3.schoolIdNumber', 's3.firstName', 's3.lastName']) as $a) {
    $subs = DB::table('studentrequirementsubmissions')->where('admissionId', $a->admissionId)->get()
        ->groupBy('submissionStatus')->map->count();
    printf("  admission %d  %s %s  %s  %s  submissions: %s\n", $a->admissionId, $a->schoolIdNumber,
        $a->firstName.' '.$a->lastName, $a->applicantType, $a->admissionStatus, json_encode($subs));
}

echo "\n--- blocks with seats, and the timetable\n";
foreach (DB::table('blocks as b')->join('courses as c2', 'c2.courseId', '=', 'b.courseId')
    ->where('b.termId', 11)->get(['b.blockId', 'b.blockName', 'b.yearLevel', 'b.maxStudents', 'c2.courseCode']) as $b) {
    $seated = DB::table('enrolledsubjects as es')->where('es.blockId', $b->blockId)
        ->join('enrollments as e2', 'e2.enrollmentId', '=', 'es.enrollmentId')
        ->where('e2.enrollmentStatus', '!=', 'dropped')->distinct()->count('e2.studentId');
    $classes = DB::table('schedulemeetings as sm')->join('schedules as sc2', 'sc2.scheduleId', '=', 'sm.scheduleId')
        ->where('sc2.blockId', $b->blockId)->count();
    printf("  block %d  %s %s yr%d  seats %d/%d  meetings %d\n", $b->blockId, $b->courseCode, $b->blockName, $b->yearLevel, $seated, $b->maxStudents, $classes);
}

echo "\n--- money: accounts the desks will see\n";
foreach (DB::table('studentassessments as a2')
    ->join('enrollments as e3', 'e3.enrollmentId', '=', 'a2.enrollmentId')
    ->join('students as s4', 's4.studentId', '=', 'e3.studentId')
    ->where('e3.termId', 11)
    ->get(['a2.assessmentId', 'a2.enrollmentId', 's4.schoolIdNumber', 'a2.totalAssessedAmount', 'a2.remainingBalance', 'e3.enrollmentStatus']) as $s) {
    $held = (float) DB::table('payments')->where('enrollmentId', $s->enrollmentId)
        ->whereIn('paymentStatus', ['paid', 'partial'])->sum('amount');
    printf("  sheet %d  %s  assessed %-9s owed %-9s held %-9s status %s\n", $s->assessmentId, $s->schoolIdNumber, $s->totalAssessedAmount, $s->remainingBalance, $held, $s->enrollmentStatus);
}

echo "\n--- scholarships, grants and withdrawals\n";
foreach (DB::table('studentscholarships as g')
    ->join('scholarshiptypes as st', 'st.scholarshipTypeId', '=', 'g.scholarshipTypeId')
    ->join('students as s5', 's5.studentId', '=', 'g.studentId')
    ->get(['g.studentScholarshipId', 'g.status', 'g.statusReason', 's5.schoolIdNumber', 'st.scholarshipName', 'st.coveragePercent']) as $g) {
    printf("  grant %d  %s  %s  %s%%  status %s  reason %s\n", $g->studentScholarshipId, $g->schoolIdNumber,
        $g->scholarshipName, $g->coveragePercent, $g->status, $g->statusReason ?? '—');
}

echo "\n--- fee types (the replacement fee must exist for the replace action)\n";
foreach (DB::table('feetypes')->get(['feeTypeId', 'feeName', 'defaultAmount']) as $f) {
    printf("  %d  %-32s ₱%s\n", $f->feeTypeId, $f->feeName, $f->defaultAmount);
}

echo "\n--- print trail by document type\n";
foreach (DB::table('documentprintlog')->get()->groupBy('documentType') as $type => $logs) {
    printf("  %-18s rows %d  highest number %d  unattributable %d\n", $type, $logs->count(),
        $logs->max('documentNumber'), $logs->filter(fn ($l) => ! $l->enrollmentId && ! $l->studentId && ! $l->blockId)->count());
}

echo "\n--- shift requests (the docket is empty until a desk files one)\n";
printf("  rows: %d\n", DB::table('shiftingrequests')->count());
