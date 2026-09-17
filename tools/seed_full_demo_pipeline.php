<?php
// Comprehensive Demo-Day Pipeline Seeder: populates active, realistic students
// across every single office desk and workflow queue for live demonstration.
//
// Usage: php tools/seed_full_demo_pipeline.php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\AcademicStanding;
use App\Enums\AdmissionStatus;
use App\Enums\ApplicantType;
use App\Enums\ApplicationMode;
use App\Enums\ClearanceOverallStatus;
use App\Enums\EnrolledSubjectStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\FeeUnitBasis;
use App\Enums\OfficeId;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
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
use App\Services\WorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

echo "=======================================================\n";
echo " SEAIT EMS — COMPREHENSIVE WORKFLOW DEMO-DAY SEEDER   \n";
echo "=======================================================\n\n";

$termId = 18; // 2025-2026 Summer
$defaultPassword = Hash::make('password123');
$adminUser = Staffusers::where('username', 'staff8')->first() ?? Staffusers::first();
$workflowService = app(WorkflowService::class);

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
function createDemoStudent($schoolId, $first, $last, $user, $email, $gender = 'male', $passwordHash = null) {
    global $defaultPassword;
    $student = Students::where('schoolIdNumber', $schoolId)->first();
    if ($student) return $student;

    return Students::create([
        'schoolIdNumber' => $schoolId,
        'firstName' => $first,
        'lastName' => $last,
        'middleName' => 'M',
        'suffix' => 'N/A',
        'gender' => $gender,
        'birthdate' => '2004-05-12',
        'birthplace' => 'Davao City',
        'citizenship' => 'Filipino',
        'civilStatus' => 'single',
        'religionId' => 1,
        'contactNumber' => '0917' . rand(1000000, 9999999),
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
            if ($isApp) $approvedCount++;
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
        'academicStanding' => AcademicStanding::Regular,
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
        'academicStanding' => AcademicStanding::Regular,
        'evaluatedBy' => 5,
        'enrollmentStatus' => EnrollmentStatus::Pending,
    ]);
    echo "✔ Evaluation Desk: Seeded Rafael Cruz (DEMO-2026-009) - PENDING transferee queue\n";
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
        'academicStanding' => AcademicStanding::Regular,
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
        'academicStanding' => AcademicStanding::Regular,
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
        'academicStanding' => AcademicStanding::Regular,
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
        'academicStanding' => AcademicStanding::Regular,
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
    echo "✔ ID Desk: Seeded Danica Sotto (DEMO-2026-016) - ID REQUESTED (pending card production)\n";
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
    $idReqId = DB::table('idrequests')->insertGetId([
        'enrollmentId' => $enrKev->enrollmentId,
        'requestReason' => 'newStudent',
        'emergencyContactName' => 'Rosa Santos',
        'emergencyContactNumber' => '09171234500',
        'bloodType' => 'B+',
        'status' => 'cardProduced',
        'requestDate' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('studentids')->insert([
        'studentId' => $kevin->studentId,
        'idRequestId' => $idReqId,
        'qrCode' => 'SEAIT-DEMO-' . $kevin->studentId,
        'issueDate' => now(),
        'validationStatus' => 'pendingValidation',
    ]);
    echo "✔ ID Desk: Seeded Kevin Santos (DEMO-2026-017) - ID PRODUCED (ready for validation & release)\n";
}

echo "\n=======================================================\n";
echo " DEMO-DAY DATASET FULLY SEEDED!                      \n";
echo " Every single desk and workflow queue is populated.    \n";
echo "=======================================================\n";
