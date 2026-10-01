<?php

// Comprehensive Demo-Day Pipeline Seeder: populates active, realistic students
// across every single office desk and workflow queue for live demonstration.
//
// Usage: php tools/seed_full_demo_pipeline.php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
// Spelled out rather than imported: a `use` statement only takes effect from its
// own line onward, and every import in this file sits below this bootstrap call.
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\AcademicStanding;
use App\Enums\AdmissionStatus;
use App\Enums\ApplicantType;
use App\Enums\ApplicationMode;
use App\Enums\ClearanceOverallStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\OfficeId;
use App\Enums\StudentType;
use App\Enums\WorkflowStatus;
use App\Enums\WorkflowStepStatus;
use App\Models\Admissions;
use App\Models\Clearanceperiods;
use App\Models\Clearancerequirements;
use App\Models\Enrollments;
use App\Models\Enrollmentworkflow;
use App\Models\Staffusers;
use App\Models\Studentclearances;
use App\Models\Students;
use App\Models\Workflowsteps;
use App\Services\AcademicStandingService;
use App\Services\EnrollmentStateMachine;
use App\Services\WorkflowService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

echo "=======================================================\n";
echo " SEAIT EMS — COMPREHENSIVE WORKFLOW DEMO-DAY SEEDER   \n";
echo "=======================================================\n\n";

$termId = 18; // 2025-2026 Summer
$defaultPassword = Hash::make('password123');
$adminUser = Staffusers::where('username', 'staff8')->first() ?? Staffusers::first();
$workflowService = app(WorkflowService::class);
$standingService = app(AcademicStandingService::class);

/**
 * Write a portrait placeholder onto the same private disk the ID desk stores
 * captured face photos on, and return its relative path.
 *
 * IDPolicy::validate refuses to validate a request with no photo on file, so a
 * demo row that claims to be validated without one shows the desk a record its
 * own rule could never have produced — and the request screen has nothing to
 * display where the face should be.
 */
$demoFacePhoto = function (string $slug): string {
    $disk = Storage::disk(config('filesystems.default', 'local'));
    $path = 'id-photos/'.$slug.'.png';

    if (! $disk->exists($path)) {
        $canvas = imagecreatetruecolor(300, 360);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 226, 232, 240));
        $ink = imagecolorallocate($canvas, 100, 116, 139);
        imagefilledellipse($canvas, 150, 132, 110, 132, $ink);
        imagefilledellipse($canvas, 150, 336, 232, 192, $ink);
        $tmp = sys_get_temp_dir().'/ems-face-'.uniqid().'.png';
        imagepng($canvas, $tmp);
        imagedestroy($canvas);
        $disk->put($path, file_get_contents($tmp));
        unlink($tmp);
    }

    return $path;
};

// Ensure BSIT 1-A block exists
$bsitBlock = DB::table('blocks')->where('courseId', 1)->where('termId', $termId)->first();
if (! $bsitBlock) {
    $bsitBlockId = DB::table('blocks')->insertGetId([
        'courseId' => 1, 'termId' => $termId, 'yearLevel' => 1, 'blockName' => 'BSIT 1-A', 'maxStudents' => 40,
    ]);
    DB::table('schedules')->insert([
        'blockId' => $bsitBlockId,
        'subjectId' => 5, // IT101
        'instructorId' => $adminUser->userId,
        'roomId' => 1,
    ]);
    echo "✔ Created block BSIT 1-A with IT101 schedule\n";
}

// Helper to create basic student
function createDemoStudent($schoolId, $first, $last, $user, $email, $gender = 'male', $passwordHash = null)
{
    global $defaultPassword;
    $student = Students::where('schoolIdNumber', $schoolId)->first();
    if ($student) {
        return $student;
    }

    return Students::create([
        'schoolIdNumber' => $schoolId,
        'firstName' => $first,
        'lastName' => $last,
        'middleName' => 'M',
        'suffix' => '',
        'gender' => $gender,
        'birthdate' => '2004-05-12',
        'birthplace' => 'Davao City',
        'citizenship' => 'Filipino',
        'civilStatus' => 'single',
        'religionId' => 1,
        'contactNumber' => '0917'.rand(1000000, 9999999),
        'email' => $email,
        'username' => $user,
        'passwordHash' => $passwordHash ?? $defaultPassword,
        'status' => 'active',
        'semestersCompleted' => 0,
        'yearsInInstitution' => 0,
    ]);
}

