<?php
// Demo dataset seeder: walks two students through the enrollment pipeline
// using the real HTTP endpoints + authenticated staff (same flow the E2E test
// drives), leaving persistent rows in the `ems` database so every parametrized
// page has data to render during the presentation.
//
// Usage: php seed_demo_data.php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Admissions;
use App\Models\Clearanceperiods;
use App\Models\Enrollments;
use App\Models\Staffusers;
use App\Models\Studentclearances;
use App\Models\Students;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

function req(Staffusers $user, string $method, string $uri, array $data = []) {
    $session = app('session.store');
    $session->start();

    Auth::login($user);
    // login() regenerates the session ID AND the CSRF token — so the token
    // must be read AFTER login, or ValidateCsrfToken rejects it with 419.
    $token = $session->token();

    $request = Request::create($uri, $method, $data + ['_token' => $token], [], [], [
        'HTTP_ACCEPT' => 'text/html',
    ]);
    $request->setLaravelSession($session);
    $response = app()->handle($request);
    Auth::logout(); // keep the session clean for the next actor
    return $response;
}

function ok(string $label, $response) {
    $code = $response->getStatusCode();
    if (! in_array($code, [200, 302])) {
        echo "FAIL $label -- HTTP $code: " . substr($response->getContent(), 0, 300) . "\n";
        exit(1);
    }
    echo "ok   $label ($code)\n";
}

$staff = fn (int $officeId) => Staffusers::where('officeId', $officeId)->where('status', 'active')->firstOrFail();

// ---------- 1. Admission (first-year, BSCrim requires entrance exam) ----------
$payload = [
    'schoolIdNumber' => 'DEMO-2026-001',
    'lastName' => 'Dela Cruz', 'firstName' => 'Juan', 'middleName' => 'P',
    'suffix' => 'N/A', 'gender' => 'male', 'birthdate' => '2004-01-01',
    'birthplace' => 'Test City', 'citizenship' => 'Filipino', 'religionId' => 1,
    'civilStatus' => 'single', 'contactNumber' => '09171234567', 'telephoneNumber' => null,
    'email' => 'demo.juan@example.com', 'username' => 'demo_juan', 'password' => 'password123', 'password_confirmation' => 'password123',
    'courseId' => 3, 'termId' => 18, 'applicantType' => 'firstYear',
    'addresses' => [[
        'addressType' => 'home', 'houseBuildingNo' => '123', 'street' => 'Rizal St', 'sitioPurok' => 'Purok 1',
        'barangay' => 'Barangay 1', 'cityMunicipality' => 'Davao City', 'district' => 'District 1',
        'province' => 'Davao del Sur', 'region' => 'Region XI', 'zipCode' => '8000', 'country' => 'Philippines',
    ]],
    'guardians' => [[
        'relationship' => 'mother', 'fullName' => 'Maria Dela Cruz', 'contactNumber' => '09171234568',
        'email' => 'demo.maria@example.com', 'isEmergencyContact' => true, 'isAuthorizedToActOnBehalf' => true,
    ]],
    'educationalBackgrounds' => [[
        'institutionName' => 'Test National High School', 'institutionType' => 'seniorHigh',
        'cityMunicipality' => 'Davao City', 'province' => 'Davao del Sur', 'levelCompleted' => 'seniorHigh',
        'strandTrack' => 'STEM', 'yearCompleted' => '2024-03-31', 'honorsCertifications' => null,
    ]],
];
ok('admission.store', req($staff(6), 'POST', route('admission.store'), $payload));

$studentId = DB::table('students')->where('username', 'demo_juan')->value('studentId');
$admission = Admissions::where('studentId', $studentId)->firstOrFail();
echo "     student #$studentId, admission #{$admission->admissionId}\n";

// Verify requirements
DB::table('studentrequirementsubmissions')->where('admissionId', $admission->admissionId)
    ->update(['submissionStatus' => 'verified']);
echo "ok   requirements verified\n";

// ---------- 2. Entrance exams (2-stage for BSCrim) ----------
$examData = ['studentId' => $studentId, 'courseId' => 3, 'termId' => 18, 'examResult' => 'pass', 'examDate' => now()->toDateString()];
ok('exam.general.record', req($staff(7), 'POST', route('exam.general.record'), $examData));
ok('exam.course-specific.record', req($staff(4), 'POST', route('exam.course-specific.record'), $examData));
$admission->refresh();
echo "     admission status: {$admission->admissionStatus->value}\n";

