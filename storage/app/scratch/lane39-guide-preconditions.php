<?php

/**
 * Lane 39 — can Documentation/Demo-Day-Guide.md §3 actually be walked from the dataset the seeder builds?
 *
 * Lane 38 made `tools/seed_full_demo_pipeline.php` self-sufficient, and its closing banner has always read
 * "DEMO-DAY DATASET FULLY SEEDED! Every single desk and workflow queue is populated." The first half of that
 * sentence is now load-bearing in a way it was not before: a fresh install can reach the banner without ever
 * touching `Documentation/ems-demo-backup.sql`. This file asks the one question the banner implies the answer
 * to — walk the guide, step by step, and count the steps that have a record to act on.
 *
 * Each check names the guide line it comes from. Nothing here opens a browser: a precondition with no row
 * behind it fails at the database, and failing there is cheaper and repeatable.
 *
 * Usage: php storage/app/scratch/lane39-guide-preconditions.php            (live ems — the restored dataset)
 *        DB_DATABASE=ems_lane38_spent php storage/app/scratch/lane39-guide-preconditions.php
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\ClearanceOverallStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\IdRequestStatus;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$term = 11;
$database = DB::connection()->getDatabaseName();

echo "Dataset: {$database}\n";
echo '     (the guide bookmarks Daily Collections at 2026-09-13 and walks FDD 3.0 → 15.0 in order)'."\n\n";

$student = fn (string $schoolId) => DB::table('students')->where('schoolIdNumber', $schoolId)->first();

$juan = $student('DEMO-2026-001');
$maria = $student('DEMO-2026-002');
$pedro = $student('DEMO-2026-003');

$juanEnrollment = $juan === null ? null : DB::table('enrollments')
    ->where('studentId', $juan->studentId)->where('termId', $term)->orderByDesc('enrollmentId')->first();

$checks = [];

$add = function (string $step, string $what, bool $ok, string $detail = '') use (&$checks): void {
    $checks[] = ['step' => $step, 'what' => $what, 'ok' => $ok, 'detail' => $detail];
};

// Step 4 — Admissions: Pedro Santos pending, with one requirement already carrying an uploaded file.
//
// Every check runs on every dataset, so the two totals are comparable. The first version skipped the checks
// that depended on an absent student, which counted a dataset holding 6 of 19 against one holding 17 of 19
// — the denominators differed, so the comparison said less than it looked like it said.
$add(
    '4  Admissions',
    'Pedro Santos (DEMO-2026-003) exists',
    $pedro !== null,
    $pedro === null ? 'the seeder never names this student' : ''
);

$pedroAdmission = $pedro === null ? null : DB::table('admissions')->where('studentId', $pedro->studentId)->first();

$add(
    '4  Admissions',
    'his application is pending',
    $pedroAdmission !== null && $pedroAdmission->admissionStatus === 'pending',
    $pedroAdmission === null ? 'no admission row' : 'status '.$pedroAdmission->admissionStatus
);

// An uploaded requirement paper is a `documents` row hanging off the submission it belongs to
// (documents.submissionId), not a column on the submission itself — measured, because the first version of
// this file guessed `filePath` and the database said no such column exists.
$attached = $pedroAdmission === null ? 0 : DB::table('documents as d')
    ->join('studentrequirementsubmissions as s', 's.submissionId', '=', 'd.submissionId')
    ->where('s.admissionId', $pedroAdmission->admissionId)
    ->whereNotNull('d.fileUrl')->where('d.fileUrl', '!=', '')
    ->count();
$add('4  Admissions', 'a requirement has Form 138 attached', $attached > 0, "{$attached} document row(s) carry a file");

// Step 5 — Examinations. The guide calls Pedro "admitted, not yet enrolled = exam candidate" one step after
// calling his status pending, which looked like a contradiction until the roster was read: ExamController::
// entranceApplicants() selects admissions in Pending OR Approved with no enrollment on that term, so a
// pending applicant IS a candidate. The predicate here matches the code, not the guide's phrasing.
$add(
    '5  Exams',
    'Pedro is on the School Entrance roster (ExamController::entranceApplicants)',
    $pedroAdmission !== null
        && in_array($pedroAdmission->admissionStatus, ['pending', 'approved'], true)
        && DB::table('enrollments')->where('studentId', (int) $pedro->studentId)
            ->where('termId', $term)->count() === 0,
    $pedroAdmission === null ? 'no admission row' : 'his admission reads '.$pedroAdmission->admissionStatus
);

$add(
    '5  Exams',
    'Juan appears on the Pass/Fail tab (an exam result of his own)',
    $juan !== null && DB::table('examresults')->where('studentId', $juan->studentId)->count() > 0,
    $juan === null ? '' : (string) ($juan !== null ? DB::table('examresults')->where('studentId', $juan->studentId)->count() : 0).' result row(s)'
);

// Step 6 — Evaluation: Juan's profile captured, subjects proposed, box signed; Maria's retention result on file.
if ($juanEnrollment !== null) {
    $add('6  Evaluation', 'Juan holds an enrollment on the demo term', true, 'enrollment '.$juanEnrollment->enrollmentId);
    $add('6  Evaluation', 'his load was proposed from the curriculum', DB::table('enrolledsubjects')
        ->where('enrollmentId', $juanEnrollment->enrollmentId)->count() > 0);
    $add('6  Evaluation', "his record carries the department's signature", DB::table('workflowsteps as ws')
        ->join('enrollmentworkflow as w', 'w.workflowId', '=', 'ws.workflowId')
        ->where('w.enrollmentId', $juanEnrollment->enrollmentId)->where('ws.officeId', 4)
        ->where('ws.stepStatus', 'completed')->whereNotNull('ws.signedBy')->count() > 0);
} else {
    $add('6  Evaluation', 'Juan holds an enrollment on the demo term', false);
}

$add(
    '6  Evaluation',
    "Maria Reyes's retention result is on record",
    $maria !== null && DB::table('examresults')->where('studentId', $maria->studentId)
        ->where('examStage', 'retention')->count() > 0,
    $maria === null ? 'the seeder never names this student' : ''
);

// Step 7 — Assessment: an itemised, finalised sheet.
$add(
    '7  Assessment',
    "Juan's fee sheet carries itemised charges",
    $juanEnrollment !== null && DB::table('charges as c')
        ->join('studentassessments as a', 'a.assessmentId', '=', 'c.assessmentId')
        ->where('a.enrollmentId', $juanEnrollment->enrollmentId)->count() > 0
);

// Step 8 — Payments: OR DEMO-OR-0001, then the collections day the guide bookmarks.
$add(
    '8  Payments',
    'Juan owns receipt DEMO-OR-0001',
    $juanEnrollment !== null && DB::table('payments')->where('enrollmentId', $juanEnrollment->enrollmentId)
        ->where('orNumber', 'DEMO-OR-0001')->count() > 0
);
$add(
    '8  Payments',
    'a receipt is dated 2026-09-13 (the URL the guide tells you to bookmark)',
    DB::table('payments')->whereDate('paymentDate', '2026-09-13')->count() > 0,
    (string) DB::table('payments')->whereDate('paymentDate', '2026-09-13')->count().' row(s) on that day'
);

// Step 9 — Clearance: Maria's slip with all nine lines approved, and the replacement fee recorded.
$mariaSlip = $maria === null ? null : DB::table('studentclearances')->where('studentId', $maria->studentId)
    ->where('overallStatus', ClearanceOverallStatus::Approved->value)->first();

$add(
    '9  Clearance',
    'Maria Reyes holds an approved slip',
    $mariaSlip !== null,
    $maria === null ? 'the seeder never names this student' : ''
);

if ($mariaSlip !== null) {
    $lines = DB::table('clearanceapprovals')->where('studentClearanceId', $mariaSlip->studentClearanceId)
        ->where('status', 'approved')->count();
    $add('9  Clearance', 'all nine requirement lines are approved', $lines >= 9, "{$lines} approved");
} else {
    $add('9  Clearance', 'all nine requirement lines are approved', false, 'no slip to approve');
}

// Step 10 — Blocking: block BSCrim 1-A exists with Juan inside it.
$add(
    '10 Blocking',
    'Juan is assigned to a block section',
    $juanEnrollment !== null && DB::table('enrolledsubjects')->where('enrollmentId', $juanEnrollment->enrollmentId)
        ->whereNotNull('blockId')->count() > 0
);

// Steps 11-13 — Registrar approval, the clinic assessment, the validated ID with a face photo.
$add(
    '11 Registrar',
    "Juan's record shows final approval",
    $juanEnrollment !== null && $juanEnrollment->enrollmentStatus === EnrollmentStatus::Enrolled->value
);
$add(
    '12 Clinic',
    "Juan's health assessment is on file",
    $juanEnrollment !== null && DB::table('clinicrecords')->where('enrollmentId', $juanEnrollment->enrollmentId)->count() > 0
);
$add(
    '13 IDs',
    "Juan's ID request is validated with a face photo",
    $juanEnrollment !== null && DB::table('idrequests')->where('enrollmentId', $juanEnrollment->enrollmentId)
        ->where('status', IdRequestStatus::Validated->value)->whereNotNull('cardPhotoPath')->count() > 0
);

// Step 14 — Student 360 needs the admission history the guide names as part of "his whole life".
$add(
    '14 Student 360',
    "Juan's profile carries an admission history to show",
    $juan !== null && DB::table('admissions')->where('studentId', $juan->studentId)->count() > 0
);

$present = count(array_filter($checks, fn (array $c): bool => $c['ok']));

foreach ($checks as $check) {
    printf(
        "  %s  %-14s %s%s\n",
        $check['ok'] ? 'have ' : 'MISS',
        $check['step'],
        $check['what'],
        $check['detail'] === '' ? '' : '  ['.$check['detail'].']'
    );
}

printf(
    "\n  %d of %d guide preconditions present in %s\n",
    $present,
    count($checks),
    $database
);