// Ensure today's payment exists on Juan Dela Cruz (DEMO-2026-001) for Daily Collections Report
$juanStudent = Students::where('schoolIdNumber', 'DEMO-2026-001')->first();
if ($juanStudent) {
    $juanEnrollment = Enrollments::where('studentId', $juanStudent->studentId)->first();
    if ($juanEnrollment) {
        $todayPayment = DB::table('payments')
            ->where('enrollmentId', $juanEnrollment->enrollmentId)
            ->whereDate('paymentDate', now()->toDateString())
            ->first();
        if (! $todayPayment) {
            // The OR number is unique and controlled, so re-running this on a
            // later calendar day must re-date the existing slip rather than
            // insert a second one with the same number.
            $earlierPayment = DB::table('payments')->where('orNumber', 'DEMO-OR-TODAY-01')->first();
            if ($earlierPayment) {
                DB::table('payments')
                    ->where('paymentId', $earlierPayment->paymentId)
                    ->update(['paymentDate' => now()->toDateString(), 'updated_at' => now()]);
                echo "✔ Re-dated Juan Dela Cruz's demo payment to today (Daily Collections Report active)\n";
            } else {
                DB::table('payments')->insert([
                    'enrollmentId' => $juanEnrollment->enrollmentId,
                    'orNumber' => 'DEMO-OR-TODAY-01',
                    'amount' => 17500.00,
                    'paymentDate' => now()->toDateString(),
                    'paymentMode' => 'cash',
                    'processedBy' => 3,
                    'paymentStatus' => 'paid',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                echo "✔ Added today-dated payment for Juan Dela Cruz (Daily Collections Report active)\n";
            }
        }
    }
}

// -------------------------------------------------------------
// 1. ADMISSIONS DESK — REJECTED APPLICANT (DEMO-2026-006)
// -------------------------------------------------------------
$carlo = createDemoStudent('DEMO-2026-006', 'Carlo', 'Mendoza', 'demo_carlo', 'demo.carlo@example.com');
$admCarlo = Admissions::where('studentId', $carlo->studentId)->first();
if (! $admCarlo) {
    $admCarlo = Admissions::create([
        'studentId' => $carlo->studentId,
        'courseId' => 3, // BSCrim
        'termId' => $termId,
        'applicantType' => ApplicantType::Transferee,
        'applicationMode' => ApplicationMode::FaceToFace,
        'admissionStatus' => AdmissionStatus::Rejected,
        'evaluatedBy' => 7, // Admission Head
        'evaluatedDate' => now(),
    ]);
    echo "✔ Admissions: Seeded Carlo Mendoza (DEMO-2026-006) - REJECTED with remarks\n";
}

// -------------------------------------------------------------
// 2. ADMISSIONS DESK — APPROVED APPLICANT (DEMO-2026-005)
// -------------------------------------------------------------
$ana = createDemoStudent('DEMO-2026-005', 'Ana', 'Gonzales', 'demo_ana', 'demo.ana@example.com', 'female');
$admAna = Admissions::where('studentId', $ana->studentId)->first();
if (! $admAna) {
    $admAna = Admissions::create([
        'studentId' => $ana->studentId,
        'courseId' => 1, // BSIT
        'termId' => $termId,
        'applicantType' => ApplicantType::FirstYear,
        'applicationMode' => ApplicationMode::FaceToFace,
        'admissionStatus' => AdmissionStatus::Approved,
        'evaluatedBy' => 7,
        'evaluatedDate' => now(),
    ]);
    echo "✔ Admissions: Seeded Ana Gonzales (DEMO-2026-005) - APPROVED\n";
}

// -------------------------------------------------------------
// 3. CLEARANCE DESK — MULTI-OFFICE IN PROGRESS (DEMO-2026-007)
// -------------------------------------------------------------
$rico = createDemoStudent('DEMO-2026-007', 'Rico', 'Navarro', 'demo_rico', 'demo.rico@example.com');
$period = Clearanceperiods::where('periodStatus', 'open')->first();
if ($period) {
    $clrRico = Studentclearances::where('studentId', $rico->studentId)->where('clearancePeriodId', $period->clearancePeriodId)->first();
    if (! $clrRico) {
        $clrRico = Studentclearances::create([
            'studentId' => $rico->studentId,
            'clearancePeriodId' => $period->clearancePeriodId,
            'overallStatus' => ClearanceOverallStatus::Pending,
        ]);
        $reqs = Clearancerequirements::all();
        $approvedCount = 0;
        foreach ($reqs as $idx => $req) {
            // First 4 approved, remaining pending to show live interactive action
            $isApp = $idx < 4;
            DB::table('clearanceapprovals')->insert([
                'studentClearanceId' => $clrRico->studentClearanceId,
                'clearanceRequirementId' => $req->clearanceRequirementId,
                'status' => $isApp ? 'approved' : 'pending',
                'approvedBy' => $isApp ? 1 : null,
                'approvalDate' => $isApp ? now() : null,
                'remarks' => $isApp ? 'Cleared' : '',
            ]);
            if ($isApp) {
                $approvedCount++;
            }
        }
        echo "✔ Clearance: Seeded Rico Navarro (DEMO-2026-007) - IN PROGRESS ($approvedCount/10 offices approved)\n";
    }
}

// -------------------------------------------------------------
// 4. ACADEMIC EVALUATION DESK — WAITING IN QUEUE (DEMO-2026-008 & 009)
// -------------------------------------------------------------
$elena = createDemoStudent('DEMO-2026-008', 'Elena', 'Ramos', 'demo_elena', 'demo.elena@example.com', 'female');
$enrElena = Enrollments::where('studentId', $elena->studentId)->first();
if (! $enrElena) {
    $enrElena = Enrollments::create([
        'studentId' => $elena->studentId,
        'courseId' => 1, // BSIT
        'termId' => $termId,
        'yearLevel' => 1,
        'studentType' => StudentType::FirstYear,
        'enrollmentType' => EnrollmentType::New,
        'academicStanding' => null,
        'evaluatedBy' => 5,
        'enrollmentStatus' => EnrollmentStatus::Pending,
    ]);
    echo "✔ Evaluation Desk: Seeded Elena Ramos (DEMO-2026-008) - PENDING evaluation queue\n";
}

$rafael = createDemoStudent('DEMO-2026-009', 'Rafael', 'Cruz', 'demo_rafael', 'demo.rafael@example.com');
$enrRafael = Enrollments::where('studentId', $rafael->studentId)->first();
if (! $enrRafael) {
    $enrRafael = Enrollments::create([
        'studentId' => $rafael->studentId,
        'courseId' => 3, // BSCrim
        'termId' => $termId,
        'yearLevel' => 2,
        'studentType' => StudentType::Transferee,
        'enrollmentType' => EnrollmentType::New,
        'academicStanding' => null,
        'evaluatedBy' => 5,
        'enrollmentStatus' => EnrollmentStatus::Pending,
    ]);
    echo "✔ Evaluation Desk: Seeded Rafael Cruz (DEMO-2026-009) - PENDING transferee queue\n";
}

// -------------------------------------------------------------
// 4b. ACADEMIC STANDING EVIDENCE — the grades the Evaluation desk derives from
//     (DEMO-2026-004 repeating a year, DEMO-2026-018 advancing, DEMO-2026-008
//      and DEMO-2026-009 with no institutional grades at all)
//
// Academic standing is NOT asserted when an enrollment is created any more: the
// evaluating department decides it from the grades on file and the Registrar
// finalizes it at approval. So the students queued above need a GRADED PREVIOUS
// TERM for the desk to have anything to decide from. The grades below are the
// only grades this tool writes.
//
// Grades use the Philippine inverted scale (1.00 highest, 5.00 lowest). Against
// the PROVISIONAL grade scale seeded by DevReferenceDataSeeder, anything worse
// than 3.00 is a failed subject:
//   passes + 1 failure    -> derived IRREGULAR
//   3 clean passes        -> derived REGULAR
//   no institutional past -> cannot derive, the evaluator decides on their own
// -------------------------------------------------------------
$priorTermId = 11; // 2nd semester 2025-2026 — a completed term, distinct from $termId

// DEMO-2026-004 Liza Bautista already owns a term-11 enrollment; grade it with
// one failed subject, then queue her for the same year level again.
$liza = Students::where('schoolIdNumber', 'DEMO-2026-004')->first();
$lizaPrior = Enrollments::where('studentId', $liza->studentId)->where('termId', $priorTermId)->first();
if ($lizaPrior) {
    foreach ([1 => '2.50', 2 => '3.00', 3 => '4.50'] as $subjectId => $grade) {
        DB::table('enrolledsubjects')->updateOrInsert(
            ['enrollmentId' => $lizaPrior->enrollmentId, 'subjectId' => $subjectId, 'attempt_number' => 1],
            ['status' => 'confirmed', 'blockId' => null, 'scheduleId' => null, 'grade' => $grade]
        );
    }
    echo "✔ Standing evidence: graded Liza Bautista's term-11 record (MATH101 = 4.50, below the pass line)\n";
}

$enrLiza = Enrollments::where('studentId', $liza->studentId)->where('termId', $termId)->first();
if (! $enrLiza) {
    $enrLiza = Enrollments::create([
        'studentId' => $liza->studentId,
        'courseId' => $lizaPrior->courseId,
        'termId' => $termId,
        'yearLevel' => $lizaPrior->yearLevel,
        'studentType' => StudentType::Continuing,
        'enrollmentType' => EnrollmentType::Old,
        'academicStanding' => null,
        'evaluatedBy' => 5,
        'enrollmentStatus' => EnrollmentStatus::Pending,
    ]);
    echo "✔ Evaluation Desk: Seeded Liza Bautista (DEMO-2026-004) - PENDING, repeating the year level\n";
}

// DEMO-2026-018 Marco Villanueva — clean term-11 record, now queued for year 2.
$marco = createDemoStudent('DEMO-2026-018', 'Marco', 'Villanueva', 'demo_marco', 'demo.marco@example.com');
$marcoPrior = Enrollments::where('studentId', $marco->studentId)->where('termId', $priorTermId)->first();
if (! $marcoPrior) {
    $marcoPrior = Enrollments::create([
        'studentId' => $marco->studentId,
        'courseId' => 1, // BSIT
        'termId' => $priorTermId,
        'yearLevel' => 1,
        'studentType' => StudentType::FirstYear,
        'enrollmentType' => EnrollmentType::New,
        'academicStanding' => AcademicStanding::Regular,
        'evaluatedBy' => 5,
        'enrolledDate' => now()->subMonths(9),
        'enrollmentStatus' => EnrollmentStatus::Enrolled,
    ]);
    foreach ([1 => '1.50', 2 => '2.25', 5 => '2.75'] as $subjectId => $grade) {
        DB::table('enrolledsubjects')->updateOrInsert(
            ['enrollmentId' => $marcoPrior->enrollmentId, 'subjectId' => $subjectId, 'attempt_number' => 1],
            ['status' => 'confirmed', 'blockId' => null, 'scheduleId' => null, 'grade' => $grade]
        );
    }
    echo "✔ Standing evidence: Marco Villanueva's term-11 record (all subjects pass)\n";
}

$enrMarco = Enrollments::where('studentId', $marco->studentId)->where('termId', $termId)->first();
if (! $enrMarco) {
    $enrMarco = Enrollments::create([
        'studentId' => $marco->studentId,
        'courseId' => 1, // BSIT
        'termId' => $termId,
        'yearLevel' => 2,
        'studentType' => StudentType::Continuing,
        'enrollmentType' => EnrollmentType::Old,
        'academicStanding' => null,
        'evaluatedBy' => 5,
        'enrollmentStatus' => EnrollmentStatus::Pending,
    ]);
    echo "✔ Evaluation Desk: Seeded Marco Villanueva (DEMO-2026-018) - PENDING, advancing to year 2\n";
}

// Nothing upstream of the Registrar may assert a standing: the desk in front of
// them decides it from the grades on file, and only the Registrar's approval
// makes it official. Rows seeded before that rule carry a `regular` nobody
// chose, so every enrollment still short of approval is corrected here — which
// also makes the Registrar screen's derivation panel the honest answer.
Enrollments::whereNotIn('enrollmentStatus', [
    EnrollmentStatus::Enrolled,
    EnrollmentStatus::Dropped,
])->update(['academicStanding' => null]);

$marco->update(['semestersCompleted' => 1, 'yearsInInstitution' => 1]);

// Print what the desk will actually see, so the operator knows the demo works
// before opening the browser.
foreach (Enrollments::where('termId', $termId)
    ->whereIn('enrollmentStatus', [EnrollmentStatus::Pending, EnrollmentStatus::ReturnedToEvaluation])
    ->with('student')->get() as $queued) {
    $report = $standingService->derive($queued);
    echo sprintf(
        "   ↳ %s: standing %s | records derive %s from %d graded subject(s) of a previous term\n",
        $queued->student->lastName.', '.$queued->student->firstName,
        $queued->academicStanding?->value ?? 'NOT YET DECIDED',
        $report['derived'] ?? 'nothing (no grades on file)',
        $report['gradedSubjectCount']
    );
}

// -------------------------------------------------------------
// 5. ASSESSMENT DESK — AWAITING FEE COMPUTATION (DEMO-2026-010)
// -------------------------------------------------------------
$grace = createDemoStudent('DEMO-2026-010', 'Grace', 'Tan', 'demo_grace', 'demo.grace@example.com', 'female');
$enrGrace = Enrollments::where('studentId', $grace->studentId)->first();
if (! $enrGrace) {
    $enrGrace = Enrollments::create([
        'studentId' => $grace->studentId,
        'courseId' => 1, // BSIT
        'termId' => $termId,
        'yearLevel' => 1,
        'studentType' => StudentType::FirstYear,
        'enrollmentType' => EnrollmentType::New,
        'academicStanding' => null,
        'evaluatedBy' => 5,
        'formSignedDate' => now(),
        'enrollmentStatus' => EnrollmentStatus::Evaluated,
    ]);
    // Propose 3 subjects
    foreach ([1, 2, 5] as $subId) {
        DB::table('enrolledsubjects')->insert([
            'enrollmentId' => $enrGrace->enrollmentId,
            'subjectId' => $subId,
            'status' => 'proposed',
            'attempt_number' => 1,
        ]);
    }
    // Create workflow
    $wf = Enrollmentworkflow::create([
        'enrollmentId' => $enrGrace->enrollmentId,
        'currentStep' => 1,
        'workflowStatus' => WorkflowStatus::InProgress,
    ]);
    foreach ([4 => 1, 3 => 2, 2 => 3, 1 => 4, 5 => 5, 11 => 6, 22 => 7] as $off => $order) {
        Workflowsteps::create([
            'workflowId' => $wf->workflowId,
            'officeId' => $off,
            'stepOrder' => $order,
            'stepStatus' => $order === 1 ? WorkflowStepStatus::Completed : WorkflowStepStatus::Pending,
            'signedBy' => $order === 1 ? 5 : null,
            'signedDate' => $order === 1 ? now() : null,
        ]);
    }
    echo "✔ Assessment Desk: Seeded Grace Tan (DEMO-2026-010) - EVALUATED, awaiting fee computation\n";
}

// -------------------------------------------------------------
// 6. CASHIER DESK — UNPAID & PARTIAL PAYMENTS (DEMO-2026-012 & 013)
// -------------------------------------------------------------
// Unpaid student awaiting cashier payment
$christian = createDemoStudent('DEMO-2026-012', 'Christian', 'Lim', 'demo_christian', 'demo.christian@example.com');
$enrChristian = Enrollments::where('studentId', $christian->studentId)->first();
if (! $enrChristian) {
    $enrChristian = Enrollments::create([
        'studentId' => $christian->studentId,
        'courseId' => 1,
        'termId' => $termId,
        'yearLevel' => 1,
        'studentType' => StudentType::FirstYear,
        'enrollmentType' => EnrollmentType::New,
        'academicStanding' => null,
        'evaluatedBy' => 5,
        'formSignedDate' => now(),
        'enrollmentStatus' => EnrollmentStatus::Assessed,
    ]);
    foreach ([1, 2, 3] as $subId) {
        DB::table('enrolledsubjects')->insert([
            'enrollmentId' => $enrChristian->enrollmentId,
            'subjectId' => $subId,
            'status' => 'proposed',
            'attempt_number' => 1,
        ]);
    }
    $assChristian = DB::table('studentassessments')->insertGetId([
        'enrollmentId' => $enrChristian->enrollmentId,
        'totalAssessedAmount' => 17500.00,
        'totalScholarshipCoverage' => 0.00,
        'totalWaived' => 0.00,
        'remainingBalance' => 17500.00,
        'assessmentDate' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    // Fee charges
    DB::table('charges')->insert([
        ['assessmentId' => $assChristian, 'feeTypeId' => 1, 'amount' => 11250, 'waivedAmount' => 0],
        ['assessmentId' => $assChristian, 'feeTypeId' => 2, 'amount' => 1500, 'waivedAmount' => 0],
        ['assessmentId' => $assChristian, 'feeTypeId' => 3, 'amount' => 4500, 'waivedAmount' => 0],
        ['assessmentId' => $assChristian, 'feeTypeId' => 4, 'amount' => 250, 'waivedAmount' => 0],
    ]);
    echo "✔ Cashier Desk: Seeded Christian Lim (DEMO-2026-012) - ASSESSED, unpaid balance ₱17,500\n";
}

// Partial payment student
$bea = createDemoStudent('DEMO-2026-013', 'Bea', 'Alonzo', 'demo_bea', 'demo.bea@example.com', 'female');
$enrBea = Enrollments::where('studentId', $bea->studentId)->first();
if (! $enrBea) {
    $enrBea = Enrollments::create([
        'studentId' => $bea->studentId,
        'courseId' => 5, // BSBA
        'termId' => $termId,
        'yearLevel' => 1,
        'studentType' => StudentType::FirstYear,
        'enrollmentType' => EnrollmentType::New,
        'academicStanding' => null,
        'evaluatedBy' => 5,
        'formSignedDate' => now(),
        'enrollmentStatus' => EnrollmentStatus::Assessed,
    ]);
    foreach ([1, 2, 6] as $subId) {
        DB::table('enrolledsubjects')->insert([
            'enrollmentId' => $enrBea->enrollmentId,
            'subjectId' => $subId,
            'status' => 'proposed',
            'attempt_number' => 1,
        ]);
    }
    $assBea = DB::table('studentassessments')->insertGetId([
        'enrollmentId' => $enrBea->enrollmentId,
        'totalAssessedAmount' => 18500.00,
        'totalScholarshipCoverage' => 0.00,
        'totalWaived' => 0.00,
        'remainingBalance' => 10500.00, // ₱18,500 - ₱8,000 paid
        'assessmentDate' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('charges')->insert([
        ['assessmentId' => $assBea, 'feeTypeId' => 1, 'amount' => 12250, 'waivedAmount' => 0],
        ['assessmentId' => $assBea, 'feeTypeId' => 2, 'amount' => 1500, 'waivedAmount' => 0],
        ['assessmentId' => $assBea, 'feeTypeId' => 3, 'amount' => 4500, 'waivedAmount' => 0],
        ['assessmentId' => $assBea, 'feeTypeId' => 4, 'amount' => 250, 'waivedAmount' => 0],
    ]);
    // Today's partial payment
    DB::table('payments')->insert([
        'enrollmentId' => $enrBea->enrollmentId,
        'orNumber' => 'DEMO-OR-0002',
        'amount' => 8000.00,
        'paymentDate' => now()->toDateString(),
        'paymentMode' => 'cash',
        'processedBy' => 3,
        'paymentStatus' => 'paid',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    echo "✔ Cashier Desk: Seeded Bea Alonzo (DEMO-2026-013) - PARTIAL payment (₱8,000 paid, ₱10,500 balance)\n";
}

// -------------------------------------------------------------
// 7. REGISTRAR DESK — PAID, READY FOR APPROVAL (DEMO-2026-014)
// -------------------------------------------------------------
$patricia = createDemoStudent('DEMO-2026-014', 'Patricia', 'Diaz', 'demo_patricia', 'demo.patricia@example.com', 'female');
$enrPat = Enrollments::where('studentId', $patricia->studentId)->first();
if (! $enrPat) {
    $enrPat = Enrollments::create([
        'studentId' => $patricia->studentId,
        'courseId' => 1,
        'termId' => $termId,
        'yearLevel' => 1,
        'studentType' => StudentType::FirstYear,
        'enrollmentType' => EnrollmentType::New,
        'academicStanding' => null,
        'evaluatedBy' => 5,
        'formSignedDate' => now(),
        'enrollmentStatus' => EnrollmentStatus::Paid,
    ]);
    foreach ([1, 2, 5] as $subId) {
        DB::table('enrolledsubjects')->insert([
            'enrollmentId' => $enrPat->enrollmentId,
            'subjectId' => $subId,
            'status' => 'proposed',
            'attempt_number' => 1,
        ]);
    }
    $assPat = DB::table('studentassessments')->insertGetId([
        'enrollmentId' => $enrPat->enrollmentId,
        'totalAssessedAmount' => 17500.00,
        'totalScholarshipCoverage' => 0.00,
        'totalWaived' => 0.00,
        'remainingBalance' => 0.00,
        'assessmentDate' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('payments')->insert([
        'enrollmentId' => $enrPat->enrollmentId,
        'orNumber' => 'DEMO-OR-0003',
        'amount' => 17500.00,
        'paymentDate' => now()->toDateString(),
        'paymentMode' => 'cash',
        'processedBy' => 3,
        'paymentStatus' => 'paid',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $wfPat = Enrollmentworkflow::create([
        'enrollmentId' => $enrPat->enrollmentId,
        'currentStep' => 3,
        'workflowStatus' => WorkflowStatus::InProgress,
    ]);
    foreach ([4 => 1, 3 => 2, 2 => 3, 1 => 4, 5 => 5, 11 => 6, 22 => 7] as $off => $order) {
        $done = $order <= 3;
        Workflowsteps::create([
            'workflowId' => $wfPat->workflowId,
            'officeId' => $off,
            'stepOrder' => $order,
            'stepStatus' => $done ? WorkflowStepStatus::Completed : WorkflowStepStatus::Pending,
            'signedBy' => $done ? 3 : null,
            'signedDate' => $done ? now() : null,
        ]);
    }
    echo "✔ Registrar Desk: Seeded Patricia Diaz (DEMO-2026-014) - PAID, all prerequisites complete for approval\n";
}

// -------------------------------------------------------------
// 8. BLOCKING & CLINIC DESK — READY FOR ASSIGNMENT (DEMO-2026-015)
// -------------------------------------------------------------
$gabriel = createDemoStudent('DEMO-2026-015', 'Gabriel', 'Ramos', 'demo_gabriel', 'demo.gabriel@example.com');
$enrGab = Enrollments::where('studentId', $gabriel->studentId)->first();
if (! $enrGab) {
    $enrGab = Enrollments::create([
        'studentId' => $gabriel->studentId,
        'courseId' => 1,
        'termId' => $termId,
        'yearLevel' => 1,
        'studentType' => StudentType::FirstYear,
        'enrollmentType' => EnrollmentType::New,
        'academicStanding' => AcademicStanding::Regular,
        'evaluatedBy' => 5,
        'registrarProcessedBy' => 2,
        'enrolledDate' => now(),
        'formSignedDate' => now(),
        'enrollmentStatus' => EnrollmentStatus::Enrolled,
    ]);
    foreach ([1, 2, 5] as $subId) {
        DB::table('enrolledsubjects')->insert([
            'enrollmentId' => $enrGab->enrollmentId,
            'subjectId' => $subId,
            'status' => 'confirmed',
            'attempt_number' => 1,
        ]);
    }
    $wfGab = Enrollmentworkflow::create([
        'enrollmentId' => $enrGab->enrollmentId,
        'currentStep' => 4,
        'workflowStatus' => WorkflowStatus::InProgress,
    ]);
    foreach ([4 => 1, 3 => 2, 2 => 3, 1 => 4, 5 => 5, 11 => 6, 22 => 7] as $off => $order) {
        $done = $order <= 4;
        Workflowsteps::create([
            'workflowId' => $wfGab->workflowId,
            'officeId' => $off,
            'stepOrder' => $order,
            'stepStatus' => $done ? WorkflowStepStatus::Completed : WorkflowStepStatus::Pending,
            'signedBy' => $done ? 2 : null,
            'signedDate' => $done ? now() : null,
        ]);
    }
    echo "✔ Blocking & Clinic: Seeded Gabriel Ramos (DEMO-2026-015) - ENROLLED, awaiting block & clinic\n";
}

// -------------------------------------------------------------
// 9. STUDENT ID HUB — PENDING ID PRODUCTION (DEMO-2026-016 & 017)
// -------------------------------------------------------------
$danica = createDemoStudent('DEMO-2026-016', 'Danica', 'Sotto', 'demo_danica', 'demo.danica@example.com', 'female');
$enrDan = Enrollments::where('studentId', $danica->studentId)->first();
if (! $enrDan) {
    $enrDan = Enrollments::create([
        'studentId' => $danica->studentId,
        'courseId' => 3,
        'termId' => $termId,
        'yearLevel' => 1,
        'studentType' => StudentType::FirstYear,
        'enrollmentType' => EnrollmentType::New,
        'academicStanding' => AcademicStanding::Regular,
        'evaluatedBy' => 5,
        'registrarProcessedBy' => 2,
        'enrolledDate' => now(),
        'enrollmentStatus' => EnrollmentStatus::Enrolled,
    ]);
    DB::table('idrequests')->insert([
        'enrollmentId' => $enrDan->enrollmentId,
        'requestReason' => 'newStudent',
        'emergencyContactName' => 'Vic Sotto',
        'emergencyContactNumber' => '09171234599',
        'bloodType' => 'A+',
        'status' => 'pending',
        'requestDate' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    echo "✔ ID Desk: Seeded Danica Sotto (DEMO-2026-016) - ID REQUESTED (awaiting ID Office validation)\n";
}

$kevin = createDemoStudent('DEMO-2026-017', 'Kevin', 'Santos', 'demo_kevin', 'demo.kevin@example.com');
$enrKev = Enrollments::where('studentId', $kevin->studentId)->first();
if (! $enrKev) {
    $enrKev = Enrollments::create([
        'studentId' => $kevin->studentId,
        'courseId' => 3,
        'termId' => $termId,
        'yearLevel' => 1,
        'studentType' => StudentType::FirstYear,
        'enrollmentType' => EnrollmentType::New,
        'academicStanding' => AcademicStanding::Regular,
        'evaluatedBy' => 5,
        'registrarProcessedBy' => 2,
        'enrolledDate' => now(),
        'enrollmentStatus' => EnrollmentStatus::Enrolled,
    ]);
    DB::table('idrequests')->insert([
        'enrollmentId' => $enrKev->enrollmentId,
        'requestReason' => 'newStudent',
        'emergencyContactName' => 'Rosa Santos',
        'emergencyContactNumber' => '09171234500',
        'bloodType' => 'B+',
        'cardPhotoPath' => $demoFacePhoto('demo-2026-017'),
        'status' => 'validated',
        'validatedBy' => 11, // office22_head — the ID Office signer, see $deskSigners
        'validatedDate' => now(),
        'requestDate' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    echo "✔ ID Desk: Seeded Kevin Santos (DEMO-2026-017) - ID VALIDATED (validation is this desk's final action)\n";
}

// -------------------------------------------------------------
// 10. DESK SNAPSHOT — RE-SEAT EVERY QUEUE, WHATEVER THE DESKS DID
// -------------------------------------------------------------
// Sections 1-9 only write a row when it is missing, so the first time a screen
// is used in practice — an enrollment approved, a payment recorded, a clearance
// released — that desk's queue empties and a second run of this tool cannot
// notice. This section runs unconditionally and drives named students back to
// the position each desk needs. Status changes go through the
// EnrollmentStateMachine so the history panels print is real, and every workflow
// box is signed by staff who belong to the office that owns that box.

$deskSigners = [
    OfficeId::Guidance->value => 5,       // office4_head  — Department Evaluation
    OfficeId::Scholarship->value => 4,    // office3_head  — Assessment
    OfficeId::Accounting->value => 3,     // office2_head  — Cashier
    OfficeId::Registrar->value => 2,      // office1_head  — Registrar
    OfficeId::Blocking->value => 6,       // office5_head  — Blocking & Scheduling
    OfficeId::Clinic->value => 10,        // office11_head — Clinic
    OfficeId::IdOffice->value => 11,      // office22_head — ID Office
];

$stateMachine = app(EnrollmentStateMachine::class);

/**
 * Complete every box ahead of $officeId and leave that box pending, which is
 * exactly the state the desk in front of this one leaves behind. Written through
 * the query builder because the step models do not mass-assign these columns.
 */
$seatWorkflow = function (Enrollments $enrollment, int $officeId) use ($workflowService, $deskSigners): void {
    // The relation is dropped first: a caller may hold an instance loaded before
    // this enrollment had a workflow, and a stale null would mint a second one —
    // two competing workflows on one enrollment is exactly what an approver's
    // "next pending step" check cannot survive.
    $enrollment->unsetRelation('enrollmentworkflow');
    $workflow = $enrollment->enrollmentworkflow ?? $workflowService->createWorkflow($enrollment);
    $steps = $workflow->workflowsteps()->orderBy('stepOrder')->get();
    $target = $steps->firstWhere('officeId', $officeId);

    if (! $target) {
        echo "  ! no workflow box for office {$officeId} on enrollment {$enrollment->enrollmentId} — skipped\n";

        return;
    }

    foreach ($steps as $step) {
        $ahead = $step->stepOrder < $target->stepOrder;
        DB::table('workflowsteps')->where('workflowStepId', $step->workflowStepId)->update([
            'stepStatus' => $ahead ? WorkflowStepStatus::Completed->value : WorkflowStepStatus::Pending->value,
            'signedBy' => $ahead ? ($deskSigners[$step->officeId] ?? 1) : null,
            'signedDate' => $ahead ? now() : null,
        ]);
    }

    DB::table('enrollmentworkflow')->where('workflowId', $workflow->workflowId)->update([
        'currentStep' => $target->stepOrder,
        'workflowStatus' => WorkflowStatus::InProgress->value,
    ]);
};

/**
 * Sign every box on an enrollment and close the workflow — the finished state a
 * student reaches after the last desk. Same query-builder approach as
 * $seatWorkflow, for the same reason.
 */
$completeWorkflow = function (Enrollments $enrollment) use ($workflowService, $deskSigners): void {
    $enrollment->unsetRelation('enrollmentworkflow');
    $workflow = $enrollment->enrollmentworkflow ?? $workflowService->createWorkflow($enrollment);
    $steps = $workflow->workflowsteps()->orderBy('stepOrder')->get();

    foreach ($steps as $step) {
        DB::table('workflowsteps')->where('workflowStepId', $step->workflowStepId)->update([
            'stepStatus' => WorkflowStepStatus::Completed->value,
            'signedBy' => $deskSigners[$step->officeId] ?? 1,
            'signedDate' => now(),
        ]);
    }

    DB::table('enrollmentworkflow')->where('workflowId', $workflow->workflowId)->update([
        'currentStep' => $steps->max('stepOrder'),
        'workflowStatus' => WorkflowStatus::Completed->value,
    ]);
};

// The fee sheet every demo student is charged, from the seeded fee types.
$chargeDemoFees = function (int $assessmentId, array $amounts = [1 => 11250, 2 => 1500, 3 => 4500, 4 => 250]): void {
    foreach ($amounts as $feeTypeId => $amount) {
        DB::table('charges')->updateOrInsert(
            ['assessmentId' => $assessmentId, 'feeTypeId' => $feeTypeId],
            ['amount' => $amount, 'waivedAmount' => 0]
        );
    }
};

// An Official Receipt is a controlled document, so the number is the key: re-running
// the tool re-dates the slip rather than minting a second one for the same number.
$recordDemoPayment = function (Enrollments $enrollment, string $orNumber, float $amount, string $mode) use ($deskSigners): void {
    $cashier = $deskSigners[OfficeId::Accounting->value];
    DB::table('payments')->updateOrInsert(
        ['orNumber' => $orNumber],
        [
            'enrollmentId' => $enrollment->enrollmentId,
            'amount' => $amount,
            'paymentDate' => now()->toDateString(),
            'paymentMode' => $mode,
            'processedBy' => $cashier,
            'paymentStatus' => 'paid',
            'updated_at' => now(),
            'created_at' => now(),
        ]
    );
};

$assessmentFor = function (Enrollments $enrollment, float $assessed, float $covered = 0, float $waived = 0): int {
    $existing = DB::table('studentassessments')->where('enrollmentId', $enrollment->enrollmentId)->first();
    $data = [
        'totalAssessedAmount' => $assessed,
        'totalScholarshipCoverage' => $covered,
        'totalWaived' => $waived,
        'assessmentDate' => now()->toDateString(),
        'updated_at' => now(),
    ];

    if ($existing) {
        DB::table('studentassessments')->where('assessmentId', $existing->assessmentId)->update($data);

        return (int) $existing->assessmentId;
    }

    return (int) DB::table('studentassessments')->insertGetId($data + [
        'enrollmentId' => $enrollment->enrollmentId,
        'remainingBalance' => max(0, $assessed - $covered - $waived),
        'created_at' => now(),
    ]);
};

$proposeSubjects = function (Enrollments $enrollment, array $subjectIds, string $status = 'proposed'): void {
    foreach ($subjectIds as $subjectId) {
        // enrolledsubjects carries no timestamps, so the attempt triple is the key.
        DB::table('enrolledsubjects')->updateOrInsert(
            ['enrollmentId' => $enrollment->enrollmentId, 'subjectId' => $subjectId, 'attempt_number' => 1],
            ['status' => $status]
        );
    }
};

// 10a. The Registrar needs an enrollment it can approve, and a live walkthrough
//      consumes one — so the desk is seeded with three, covering both approval
//      paths: a first-year the department must judge on its own judgment, and two
//      continuing students whose standing the record can derive from real
//      previous-term grades (and who therefore need a released clearance).
//      Receipt numbers are controlled documents and unique in the database, so
//      each student owns their own — sharing one silently moves the slip to the
//      last student written and leaves the first paid in full on paper only.
$registrarSeats = [
    'DEMO-2026-004' => ['cash' => 'DEMO-OR-0006', 'check' => 'DEMO-OR-CHK-01'],
    'DEMO-2026-010' => ['cash' => 'DEMO-OR-0005', 'check' => 'DEMO-OR-CHK-02'],
    'DEMO-2026-018' => ['cash' => 'DEMO-OR-0007', 'check' => 'DEMO-OR-CHK-03'],
];

$seatRegistrar = function (string $schoolId, array $receipts) use (
    $termId,
    $stateMachine,
    $deskSigners,
    $proposeSubjects,
    $assessmentFor,
    $chargeDemoFees,
    $recordDemoPayment,
    $seatWorkflow
): ?array {
    $student = Students::where('schoolIdNumber', $schoolId)->first();
    $enrollment = $student
        ? Enrollments::where('studentId', $student->studentId)->where('termId', $termId)->orderByDesc('enrollmentId')->first()
        : null;

    if (! $enrollment) {
        echo "  ! Registrar-ready enrollment not found for {$schoolId} — skipped\n";

        return null;
    }

    // An enrollment a walkthrough already approved stays approved: reopening it
    // would leave the certificate printed against a record that is pending again.
    if (in_array($enrollment->enrollmentStatus, [EnrollmentStatus::Enrolled, EnrollmentStatus::Dropped], true)) {
        echo "  • {$schoolId} is already {$enrollment->enrollmentStatus->value} — left as the record shows it\n";

        return null;
    }

    $evaluator = Staffusers::find($deskSigners[OfficeId::Guidance->value]);
    $assessmentOfficer = Staffusers::find($deskSigners[OfficeId::Scholarship->value]);
    $cashier = Staffusers::find($deskSigners[OfficeId::Accounting->value]);

    $proposeSubjects($enrollment, [1, 2, 5]);

    if ($enrollment->enrollmentStatus === EnrollmentStatus::Pending) {
        $enrollment = $stateMachine->transition($enrollment, EnrollmentStatus::Evaluated, $evaluator, 'Subject load proposed by Department Evaluation');
    }
    $enrollment->update(['evaluatedBy' => $evaluator->userId]);

    $assessmentId = $assessmentFor($enrollment, 17500.00);
    $chargeDemoFees($assessmentId);

    if ($enrollment->enrollmentStatus === EnrollmentStatus::Evaluated) {
        $enrollment = $stateMachine->transition($enrollment, EnrollmentStatus::Assessed, $assessmentOfficer, 'Fees computed by Assessment');
    }

    $recordDemoPayment($enrollment, $receipts['cash'], 12000.00, 'cash');
    $recordDemoPayment($enrollment, $receipts['check'], 5500.00, 'check');

    $paid = (float) DB::table('payments')->where('enrollmentId', $enrollment->enrollmentId)->where('paymentStatus', 'paid')->sum('amount');
    DB::table('studentassessments')->where('assessmentId', $assessmentId)->update(['remainingBalance' => max(0, 17500.00 - $paid)]);

    if ($enrollment->enrollmentStatus === EnrollmentStatus::Assessed) {
        $enrollment = $stateMachine->transition(
            $enrollment,
            EnrollmentStatus::Paid,
            $cashier,
            'Full payment received — cash OR '.$receipts['cash'].' and bank check OR '.$receipts['check']
        );
    }

    // Standing stays UNDECIDED here on purpose: the evaluating department may
    // propose it, but the label only becomes official when the Registrar chooses
    // it at approval, and the screen must show what the grades actually support.
    $enrollment->update(['academicStanding' => null]);

    $seatWorkflow($enrollment, OfficeId::Registrar->value);

    echo "✔ Registrar Desk: {$schoolId} re-seated — PAID (cash + bank check), standing undecided, awaiting approval\n";

    return ['student' => $student, 'enrollment' => $enrollment];
};

$seatedForRegistrar = array_values(array_filter(array_map(
    fn (string $schoolId) => $seatRegistrar($schoolId, $registrarSeats[$schoolId]),
    array_keys($registrarSeats)
)));

// 10b. A continuing student cannot be approved without an approved clearance in
//      the open period, and every clearance must carry one approval row per
//      requirement — that checklist is what the Clearance desk prints. The
//      multi-office demo clearance lost its rows (it predates this tool writing
//      them), which left the desk with an empty checklist.
$openPeriod = Clearanceperiods::where('periodStatus', 'open')->first();

foreach (Studentclearances::with('approvals')->get() as $clearance) {
    $covered = $clearance->approvals->pluck('clearanceRequirementId')->all();
    $outstanding = 0;

    foreach (Clearancerequirements::all() as $index => $requirement) {
        if (in_array($requirement->clearanceRequirementId, $covered, true)) {
            continue;
        }

        // Four offices already cleared so the slip is visibly mid-flight, the
        // rest stay pending for the desks to act on live.
        $cleared = $clearance->overallStatus !== ClearanceOverallStatus::Approved && $index < 4;
        DB::table('clearanceapprovals')->insert([
            'studentClearanceId' => $clearance->studentClearanceId,
            'clearanceRequirementId' => $requirement->clearanceRequirementId,
            'status' => $cleared ? 'approved' : 'pending',
            'approvedBy' => $cleared ? ($deskSigners[$requirement->officeId] ?? 1) : null,
            'approvalDate' => $cleared ? now()->toDateString() : null,
            'remarks' => $cleared ? 'Cleared' : '',
        ]);
        $outstanding++;
    }

    if ($outstanding > 0) {
        echo "✔ Clearance: seeded {$outstanding} approval row(s) on clearance {$clearance->studentClearanceId}\n";
    }
}

// Only continuing and shifter students are checked against a clearance at the
// Registrar desk — a first-year or transferee has no prior term to clear, so the
// gate treats them as verified and releasing a slip here would be theatre.
foreach ($seatedForRegistrar as $seat) {
    $student = $seat['student'];
    $enrollment = $seat['enrollment'];

    if (! in_array($enrollment->studentType->value, ['continuing', 'shifter'], true)) {
        echo "  • {$student->schoolIdNumber} is {$enrollment->studentType->value} — no clearance required\n";

        continue;
    }

    if (! $openPeriod) {
        echo "  ! No open clearance period — {$student->schoolIdNumber} cannot be approved\n";

        continue;
    }

    $clearance = Studentclearances::where('studentId', $student->studentId)
        ->where('clearancePeriodId', $openPeriod->clearancePeriodId)
        ->first();

    if (! $clearance) {
        $clearance = Studentclearances::create([
            'studentId' => $student->studentId,
            'clearancePeriodId' => $openPeriod->clearancePeriodId,
            'overallStatus' => ClearanceOverallStatus::Pending,
        ]);
    }

    foreach (Clearancerequirements::all() as $requirement) {
        DB::table('clearanceapprovals')->updateOrInsert(
            [
                'studentClearanceId' => $clearance->studentClearanceId,
                'clearanceRequirementId' => $requirement->clearanceRequirementId,
            ],
            [
                'status' => 'approved',
                'approvedBy' => $deskSigners[$requirement->officeId] ?? 1,
                'approvalDate' => now()->toDateString(),
                'remarks' => 'No outstanding account',
            ]
        );
    }

    $clearance->update([
        'overallStatus' => ClearanceOverallStatus::Approved,
        'receivedBy' => $deskSigners[OfficeId::Registrar->value],
        'receivedDate' => now(),
    ]);

    echo "✔ Clearance: {$student->schoolIdNumber} released — all offices cleared, slip received at the Registrar desk\n";
}

// 10c. Assessment desk — a proposed first-year load waiting to be costed.
$angelica = createDemoStudent('DEMO-2026-019', 'Angelica', 'Reyes', 'demo_angelica', 'demo.angelica@example.com', 'female');
$enrAngelica = Enrollments::where('studentId', $angelica->studentId)->where('termId', $termId)->first();
if (! $enrAngelica) {
    $enrAngelica = Enrollments::create([
        'studentId' => $angelica->studentId,
        'courseId' => 1,
        'termId' => $termId,
        'yearLevel' => 1,
        'studentType' => StudentType::FirstYear,
        'enrollmentType' => EnrollmentType::New,
        'academicStanding' => null,
        'evaluatedBy' => $deskSigners[OfficeId::Guidance->value],
        'formSignedDate' => now(),
        'enrollmentStatus' => EnrollmentStatus::Evaluated,
    ]);
    echo "✔ Assessment Desk: Seeded DEMO-2026-019 Angelica Reyes — EVALUATED, awaiting fee computation\n";
} else {
    echo "✔ Assessment Desk: DEMO-2026-019 Angelica Reyes re-seated\n";
}
$proposeSubjects($enrAngelica, [1, 2, 5]);
$seatWorkflow($enrAngelica, OfficeId::Scholarship->value);

// 10d. Accounting — an account fully covered by a grant, which is the only case
//      the Settle button exists for. The scholarship record is what makes the
//      coverage auditable rather than a number typed onto the assessment.
$marisol = createDemoStudent('DEMO-2026-020', 'Marisol', 'Domingo', 'demo_marisol', 'demo.marisol@example.com', 'female');
$enrMarisol = Enrollments::where('studentId', $marisol->studentId)->where('termId', $termId)->first();
if (! $enrMarisol) {
    $enrMarisol = Enrollments::create([
        'studentId' => $marisol->studentId,
        'courseId' => 1,
        'termId' => $termId,
        'yearLevel' => 1,
        'studentType' => StudentType::FirstYear,
        'enrollmentType' => EnrollmentType::New,
        'academicStanding' => null,
        'evaluatedBy' => $deskSigners[OfficeId::Guidance->value],
        'formSignedDate' => now(),
        'enrollmentStatus' => EnrollmentStatus::Assessed,
    ]);
    $proposeSubjects($enrMarisol, [1, 2, 5]);
    echo "✔ Accounting Desk: Seeded DEMO-2026-020 Marisol Domingo — ASSESSED, ₱0 due (full grant)\n";
}

DB::table('studentscholarships')->updateOrInsert(
    ['studentId' => $marisol->studentId, 'termId' => $termId],
    [
        'scholarshipTypeId' => 1, // Full Scholarship, 100%
        'status' => 'active',
        'approvedBy' => $deskSigners[OfficeId::Scholarship->value],
        'awardedBeforeEnrollment' => true,
    ]
);

$marisolAssessment = $assessmentFor($enrMarisol, 17500.00, 17500.00);
$chargeDemoFees($marisolAssessment);
DB::table('studentassessments')->where('assessmentId', $marisolAssessment)->update(['remainingBalance' => 0]);
$seatWorkflow($enrMarisol, OfficeId::Accounting->value);

// 10e. Evaluation — a load the Registrar sent back, which is the only way that
//      desk's return reason ever reaches the evaluator's screen.
$isagani = createDemoStudent('DEMO-2026-021', 'Isagani', 'Torres', 'demo_isagani', 'demo.isagani@example.com');
$enrIsagani = Enrollments::where('studentId', $isagani->studentId)->where('termId', $termId)->first();
if (! $enrIsagani) {
    $enrIsagani = Enrollments::create([
        'studentId' => $isagani->studentId,
        'courseId' => 1,
        'termId' => $termId,
        'yearLevel' => 1,
        'studentType' => StudentType::FirstYear,
        'enrollmentType' => EnrollmentType::New,
        'academicStanding' => null,
        'evaluatedBy' => $deskSigners[OfficeId::Guidance->value],
        'formSignedDate' => now(),
        'enrollmentStatus' => EnrollmentStatus::Pending,
    ]);
    $proposeSubjects($enrIsagani, [1, 2, 5]);

    $enrIsagani = $stateMachine->transition($enrIsagani, EnrollmentStatus::Evaluated, Staffusers::find($deskSigners[OfficeId::Guidance->value]), 'Subject load proposed by Department Evaluation');
    $isaganiAssessment = $assessmentFor($enrIsagani, 17500.00);
    $chargeDemoFees($isaganiAssessment);
    $enrIsagani = $stateMachine->transition($enrIsagani, EnrollmentStatus::Assessed, Staffusers::find($deskSigners[OfficeId::Scholarship->value]), 'Fees computed by Assessment');
    $recordDemoPayment($enrIsagani, 'DEMO-OR-0004', 17500.00, 'cash');
    DB::table('studentassessments')->where('assessmentId', $isaganiAssessment)->update(['remainingBalance' => 0]);
    $enrIsagani = $stateMachine->transition($enrIsagani, EnrollmentStatus::Paid, Staffusers::find($deskSigners[OfficeId::Accounting->value]), 'Full payment received');

    $returnReason = 'The proposed load does not match the published first-year curriculum for this course — re-propose before Assessment.';
    $enrIsagani = $stateMachine->transition(
        $enrIsagani,
        EnrollmentStatus::ReturnedToEvaluation,
        Staffusers::find($deskSigners[OfficeId::Registrar->value]),
        'Returned to Department Evaluation: '.$returnReason
    );
    $enrIsagani->update(['returnReason' => $returnReason]);

    $seatWorkflow($enrIsagani, OfficeId::Guidance->value);

    echo "✔ Evaluation Desk: Seeded DEMO-2026-021 Isagani Torres — RETURNED by the Registrar, reason visible to the evaluator\n";
} else {
    $seatWorkflow($enrIsagani, OfficeId::Guidance->value);
    echo "✔ Evaluation Desk: DEMO-2026-021 Isagani Torres re-seated\n";
}

// 10f. The cashier's queue must hold a fully unpaid account and a partially paid
//      one, and both need a workflow — several rows were created before this tool
//      wrote workflows, so those desks had nothing to sign.
foreach (['DEMO-2026-012' => 17500.00, 'DEMO-2026-013' => 18500.00] as $schoolId => $assessed) {
    $student = Students::where('schoolIdNumber', $schoolId)->first();
    if (! $student) {
        continue;
    }

    $enrollment = Enrollments::where('studentId', $student->studentId)->where('termId', $termId)->first();
    if (! $enrollment) {
        continue;
    }

    $assessmentId = DB::table('studentassessments')->where('enrollmentId', $enrollment->enrollmentId)->value('assessmentId');
    if ($assessmentId) {
        $chargeDemoFees((int) $assessmentId, $schoolId === 'DEMO-2026-013'
            ? [1 => 12250, 2 => 1500, 3 => 4500, 4 => 250]
            : [1 => 11250, 2 => 1500, 3 => 4500, 4 => 250]);
        $paid = (float) DB::table('payments')->where('enrollmentId', $enrollment->enrollmentId)->where('paymentStatus', 'paid')->sum('amount');
        DB::table('studentassessments')->where('assessmentId', $assessmentId)->update(['remainingBalance' => max(0, $assessed - $paid)]);
    }

    // Status is only pushed forward when the desk has not already acted: a row
    // approved in a live walkthrough stays approved, this just reseats the queue.
    if ($enrollment->enrollmentStatus === EnrollmentStatus::Evaluated) {
        $enrollment = $stateMachine->transition($enrollment, EnrollmentStatus::Assessed, Staffusers::find($deskSigners[OfficeId::Scholarship->value]), 'Fees computed by Assessment');
    }

    // seatWorkflow creates the workflow when the row predates this tool writing
    // one, which several of these demo rows did — the desks had nothing to sign.
    $seatWorkflow($enrollment, OfficeId::Accounting->value);
    echo "✔ Accounting Desk: re-seated {$schoolId} with a live balance\n";
}

// 10g. Blocking & Scheduling. A class card whose day and time boxes are empty is
//      a blank form, not a document, and the block schedule prints nothing at all
//      while schedulemeetings holds no rows. The desk cannot list an assigned
//      roster either while enrolledsubjects carries no blockId/scheduleId. So the
//      timetable is seeded the way BlockingController writes it: one schedules row
//      per (block, subject), meetings under it, and a scheduleId stamped ONLY on
//      the subject row it belongs to — never across the whole load.
$demoInstructors = [
    ['employeeNo' => 'EMP-20001', 'firstName' => 'Elena', 'lastName' => 'Mendoza'],
    ['employeeNo' => 'EMP-20002', 'firstName' => 'Rafael', 'lastName' => 'Salazar'],
    ['employeeNo' => 'EMP-20003', 'firstName' => 'Cristina', 'lastName' => 'Alvarez'],
];
$instructorIds = [];
foreach ($demoInstructors as $person) {
    $username = 'instr.'.strtolower($person['lastName']);
    DB::table('staffusers')->updateOrInsert(
        ['username' => $username],
        [
            'employeeNo' => $person['employeeNo'],
            // Academic Department: the enum has no case for it, and an instructor
            // signs no workflow box, so this is a home office for the roster only.
            'officeId' => 7,
            'firstName' => $person['firstName'],
            'middleName' => '',
            'lastName' => $person['lastName'],
            'passwordHash' => Hash::make('password'),
            'email' => "{$username}@seait.edu.ph",
            'contactNo' => '',
            'role' => 'instructor',
            'status' => 'active',
        ]
    );
    $instructorIds[] = (int) DB::table('staffusers')->where('username', $username)->value('userId');
}

$demoRooms = [
    ['roomName' => 'Room 101', 'building' => 'Main Building', 'capacity' => 40],
    ['roomName' => 'Room 102', 'building' => 'Main Building', 'capacity' => 40],
    ['roomName' => 'Room 201', 'building' => 'Annex Building', 'capacity' => 35],
];
$roomIds = [];
foreach ($demoRooms as $room) {
    // rooms and blocks carry no timestamp columns, so the payload stays to their own fields.
    DB::table('rooms')->updateOrInsert(['roomName' => $room['roomName']], $room);
    $roomIds[] = (int) DB::table('rooms')->where('roomName', $room['roomName'])->value('roomId');
}

// One fixed slot per subject, so no two classes of the same teacher or room can
// land on the same hour however the blocks are re-seeded.
$meetingSlots = [
    1 => [['Monday', '08:00:00', '09:30:00'], ['Wednesday', '08:00:00', '09:30:00']],
    2 => [['Tuesday', '08:00:00', '09:30:00'], ['Thursday', '08:00:00', '09:30:00']],
    3 => [['Monday', '10:00:00', '11:30:00'], ['Wednesday', '10:00:00', '11:30:00']],
    4 => [['Monday', '13:00:00', '14:30:00'], ['Wednesday', '13:00:00', '14:30:00']],
    5 => [['Tuesday', '13:00:00', '14:30:00'], ['Thursday', '13:00:00', '14:30:00']],
    6 => [['Friday', '08:00:00', '09:30:00']],
];
$defaultSlot = [['Friday', '10:00:00', '11:30:00']];

// Students whose load is empty cannot be blocked or printed, so the two ID-desk
// rows are given the subjects their course actually offers — before the
// timetable is built, because the timetable reads their load.
foreach (['DEMO-2026-016', 'DEMO-2026-017'] as $schoolId) {
    $student = Students::where('schoolIdNumber', $schoolId)->first();
    $enrollment = $student
        ? Enrollments::where('studentId', $student->studentId)->where('termId', $termId)->first()
        : null;

    if ($enrollment && DB::table('enrolledsubjects')->where('enrollmentId', $enrollment->enrollmentId)->count() === 0) {
        $proposeSubjects($enrollment, [1, 2, 4], 'confirmed');
        echo "✔ Blocking Desk: {$schoolId} given the subject load their record was missing\n";
    }
}

// Only the cohort actually enrolled in the term shapes a block's timetable: a
// student still sitting at Evaluation would otherwise publish a class nobody is in.
$demoBlocks = DB::table('enrollments')
    ->where('termId', $termId)
    ->where('enrollmentStatus', EnrollmentStatus::Enrolled->value)
    ->select('courseId', 'yearLevel')
    ->distinct()
    ->orderBy('courseId')
    ->orderBy('yearLevel')
    ->get();

$timetableByBlock = [];
foreach ($demoBlocks as $index => $slot) {
    $course = DB::table('courses')->where('courseId', $slot->courseId)->first();
    $blockName = ($course->courseCode ?? 'DEMO').' '.$slot->yearLevel.'-A';

    DB::table('blocks')->updateOrInsert(
        ['courseId' => $slot->courseId, 'termId' => $termId, 'yearLevel' => $slot->yearLevel, 'blockName' => $blockName],
        ['maxStudents' => 40, 'scheduleStatus' => 'draft']
    );
    $blockId = (int) DB::table('blocks')
        ->where('courseId', $slot->courseId)->where('termId', $termId)
        ->where('yearLevel', $slot->yearLevel)->where('blockName', $blockName)
        ->value('blockId');

    // One teacher and one room per block: the hours repeat across blocks, so
    // sharing either would trip the conflict guard the desk itself enforces.
    $instructorId = $instructorIds[$index % count($instructorIds)];
    $roomId = $roomIds[$index % count($roomIds)];

    $subjectIds = DB::table('enrolledsubjects as es')
        ->join('enrollments as e', 'e.enrollmentId', '=', 'es.enrollmentId')
        ->where('e.termId', $termId)
        ->where('e.courseId', $slot->courseId)
        ->where('e.yearLevel', $slot->yearLevel)
        ->where('e.enrollmentStatus', EnrollmentStatus::Enrolled->value)
        ->where('es.status', '!=', 'dropped')
        ->distinct()
        ->pluck('es.subjectId')
        ->all();

    $schedules = [];
    foreach ($subjectIds as $subjectId) {
        $existing = DB::table('schedules')
            ->where('blockId', $blockId)->where('subjectId', $subjectId)->first();

        if ($existing) {
            DB::table('schedules')->where('scheduleId', $existing->scheduleId)->update([
                'instructorId' => $instructorId,
                'roomId' => $roomId,
            ]);
            $scheduleId = (int) $existing->scheduleId;
        } else {
            $scheduleId = (int) DB::table('schedules')->insertGetId([
                'blockId' => $blockId,
                'subjectId' => $subjectId,
                'instructorId' => $instructorId,
                'roomId' => $roomId,
            ]);
        }

        // Meetings are replaced wholesale, exactly as updateSchedule() does it.
        // Filtering the stale ones by startTime cannot work here: the column is a
        // MySQL TIME and a bound '08:00:00' string never compares equal to it, so
        // a whereNotIn prune silently deletes the rows just written.
        $slots = $meetingSlots[$subjectId] ?? $defaultSlot;
        DB::table('schedulemeetings')->where('scheduleId', $scheduleId)->delete();
        foreach ($slots as [$day, $start, $end]) {
            DB::table('schedulemeetings')->insert([
                'scheduleId' => $scheduleId,
                'dayOfWeek' => $day,
                'startTime' => $start,
                'endTime' => $end,
            ]);
        }

        $schedules[$subjectId] = $scheduleId;
    }

    // A class the enrolled cohort no longer takes is removed rather than left:
    // the desk would offer a schedule with nobody in it, and its rows would point
    // at a timetable that prints on the block schedule.
    $strays = DB::table('schedules')->where('blockId', $blockId)->whereNotIn('subjectId', $subjectIds)->pluck('scheduleId')->all();
    if ($strays !== []) {
        DB::table('schedulemeetings')->whereIn('scheduleId', $strays)->delete();
        DB::table('schedules')->whereIn('scheduleId', $strays)->delete();
        DB::table('enrolledsubjects')->whereIn('scheduleId', $strays)->update(['scheduleId' => null]);
    }

    $timetableByBlock[$blockId] = $schedules;
}

$assignedCount = 0;
foreach ($timetableByBlock as $blockId => $schedules) {
    $block = DB::table('blocks')->where('blockId', $blockId)->first();
    $candidates = Enrollments::where('termId', $termId)
        ->where('courseId', $block->courseId)
        ->where('yearLevel', $block->yearLevel)
        ->where('enrollmentStatus', EnrollmentStatus::Enrolled)
        ->orderBy('enrollmentId')
        ->get();

    // The newest student in each block is left for the desk to assign live: the
    // walkthrough needs something to do, and assigning everything here would
    // empty the very queue the screen is judged on.
    foreach ($candidates as $position => $enrollment) {
        if ($position === $candidates->count() - 1) {
            $seatWorkflow($enrollment, OfficeId::Blocking->value);
            echo "✔ Blocking Desk: enrollment {$enrollment->enrollmentId} left awaiting assignment in block {$block->blockName}\n";

            continue;
        }

        DB::table('enrolledsubjects')
            ->where('enrollmentId', $enrollment->enrollmentId)
            ->where('status', '!=', 'dropped')
            ->update(['blockId' => $blockId]);

        foreach ($schedules as $subjectId => $scheduleId) {
            DB::table('enrolledsubjects')
                ->where('enrollmentId', $enrollment->enrollmentId)
                ->where('subjectId', $subjectId)
                ->where('status', '!=', 'dropped')
                ->update(['scheduleId' => $scheduleId]);
        }

        // Anything outside this block's timetable keeps a NULL scheduleId: the
        // old blanket stamp printed one subject's hour on every line of the card.
        DB::table('enrolledsubjects')
            ->where('enrollmentId', $enrollment->enrollmentId)
            ->where('status', '!=', 'dropped')
            ->whereNotIn('subjectId', array_keys($schedules))
            ->update(['scheduleId' => null]);

        // assignStudents signs the Blocking box as part of assigning, so the
        // seeded assignment signs it too — a student stamped into a block whose
        // own box is still open reads as both assigned and not yet worked.
        $enrollment->unsetRelation('enrollmentworkflow');
        if (! $enrollment->enrollmentworkflow) {
            $seatWorkflow($enrollment, OfficeId::Blocking->value);
            $enrollment->unsetRelation('enrollmentworkflow');
        }

        $blockingBox = $enrollment->enrollmentworkflow?->workflowsteps()
            ->firstWhere('officeId', OfficeId::Blocking->value);

        if ($blockingBox) {
            DB::table('workflowsteps')->where('workflowStepId', $blockingBox->workflowStepId)->update([
                'stepStatus' => WorkflowStepStatus::Completed->value,
                'signedBy' => $deskSigners[OfficeId::Blocking->value],
                'signedDate' => now(),
            ]);

            $nextBox = $enrollment->enrollmentworkflow->workflowsteps()
                ->where('stepStatus', WorkflowStepStatus::Pending->value)
                ->orderBy('stepOrder')
                ->first();

            DB::table('enrollmentworkflow')
                ->where('workflowId', $enrollment->enrollmentworkflow->workflowId)
                ->update(['currentStep' => $nextBox?->stepOrder ?? $blockingBox->stepOrder]);
        }

        $assignedCount++;
    }
}

echo '✔ Blocking & Scheduling: '.count($timetableByBlock).' block(s), '.DB::table('schedules')->count().' schedule row(s), '
    .DB::table('schedulemeetings')->count()." meeting(s), {$assignedCount} student(s) assigned\n";

echo "\n";

// -------------------------------------------------------------
// 10h. ID OFFICE — the last box of the seven
// -------------------------------------------------------------
// The ID queue lists an enrollment only while the ID Office box is its first
// still-pending step, so a student standing at any earlier box is invisible
// here. Kevin Santos is left at Blocking on purpose — the walkthrough needs a
// live assignment. Danica Sotto is walked the rest of the way in: her Clinic box
// is signed with the record that signs it, which leaves the ID box open and the
// desk with a request to validate. Her request row is written here rather than
// beside her enrollment because the enrollment is created once — after the table
// lost the row, the guarded insert never ran again and the desk showed nothing.
$idSeat = Enrollments::with('student')
    ->whereHas('student', fn ($q) => $q->where('schoolIdNumber', 'DEMO-2026-016'))
    ->where('termId', $termId)
    ->where('enrollmentStatus', EnrollmentStatus::Enrolled)
    ->first();

if ($idSeat) {
    if (! DB::table('idrequests')->where('enrollmentId', $idSeat->enrollmentId)->exists()) {
        DB::table('idrequests')->insert([
            'enrollmentId' => $idSeat->enrollmentId,
            'requestReason' => 'newStudent',
            'emergencyContactName' => 'Vic Sotto',
            'emergencyContactNumber' => '09171234599',
            'bloodType' => 'A+',
            'status' => 'pending',
            'requestDate' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $seatWorkflow($idSeat, OfficeId::IdOffice->value);

    DB::table('clinicrecords')->updateOrInsert(
        ['enrollmentId' => $idSeat->enrollmentId],
        [
            'heightCm' => 158.0,
            'weightKg' => 52.0,
            'bloodPressure' => '110/70',
            'philhealthNumber' => "PH-DEMO-{$idSeat->enrollmentId}",
            'philhealthRegistered' => 1,
            'assessmentNotes' => 'Cleared for enrollment — no restricting findings.',
            'findings' => 'Normal',
            'clinicStaffId' => $deskSigners[OfficeId::Clinic->value],
            'assessmentDate' => now()->toDateString(),
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    echo "✔ ID Desk: seated DEMO-2026-016 Danica Sotto (enrollment {$idSeat->enrollmentId})"
        ." — pending ID request with every box ahead of it signed\n";
} else {
    echo "  ! ID Desk: DEMO-2026-016 has no ENROLLED record for this term — the desk will list nothing\n";
}

// Kevin Santos is the desk's finished example — a request that made it all the
// way through. Two things were wrong with him: the request was written as
// validated with no face photo and no validator, which is a state
// IDPolicy::validate can never produce, and his enrollment had no workflow at
// all, so the phase indicator on his profile had nothing to draw while the
// record beside it claimed every desk had signed. Re-applied on every run for
// the same reason the rest of section 10 is: the guarded inserts above only
// fire once, and a live walkthrough afterwards can leave him half-decided.
$kevinSeat = Enrollments::with('student')
    ->whereHas('student', fn ($q) => $q->where('schoolIdNumber', 'DEMO-2026-017'))
    ->where('termId', $termId)
    ->first();

if ($kevinSeat) {
    $completeWorkflow($kevinSeat);

    $kevinPhoto = $demoFacePhoto('demo-2026-017');

    if (DB::table('idrequests')->where('enrollmentId', $kevinSeat->enrollmentId)->exists()) {
        DB::table('idrequests')->where('enrollmentId', $kevinSeat->enrollmentId)->update([
            'cardPhotoPath' => $kevinPhoto,
            'status' => 'validated',
            'validatedBy' => $deskSigners[OfficeId::IdOffice->value],
            'validatedDate' => now(),
            'updated_at' => now(),
        ]);
    } else {
        DB::table('idrequests')->insert([
            'enrollmentId' => $kevinSeat->enrollmentId,
            'requestReason' => 'newStudent',
            'emergencyContactName' => 'Rosa Santos',
            'emergencyContactNumber' => '09171234500',
            'bloodType' => 'B+',
            'cardPhotoPath' => $kevinPhoto,
            'status' => 'validated',
            'validatedBy' => $deskSigners[OfficeId::IdOffice->value],
            'validatedDate' => now(),
            'requestDate' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    echo "✔ ID Desk: DEMO-2026-017 Kevin Santos (enrollment {$kevinSeat->enrollmentId})"
        ." — all boxes signed, workflow completed, validated request with a face photo on file\n";
} else {
    echo "  ! ID Desk: DEMO-2026-017 has no record for this term — the completed example will be missing\n";
}

// -------------------------------------------------------------
// 10i. ASSESSMENT DESK — a fee sheet that has not moved on
// -------------------------------------------------------------
// The desk lists assessments whose enrollment is still EVALUATED, which is
// exactly what AssessmentController::compute() leaves behind: it writes the
// sheet and its charges and deliberately does not push the enrollment to
// ASSESSED, because that box is the officer's own to sign. Every sheet this
// tool had ever created sat on an enrollment already past that box, so the
// queue listed nothing.
$custodio = createDemoStudent('DEMO-2026-022', 'Camille', 'Custodio', 'demo_camille', 'demo.camille@example.com', 'female');
$enrCamille = Enrollments::where('studentId', $custodio->studentId)->where('termId', $termId)->first();
if (! $enrCamille) {
    $enrCamille = Enrollments::create([
        'studentId' => $custodio->studentId,
        'courseId' => 1,
        'termId' => $termId,
        'yearLevel' => 1,
        'studentType' => StudentType::FirstYear,
        'enrollmentType' => EnrollmentType::New,
        'academicStanding' => null,
        'evaluatedBy' => $deskSigners[OfficeId::Guidance->value],
        'formSignedDate' => now(),
        'enrollmentStatus' => EnrollmentStatus::Evaluated,
    ]);
}
$proposeSubjects($enrCamille, [1, 2, 5]);
$camilleSheet = $assessmentFor($enrCamille, 17500.00);
$chargeDemoFees($camilleSheet);
$enrCamille->unsetRelation('enrollmentworkflow');
$seatWorkflow($enrCamille, OfficeId::Scholarship->value);
DB::table('studentassessments')->where('assessmentId', $camilleSheet)->update(['remainingBalance' => 17500.00]);
echo "✔ Assessment Desk: DEMO-2026-022 Camille Custodio — ₱17,500.00 sheet on an EVALUATED load, nothing paid\n";

echo "\n";

// -------------------------------------------------------------
// 10j. LEDGER INTEGRITY — the current term must balance everywhere
// -------------------------------------------------------------
// A signed Accounting box with no receipt behind it, a student paid twice for one
// term, or an OR that migrated to another enrollment when its number was reused
// are all visible the moment a desk opens a ledger, and every print view
// reproduces them on paper. Sections 1-9 only write what is missing, so these
// drift as soon as a screen is used; reconcile the term on every run.
$ledgerWarnings = [];

// An Official Receipt is a controlled document owned by exactly one student. If a
// number ended up on someone else's enrollment, hand it back to its rightful owner.
foreach (['DEMO-OR-0003' => 'DEMO-2026-014'] as $orNumber => $ownerSchoolId) {
    $owner = Enrollments::where('termId', $termId)
        ->whereHas('student', fn ($q) => $q->where('schoolIdNumber', $ownerSchoolId))
        ->first();
    $receipt = DB::table('payments')->where('orNumber', $orNumber)->first();

    if ($owner && $receipt && (int) $receipt->enrollmentId !== (int) $owner->enrollmentId) {
        DB::table('payments')->where('paymentId', $receipt->paymentId)->update([
            'enrollmentId' => $owner->enrollmentId,
            'amount' => 17500.00,
            'paymentMode' => 'cash',
            'processedBy' => $deskSigners[OfficeId::Accounting->value],
            'paymentStatus' => 'paid',
            'updated_at' => now(),
        ]);
        echo "✔ Ledger: returned {$orNumber} to {$ownerSchoolId} (enrollment {$owner->enrollmentId})\n";
    }
}

// An enrollment past Accounting must carry the fee sheet Accounting signed.
foreach (Enrollments::with('student')->where('termId', $termId)
    ->whereIn('enrollmentStatus', ['paid', 'enrolled'])
    ->whereDoesntHave('studentassessments')
    ->get() as $unledgered) {
    $assessmentId = $assessmentFor($unledgered, 17500.00);
    $chargeDemoFees($assessmentId);
    $recordDemoPayment($unledgered, "DEMO-OR-TERM-{$unledgered->enrollmentId}", 17500.00, 'cash');
    echo "✔ Ledger: opened and settled a fee sheet for {$unledgered->student->schoolIdNumber}"
        ." (enrollment {$unledgered->enrollmentId})\n";
}

// An enrollment whose Accounting box is signed must show a settled ledger. A short
// receipt means the box was signed on paper only, so top it up with its own OR.
foreach (Enrollments::where('termId', $termId)
    ->whereIn('enrollmentStatus', ['paid', 'enrolled'])->get() as $settled) {
    $sheet = DB::table('studentassessments')->where('enrollmentId', $settled->enrollmentId)->first();

    if (! $sheet) {
        continue;
    }

    $due = (float) $sheet->totalAssessedAmount - (float) $sheet->totalScholarshipCoverage - (float) $sheet->totalWaived;
    $paid = (float) DB::table('payments')
        ->where('enrollmentId', $settled->enrollmentId)
        ->where('paymentStatus', 'paid')
        ->sum('amount');

    if ($paid + 0.009 < $due) {
        $recordDemoPayment($settled, "DEMO-OR-SETTLE-{$settled->enrollmentId}", $due - $paid, 'cash');
        echo '✔ Ledger: settled the shortfall on enrollment '.$settled->enrollmentId
            .' (₱'.number_format($due - $paid, 2)." outstanding, mode cash)\n";
    }
}

// A today-dated receipt keeps the Daily Collections Report alive without paying a
// student twice: the slip dated today takes a ₱5,500 tail of the assessment and the
// earlier slip is trimmed to the remainder, so the ledger still sums to what was due.
$todaySlip = DB::table('payments as p')
    ->join('enrollments as e', 'e.enrollmentId', '=', 'p.enrollmentId')
    ->where('p.orNumber', 'DEMO-OR-TODAY-01')
    ->where('e.termId', $termId)
    ->select('p.paymentId', 'p.enrollmentId')
    ->first();

if ($todaySlip) {
    $sheet = DB::table('studentassessments')->where('enrollmentId', $todaySlip->enrollmentId)->first();

    if ($sheet) {
        $due = (float) $sheet->totalAssessedAmount - (float) $sheet->totalScholarshipCoverage - (float) $sheet->totalWaived;
        $tail = min(5500.00, $due);
        $others = DB::table('payments')
            ->where('enrollmentId', $todaySlip->enrollmentId)
            ->where('paymentId', '!=', $todaySlip->paymentId)
            ->where('paymentStatus', 'paid')
            ->orderByDesc('amount')
            ->get();

        DB::table('payments')->where('paymentId', $todaySlip->paymentId)
            ->update(['amount' => $tail, 'updated_at' => now()]);

        if ($others->isNotEmpty()) {
            $largest = $others->first();
            $rest = (float) $others->skip(1)->sum('amount');
            DB::table('payments')->where('paymentId', $largest->paymentId)
                ->update(['amount' => max(0.0, $due - $tail - $rest), 'updated_at' => now()]);
        }

        echo "✔ Ledger: split the collection so DEMO-OR-TODAY-01 stays today's receipt without overpaying\n";
    }
}

// Recompute every balance in the term and flag receipts that exceed the assessment.
foreach (DB::table('studentassessments as a')
    ->join('enrollments as e', 'e.enrollmentId', '=', 'a.enrollmentId')
    ->where('e.termId', $termId)
    ->select('a.assessmentId', 'a.enrollmentId', 'a.remainingBalance', 'a.totalAssessedAmount',
        'a.totalScholarshipCoverage', 'a.totalWaived', 's.schoolIdNumber')
    ->join('students as s', 's.studentId', '=', 'e.studentId')
    ->get() as $sheet) {
    $paid = (float) DB::table('payments')
        ->where('enrollmentId', $sheet->enrollmentId)
        ->where('paymentStatus', 'paid')
        ->sum('amount');
    $due = max(0, (float) $sheet->totalAssessedAmount - (float) $sheet->totalScholarshipCoverage - (float) $sheet->totalWaived);
    $balance = max(0.0, $due - $paid);

    if (abs($balance - (float) $sheet->remainingBalance) > 0.009) {
        DB::table('studentassessments')->where('assessmentId', $sheet->assessmentId)
            ->update(['remainingBalance' => $balance, 'updated_at' => now()]);
    }

    if ($paid > $due + 0.009) {
        $ledgerWarnings[] = "{$sheet->schoolIdNumber} (enrollment {$sheet->enrollmentId}) is paid "
            .'₱'.number_format($paid, 2).' against a ₱'.number_format($due, 2).' assessment';
    }
}

echo "\n";

// -------------------------------------------------------------
// 11. DESK QUEUE REPORT — what each screen shows after this run
// -------------------------------------------------------------
// The demo is judged on whether a desk has something real to do, so the tool
// prints the queue each screen will list instead of leaving that to be checked
// by hand before the defense.
$queuedFor = [
    'Admission — applications' => DB::table('admissions')->where('admissionStatus', 'pending')->count(),
    'Evaluation — loads to decide' => DB::table('enrollments')->where('termId', $termId)->whereIn('enrollmentStatus', ['pending', 'returnedToEvaluation'])->count(),
    'Assessment — loads to cost' => DB::table('enrollments')->where('termId', $termId)->where('enrollmentStatus', 'evaluated')->count(),
    'Assessment — sheets on a load not yet decided' => DB::table('studentassessments as a')
        ->join('enrollments as e', 'e.enrollmentId', '=', 'a.enrollmentId')
        ->where('e.termId', $termId)
        ->where('e.enrollmentStatus', 'evaluated')
        ->count(),
    'Accounting — accounts due' => DB::table('studentassessments as a')
        ->join('enrollments as e', 'e.enrollmentId', '=', 'a.enrollmentId')
        ->where('e.termId', $termId)
        ->where('a.remainingBalance', '>', 0)->count(),
    'Accounting — no balance to settle' => DB::table('studentassessments as a')
        ->join('enrollments as e', 'e.enrollmentId', '=', 'a.enrollmentId')
        ->where('e.enrollmentStatus', 'assessed')
        ->where('a.remainingBalance', '<=', 0)->count(),
    'Registrar — paid, awaiting approval' => DB::table('enrollments')->where('termId', $termId)->where('enrollmentStatus', 'paid')->count(),
    'Blocking — students awaiting assignment' => DB::table('enrollments as e')
        ->join('enrollmentworkflow as w', 'w.enrollmentId', '=', 'e.enrollmentId')
        ->where('e.enrollmentStatus', 'enrolled')
        ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('enrolledsubjects as es')
            ->whereColumn('es.enrollmentId', 'e.enrollmentId')->whereNotNull('es.blockId')->where('es.status', '!=', 'dropped'))
        ->count(),
    'Blocking — students inside a block' => DB::table('enrollments as e')
        ->where('e.enrollmentStatus', 'enrolled')
        ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('enrolledsubjects as es')
            ->whereColumn('es.enrollmentId', 'e.enrollmentId')->whereNotNull('es.blockId')->where('es.status', '!=', 'dropped'))
        ->count(),
    'Scheduling — class meetings on the timetable' => DB::table('schedulemeetings')->count(),
    'Clinic — health assessments on file' => DB::table('clinicrecords')->count(),
    'ID Office — requests pending' => DB::table('idrequests')->where('status', 'pending')->count(),
    'ID Office — standing at the ID box' => DB::table('enrollments as e')
        ->where('e.enrollmentStatus', 'enrolled')
        ->whereExists(fn ($q) => $q->select(DB::raw(1))
            ->from('workflowsteps as ws')
            ->join('enrollmentworkflow as w', 'w.workflowId', '=', 'ws.workflowId')
            ->whereColumn('w.enrollmentId', 'e.enrollmentId')
            ->where('ws.stepStatus', 'pending')
            ->where('ws.officeId', OfficeId::IdOffice->value)
            ->whereRaw("ws.stepOrder = (SELECT MIN(ws2.stepOrder) FROM workflowsteps ws2 WHERE ws2.workflowId = ws.workflowId AND ws2.stepStatus = 'pending')"))
        ->count(),
    'Clearance — slips in progress' => DB::table('studentclearances')->where('overallStatus', 'pending')->count(),
    'Ledger — enrolled with no fee sheet' => DB::table('enrollments as e')
        ->where('e.termId', $termId)
        ->whereIn('e.enrollmentStatus', ['paid', 'enrolled'])
        ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('studentassessments as a')
            ->whereColumn('a.enrollmentId', 'e.enrollmentId'))
        ->count(),
    'Ledger — past Accounting but still owes' => DB::table('enrollments as e')
        ->join('studentassessments as a', 'a.enrollmentId', '=', 'e.enrollmentId')
        ->where('e.termId', $termId)
        ->whereIn('e.enrollmentStatus', ['paid', 'enrolled'])
        ->where('a.remainingBalance', '>', 0)
        ->count(),
    'Ledger — receipts exceeding the assessment' => count($ledgerWarnings),
];

echo "=======================================================\n";
echo " WHAT EACH DESK WILL SHOW\n";
echo "=======================================================\n";
foreach ($queuedFor as $desk => $count) {
    printf("  %-40s %d\n", $desk, $count);
}

foreach ($ledgerWarnings as $warning) {
    echo "  ! {$warning}\n";
}

echo "\n=======================================================\n";
echo " DEMO-DAY DATASET FULLY SEEDED!                      \n";
echo " Every single desk and workflow queue is populated.    \n";
echo "=======================================================\n";