// ---------- 3. Evaluation ----------
$evaluator = $staff(4);
$enrollment = Enrollments::create([
    'studentId' => $studentId,
    'courseId' => 3,
    'termId' => 18,
    'admissionId' => $admission->admissionId,
    'yearLevel' => 1,
    'studentType' => 'firstYear',
    'enrollmentType' => 'new',
    'academicStanding' => 'regular',
    'evaluatedBy' => $evaluator->userId,
    'enrollmentStatus' => App\Enums\EnrollmentStatus::Pending,
]);
echo "     enrollment #{$enrollment->enrollmentId} created\n";

$profile = [
    'lastName' => 'Dela Cruz', 'firstName' => 'Juan', 'middleName' => 'P', 'suffix' => 'N/A',
    'gender' => 'male', 'birthdate' => '2004-01-01', 'birthplace' => 'Test City',
    'citizenship' => 'Filipino', 'religionId' => 1, 'civilStatus' => 'single',
    'contactNumber' => '09171234567', 'telephoneNumber' => null, 'email' => 'demo.juan@example.com',
    'addresses' => [[
        'addressType' => 'home', 'houseBuildingNo' => '123', 'street' => 'Rizal St', 'sitioPurok' => 'Purok 1',
        'barangay' => 'Barangay 1', 'cityMunicipality' => 'Davao City', 'district' => 'District 1',
        'province' => 'Davao del Sur', 'region' => 'Region XI', 'zipCode' => '8000', 'country' => 'Philippines',
    ]],
    'guardians' => [[
        'relationship' => 'mother', 'fullName' => 'Maria Dela Cruz', 'contactNumber' => '09171234568',
        'email' => 'demo.maria@example.com', 'isEmergencyContact' => true, 'isAuthorizedToActOnBehalf' => true,
    ]],
    'semestersCompleted' => 0, 'yearsInInstitution' => 0, 'academicStanding' => 'regular',
    'formIssuedDate' => now()->toDateString(),
];
ok('evaluation.profile.capture', req($evaluator, 'PUT', route('evaluation.profile.capture', $enrollment), $profile));

$subjectIds = DB::table('subjects')->limit(3)->pluck('subjectId')->all();
ok('evaluation.subjects.propose', req($evaluator, 'POST', route('evaluation.subjects.propose', $enrollment),
    ['subjects' => array_map(fn ($id) => ['subjectId' => $id], $subjectIds)]));
ok('evaluation.sign', req($evaluator, 'POST', route('evaluation.sign', $enrollment)));
$enrollment->refresh();
echo "     status: {$enrollment->enrollmentStatus->value}\n";

// ---------- 4. Assessment ----------
$assessmentStaff = $staff(3);
ok('assessment.compute', req($assessmentStaff, 'POST', route('assessment.compute', $enrollment)));
$assessment = $enrollment->fresh()->studentassessments;
echo "     assessment #{$assessment->assessmentId}, total: {$assessment->totalAmount}\n";
ok('assessment.finalize', req($assessmentStaff, 'POST', route('assessment.finalize', $assessment)));
$enrollment->refresh();
echo "     status: {$enrollment->enrollmentStatus->value}\n";

// ---------- 5. Accounting ----------
$assessment = $enrollment->fresh()->studentassessments;
ok('accounting.payment.record', req($staff(2), 'POST', route('accounting.payment.record', $assessment), [
    'orNumber' => 'DEMO-OR-0001', 'amount' => $assessment->remainingBalance,
    'paymentMode' => 'cash', 'paymentDate' => now()->toDateString(),
]));
$enrollment->refresh();
echo "     status: {$enrollment->enrollmentStatus->value}\n";

// ---------- 6. Registrar ----------
ok('registrar.approve', req($staff(1), 'POST', route('registrar.approve', $enrollment)));
$enrollment->refresh();
echo "     status: {$enrollment->enrollmentStatus->value}\n";

