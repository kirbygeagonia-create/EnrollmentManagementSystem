<?php

// Demo dataset seeder: walks two students through the enrollment pipeline
// using the real HTTP endpoints + authenticated staff (same flow the E2E test
// drives), leaving persistent rows in the `ems` database so every parametrized
// page has data to render during the presentation.
//
// STATUS (measured 2026-10-15, on a database built the way CI builds one — migrate, db:seed,
// DevReferenceDataSeeder): this file boots and reports honestly, but it does NOT complete. The capture step is
// refused by the application's own rule ("The addresses field must have at least 2 items", where this file
// sends one), and Department Evaluation then refuses to sign an enrollment that is still `pending`. Three
// defects were fixed on the way to that finding — the bootstrap that made the file unrunnable, two desk
// assignments that the sign-rights split and BR9 had reversed, and a reporter that printed "ok" for a
// validation redirect — and none of them rebuilt the dataset. So this is not the way to make a demo database,
// and `tools/seed_full_demo_pipeline.php` is right to refuse when the base rows are missing: the
// presentation dataset comes from running that pipeline. No snapshot ships with the code —
// Documentation/ems-demo-backup.sql was one until 2026-10-26, when it was taken out of tracking as
// machine state, and it was already a September snapshot at that.
//
// Usage: php tools/seed_demo_data.php   (from the project root)
//
// The bootstrap paths are absolute and the kernel is named in full, because neither was before: `use`
// aliases resolve positionally in PHP, so `Kernel::class` on this line did not see the `use` at the bottom
// of the import block and died with `Class "Kernel" does not exist` — measured in a two-line probe, not
// guessed. And
// `require 'vendor/autoload.php'` only worked from one working directory while the header told the reader to
// run it from another.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\EnrollmentStatus;
use App\Models\Admissions;
use App\Models\Clearanceperiods;
use App\Models\Enrollments;
use App\Models\Staffusers;
use App\Models\Studentclearances;
use App\Models\Students;
use App\Support\SchoolTime;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

function req(Staffusers $user, string $method, string $uri, array $data = [], array $files = [])
{
    $session = app('session.store');
    $session->start();

    Auth::login($user);
    // login() regenerates the session ID AND the CSRF token — so the token
    // must be read AFTER login, or ValidateCsrfToken rejects it with 419.
    $token = $session->token();

    $request = Request::create($uri, $method, $data + ['_token' => $token], [], $files, [
        'HTTP_ACCEPT' => 'text/html',
    ]);
    $request->setLaravelSession($session);
    $response = app()->handle($request);
    Auth::logout(); // keep the session clean for the next actor

    return $response;
}

/**
 * Write a portrait placeholder to a temp file and hand back an upload the ID
 * desk will accept. The desk refuses to validate a request with no face photo
 * on file (IDPolicy::validate), so the demo flow has to capture one first.
 */
function demoFaceUpload(string $name = 'face.png'): UploadedFile
{
    $path = sys_get_temp_dir().'/ems-face-'.uniqid().'.png';
    $im = imagecreatetruecolor(300, 360);
    imagefill($im, 0, 0, imagecolorallocate($im, 226, 232, 240));
    $ink = imagecolorallocate($im, 100, 116, 139);
    imagefilledellipse($im, 150, 132, 110, 132, $ink);
    imagefilledellipse($im, 150, 336, 232, 192, $ink);
    imagepng($im, $path);
    imagedestroy($im);

    register_shutdown_function(fn () => @unlink($path));

    return new UploadedFile($path, $name, 'image/png', null, true);
}

function ok(string $label, $response)
{
    $code = $response->getStatusCode();
    if (! in_array($code, [200, 302], true)) {
        echo "FAIL $label -- HTTP $code: ".substr($response->getContent(), 0, 300)."\n";
        exit(1);
    }

    // A redirect off a form post is also how Laravel says "your input did not validate" — the status code is
    // 302 either way, so a step that was refused and a step that worked print the same line. Reading the error
    // bag the request flashed is the only difference, and calling 302 `ok` without it is how this tool reported
    // nine green lines over a database whose enrollment had never left `pending`.
    if ($code === 302) {
        $session = app('session.store');
        $bag = $session->get('errors');
        $messages = [];

        // Whatever the session hands back depends on whether it has been through serialisation, so nothing is
        // assumed about its shape: providers are unwrapped and the rest is walked for strings. A flattener
        // that casts reached for `strval()` on an array of messages and died with "Array to string
        // conversion" — the report must not be able to lie by crashing.
        $strings = function ($value) use (&$strings): array {
            if (is_string($value) && $value !== '') {
                return [$value];
            }
            if (! is_array($value) && ! $value instanceof Traversable) {
                return [];
            }
            $out = [];
            foreach ($value as $inner) {
                foreach ($strings($inner) as $line) {
                    $out[] = $line;
                }
            }

            return $out;
        };

        $raw = $bag;
        if ($raw instanceof Illuminate\Support\ViewErrorBag) {
            $raw = $raw->getBag('default');
        }
        if ($raw instanceof Illuminate\Contracts\Support\MessageProvider) {
            $raw = $raw->getMessages();
        }
        $messages = $strings($raw);

        $flashed = array_merge($strings($session->get('error')), $strings($session->get('warning')));
        $session->forget(['errors', 'error', 'warning']);

        if ($messages !== [] || $flashed !== []) {
            echo 'FAIL '.$label.' -- refused on a 302: '.implode(' | ', array_merge($messages, $flashed))."\n";
            exit(1);
        }
    }

    echo "ok   $label ($code)\n";
}

$staff = fn (int $officeId) => Staffusers::where('officeId', $officeId)->where('status', 'active')->firstOrFail();

// ---------- 1. Admission (first-year, BSCrim requires entrance exam) ----------
$payload = [
    'schoolIdNumber' => 'DEMO-2026-001',
    'lastName' => 'Dela Cruz', 'firstName' => 'Juan', 'middleName' => 'P',
    'suffix' => '', 'gender' => 'male', 'birthdate' => '2004-01-01',
    'birthplace' => 'Test City', 'citizenship' => 'Filipino', 'religionId' => 1,
    'civilStatus' => 'single', 'contactNumber' => '09171234567', 'telephoneNumber' => null,
    'email' => 'demo.juan@example.com', 'username' => 'demo_juan', 'password' => 'password123', 'password_confirmation' => 'password123',
    'courseId' => 3, 'termId' => 11, 'applicantType' => 'firstYear',
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
// The two arms belong to different desks, and this file had them the wrong way round: BR9 gives Guidance
// (office 4) the general entrance exam and the department (office 7) the course-specific one, which is what
// `ExamPolicy::record` asks — `exam.record.general` is held by office4_head and never by an office-7 account,
// and `exam.record.courseSpecific` is the reverse. Both posts were 403. Nobody saw it because the file could
// not reach line 11 until the bootstrap import above was fixed.
$examData = ['studentId' => $studentId, 'courseId' => 3, 'termId' => 11, 'examResult' => 'pass', 'examDate' => SchoolTime::today()];
ok('exam.general.record', req($staff(4), 'POST', route('exam.general.record'), $examData));
ok('exam.course-specific.record', req($staff(7), 'POST', route('exam.course-specific.record'), $examData));
$admission->refresh();
echo "     admission status: {$admission->admissionStatus->value}\n";

// ---------- 3. Evaluation ----------
// Department Evaluation is office 7's box: `evaluation.sign` is held by the dean, the per-college dept.*
// accounts and office7_head — never by Guidance (office 4), which is what this line said.
$evaluator = $staff(7);
$enrollment = Enrollments::create([
    'studentId' => $studentId,
    'courseId' => 3,
    'termId' => 11,
    'admissionId' => $admission->admissionId,
    'yearLevel' => 1,
    'studentType' => 'firstYear',
    'enrollmentType' => 'new',
    'academicStanding' => 'regular',
    'evaluatedBy' => $evaluator->userId,
    'enrollmentStatus' => EnrollmentStatus::Pending,
]);
echo "     enrollment #{$enrollment->enrollmentId} created\n";

$profile = [
    'lastName' => 'Dela Cruz', 'firstName' => 'Juan', 'middleName' => 'P', 'suffix' => '',
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
    'formIssuedDate' => SchoolTime::today(),
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
    'paymentMode' => 'cash', 'paymentDate' => SchoolTime::today(),
]));
$enrollment->refresh();
echo "     status: {$enrollment->enrollmentStatus->value}\n";

// ---------- 6. Registrar ----------
ok('registrar.approve', req($staff(1), 'POST', route('registrar.approve', $enrollment)));
$enrollment->refresh();
echo "     status: {$enrollment->enrollmentStatus->value}\n";

// ---------- 7. Blocking ----------
$block = DB::table('blocks')->where('courseId', 3)->where('termId', 11)->first();
if (! $block) {
    $blockId = DB::table('blocks')->insertGetId([
        'courseId' => 3, 'termId' => 11, 'yearLevel' => 1, 'blockName' => 'BSCrim 1-A', 'maxStudents' => 40,
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
    'assessmentDate' => SchoolTime::today(),
]));

// ---------- 9. ID (validation-only flow: intake -> photo -> validate) ----------
ok('id.create', req($staff(22), 'POST', route('id.create', $enrollment), [
    'requestReason' => 'newStudent', 'emergencyContactName' => 'Maria Dela Cruz',
    'emergencyContactNumber' => '09171234568', 'bloodType' => 'O+',
    'cardPhotoPath' => null,
]));
$idRequest = $enrollment->fresh()->idrequests->first();
ok('id.photo', req($staff(22), 'POST', route('id.photo', $idRequest), [], [
    'photo' => demoFaceUpload(),
]));
ok('id.validate', req($staff(22), 'POST', route('id.validate', $idRequest)));
echo "     ID request #{$idRequest->idRequestId} validated with a face photo on file\n";

// ---------- 10. Continuing student with clearance (2nd demo path) ----------
$student2 = Students::create([
    'schoolIdNumber' => 'DEMO-2026-002', 'lastName' => 'Reyes', 'firstName' => 'Maria', 'middleName' => 'S',
    'suffix' => '', 'gender' => 'female', 'birthdate' => '2003-05-15', 'birthplace' => 'Test City',
    'citizenship' => 'Filipino', 'civilStatus' => 'single', 'religionId' => 1,
    'contactNumber' => '09171234570', 'telephoneNumber' => null,
    'semestersCompleted' => 4, 'yearsInInstitution' => 2,
    'email' => 'demo.maria.reyes@example.com', 'username' => 'demo_maria_r',
    'passwordHash' => bcrypt('password123'), 'status' => 'active',
]);
echo "     continuing student #{$student2->studentId} created\n";

$period = Clearanceperiods::accepting()->first();
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
    'studentId' => $student2->studentId, 'courseId' => 5, 'termId' => 11,
    'examResult' => 'pass', 'examDate' => SchoolTime::today(),
]));

echo "\nDEMO DATA SEEDED\n";
echo "Student 1: demo_juan (first-year, fully enrolled through ID validation)\n";
echo "Student 2: demo_maria_r (continuing, clearance approved, retention passed)\n";