// ---------- 7. Blocking ----------
$block = DB::table('blocks')->where('courseId', 3)->where('termId', 18)->first();
if (! $block) {
    $blockId = DB::table('blocks')->insertGetId([
        'courseId' => 3, 'termId' => 18, 'yearLevel' => 1, 'blockName' => 'BSCrim 1-A', 'maxStudents' => 40,
    ]);
    DB::table('schedules')->insert([
        'blockId' => $blockId, 'subjectId' => DB::table('subjects')->value('subjectId'),
        'instructorId' => Staffusers::value('userId'), 'roomId' => DB::table('rooms')->value('roomId'),
    ]);
    $block = DB::table('blocks')->where('blockId', $blockId)->first();
}
$schedule = DB::table('schedules')->where('blockId', $block->blockId)->first();
ok('blocking.assign', req($staff(5), 'POST', route('blocking.assign', $block->blockId), [
    'enrollmentIds' => [$enrollment->enrollmentId], 'scheduleId' => $schedule->scheduleId,
]));

// ---------- 8. Clinic ----------
ok('clinic.record', req($staff(11), 'POST', route('clinic.record', $enrollment), [
    'heightCm' => 170, 'weightKg' => 62, 'bloodPressure' => '120/80',
    'philhealthNumber' => 'PH-DEMO-0001', 'philhealthRegistered' => true,
    'assessmentNotes' => 'Fit for enrollment', 'findings' => 'Normal',
    'assessmentDate' => now()->toDateString(),
]));

// ---------- 9. ID ----------
ok('id.create', req($staff(22), 'POST', route('id.create', $enrollment), [
    'requestReason' => 'newStudent', 'emergencyContactName' => 'Maria Dela Cruz',
    'emergencyContactNumber' => '09171234568', 'bloodType' => 'O+',
    'cardPhotoPath' => null, 'producedByVendor' => null,
]));
$idRequest = $enrollment->fresh()->idrequests->first();
ok('id.produce', req($staff(22), 'POST', route('id.produce', $idRequest), [
    'qrCode' => 'SEAIT-DEMO-' . $studentId, 'securityPhotoPath' => null,
]));
$studentIdCard = $idRequest->fresh()->studentids;
ok('id.validate', req($staff(22), 'POST', route('id.validate', $studentIdCard)));
$cardId = $studentIdCard->studentIdId ?? $studentIdCard->studentId ?? '?';
echo "     ID card #$cardId validated\n";

// ---------- 10. Continuing student with clearance (2nd demo path) ----------
$student2 = Students::create([
    'schoolIdNumber' => 'DEMO-2026-002', 'lastName' => 'Reyes', 'firstName' => 'Maria', 'middleName' => 'S',
    'suffix' => 'N/A', 'gender' => 'female', 'birthdate' => '2003-05-15', 'birthplace' => 'Test City',
    'citizenship' => 'Filipino', 'civilStatus' => 'single', 'religionId' => 1,
    'contactNumber' => '09171234570', 'telephoneNumber' => null,
    'semestersCompleted' => 4, 'yearsInInstitution' => 2,
    'email' => 'demo.maria.reyes@example.com', 'username' => 'demo_maria_r',
    'passwordHash' => bcrypt('password123'), 'status' => 'active',
]);
echo "     continuing student #{$student2->studentId} created\n";

$period = Clearanceperiods::where('periodStatus', 'open')->first();
if ($period) {
    ok('clearance.slip.generate', req($staff(1), 'POST', route('clearance.slip.generate'), [
        'studentId' => $student2->studentId, 'clearancePeriodId' => $period->clearancePeriodId,
    ]));
    $clearance = Studentclearances::where('studentId', $student2->studentId)->first();
    foreach ($clearance->approvals as $approval) {
        ok("clearance.approve (office {$approval->requirement->officeId})",
            req($staff($approval->requirement->officeId), 'POST', route('clearance.approve', $approval), ['status' => 'approved']));
    }
    ok('clearance.receipt.record', req($staff(1), 'POST', route('clearance.receipt.record', $clearance)));
} else {
    echo "skip  clearance -- no open period\n";
}

// Retention exam for BSAIS (course 5)
ok('exam.retention.record', req($staff(7), 'POST', route('exam.retention.record'), [
    'studentId' => $student2->studentId, 'courseId' => 5, 'termId' => 18,
    'examResult' => 'pass', 'examDate' => now()->toDateString(),
]));

echo "\nDEMO DATA SEEDED\n";
echo "Student 1: demo_juan (first-year, fully enrolled through ID validation)\n";
echo "Student 2: demo_maria_r (continuing, clearance approved, retention passed)\n";
