<?php

namespace Tests\Feature\E2E;

use App\Enums\EnrollmentStatus;
use App\Enums\WorkflowStatus;
use App\Models\Admissions;
use App\Models\Clearanceperiods;
use App\Models\Enrollments;
use App\Models\Staffusers;
use App\Models\Studentclearances;
use App\Models\Studentrequirementsubmissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * End-to-end enrollment walkthroughs against the real MySQL `ems` dataset.
 *
 * Each test runs inside a transaction (rolled back after the test) so the
 * 30k-student synthetic dataset is never polluted. Reference data (courses,
 * subjects, fee types, clearance requirements, offices, roles, permissions)
 * is read from the live database, giving realistic coverage of the full
 * enrollment pipeline:
 *
 *   admission → exam → evaluation → assessment → accounting → registrar
 *   → blocking → clinic → ID
 *
 * Four student paths are covered (Build_Plan Stage 4):
 *   1. First-Year  (2-stage entrance exam, assessment included)
 *   2. Continuing  (retention exam, clearance, no assessment)
 *   3. Transferee  (credit transfer, assessment included)
 *   4. Shifter     (credit transfer, no assessment)
 */
class EnrollmentWalkthroughTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Run against the real MySQL database (same pattern as AdminAccessSmokeTest)
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => 'ems',
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.port' => '3306',
            'database.connections.mysql.username' => 'root',
            'database.connections.mysql.password' => '',
        ]);
        DB::purge('mysql');
    }

    /**
     * Create a staff user in the given office with the OfficeHead role
     * (OfficeHead carries every module action permission).
     */
    private function staffForOffice(int $officeId): Staffusers
    {
        // Use make() so we can drop remember_token (real staffusers table has no such column)
        $staff = Staffusers::factory()->make([
            'officeId' => $officeId,
            'role' => 'officeHead',
            'username' => 'e2e_office'.$officeId.'_'.uniqid(),
            'email' => 'e2e_office'.$officeId.'_'.uniqid().'@example.com',
        ]);
        unset($staff->remember_token);
        $staff->save();

        $staff->assignRole('OfficeHead');

        return $staff;
    }

    /**
     * Create a student + admission via the real admission.store endpoint.
     */
    private function createAdmission(array $overrides = []): Admissions
    {
        $payload = array_merge([
            'schoolIdNumber' => 'E2E-'.uniqid(),
            'lastName' => 'Walkthrough',
            'firstName' => 'Student',
            'middleName' => 'E',
            'suffix' => 'N/A', // real students.suffix column is NOT NULL
            'gender' => 'male',
            'birthdate' => '2004-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'religionId' => 1,
            'civilStatus' => 'single',
            'contactNumber' => '09171234567',
            'telephoneNumber' => null,
            'email' => 'e2e_'.uniqid().'@example.com',
            'username' => 'e2e_student_'.uniqid(),
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'courseId' => 3, // BSCrim (requires entrance exam)
            'termId' => 18,  // current term (Summer)
            'applicantType' => 'firstYear',
            'addresses' => [
                [
                    'addressType' => 'home',
                    'houseBuildingNo' => '123',
                    'street' => 'Test St',
                    'sitioPurok' => 'Purok 1',
                    'barangay' => 'Test Barangay',
                    'cityMunicipality' => 'Test City',
                    'district' => 'District 1',
                    'province' => 'Test Province',
                    'region' => 'Region XI',
                    'zipCode' => '8000',
                    'country' => 'Philippines',
                ],
                [
                    'addressType' => 'current',
                    'houseBuildingNo' => '456',
                    'street' => 'Current St',
                    'sitioPurok' => 'Purok 2',
                    'barangay' => 'Current Barangay',
                    'cityMunicipality' => 'Current City',
                    'district' => 'District 2',
                    'province' => 'Current Province',
                    'region' => 'Region XI',
                    'zipCode' => '8001',
                    'country' => 'Philippines',
                ],
            ],
            'guardians' => [
                [
                    'relationship' => 'mother',
                    'fullName' => 'Mother Guardian',
                    'contactNumber' => '09171234568',
                    'email' => 'mother_'.uniqid().'@example.com',
                    'isEmergencyContact' => true,
                    'isAuthorizedToActOnBehalf' => true,
                ],
            ],
            'educationalBackgrounds' => [
                [
                    'institutionName' => 'Test High School',
                    'institutionType' => 'seniorHigh',
                    'cityMunicipality' => 'Test City',
                    'province' => 'Test Province',
                    'levelCompleted' => 'seniorHigh',
                    'strandTrack' => 'STEM',
                    'yearCompleted' => '2024-03-31',
                    'honorsCertifications' => null,
                ],
            ],
        ], $overrides);

        $admissionStaff = $this->staffForOffice(6); // Admission Office
        $this->actingAs($admissionStaff)->post(route('admission.store'), $payload)
            ->assertRedirect(route('admission.index'));

        return Admissions::where('studentId', $this->lastStudentId())
            ->latest('admissionId')
            ->firstOrFail();
    }

    private function lastStudentId(): int
    {
        return DB::table('students')->latest('studentId')->value('studentId');
    }

    /**
     * Verify all required admission requirements for an admission.
     */
    private function verifyAllRequirements(Admissions $admission): void
    {
        Studentrequirementsubmissions::where('admissionId', $admission->admissionId)
            ->update(['submissionStatus' => 'verified']);
    }

    /**
     * Create an enrollment directly (the system creates enrollments from
     * approved admissions; there is no enrollment-creation endpoint).
     */
    private function createEnrollment(Admissions $admission, string $studentType, int $yearLevel = 1): Enrollments
    {
        return Enrollments::create([
            'studentId' => $admission->studentId,
            'courseId' => $admission->courseId,
            'termId' => $admission->termId,
            'admissionId' => $admission->admissionId,
            'yearLevel' => $yearLevel,
            'studentType' => $studentType,
            'enrollmentType' => in_array($studentType, ['firstYear', 'transferee']) ? 'new' : 'old',
            'academicStanding' => 'regular',
            'enrollmentStatus' => EnrollmentStatus::Pending,
        ]);
    }

    /**
     * Walk the enrollment through the full pipeline after evaluation.
     * Returns the final enrollment.
     *
     * Note: ALL student types get an assessment (RegistrarPolicy requires
     * assessment_completed for everyone) — the workflow only skips the
     * office-3 (Assessment) *step* for continuing/shifter students. The
     * finalize controller signs office 3 null-safely, so this works for both.
     */
    private function walkPipeline(Enrollments $enrollment, array $staff): Enrollments
    {
        // --- Assessment (all types; office 2/3) ---
        $this->actingAs($staff['assessment'])
            ->post(route('assessment.compute', $enrollment))
            ->assertSessionHasNoErrors();

        $assessment = $enrollment->fresh()->studentassessments;
        $this->assertNotNull($assessment, 'Assessment should be computed');

        $this->actingAs($staff['assessment'])
            ->post(route('assessment.finalize', $assessment))
            ->assertSessionHasNoErrors();

        $this->assertEquals(
            EnrollmentStatus::Assessed,
            $enrollment->fresh()->enrollmentStatus,
            'Enrollment should transition to assessed after finalize'
        );

        // --- Accounting (payment) ---
        $assessment = $enrollment->fresh()->studentassessments;
        $this->assertNotNull($assessment, 'Assessment must exist before payment');

        $this->actingAs($staff['accounting'])
            ->post(route('accounting.payment.record', $assessment), [
                'orNumber' => 'E2E-OR-'.uniqid(),
                'amount' => $assessment->remainingBalance,
                'paymentMode' => 'cash',
                'paymentDate' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertEquals(
            EnrollmentStatus::Paid,
            $enrollment->fresh()->enrollmentStatus,
            'Enrollment should transition to paid after full payment'
        );

        // --- Registrar approval ---
        $this->actingAs($staff['registrar'])
            ->post(route('registrar.approve', $enrollment))
            ->assertSessionHasNoErrors();

        $this->assertEquals(
            EnrollmentStatus::Enrolled,
            $enrollment->fresh()->enrollmentStatus,
            'Enrollment should transition to enrolled after registrar approval'
        );

        // --- Blocking (assign to block + schedule) ---
        $block = DB::table('blocks')
            ->where('courseId', $enrollment->courseId)
            ->where('termId', $enrollment->termId)
            ->first();

        $this->assertNotNull($block, 'A block must exist for the course/term');

        $schedule = DB::table('schedules')->where('blockId', $block->blockId)->first();
        $this->assertNotNull($schedule, 'A schedule must exist for the block');

        $this->actingAs($staff['blocking'])
            ->post(route('blocking.assign', $block->blockId), [
                'enrollmentIds' => [$enrollment->enrollmentId],
                'scheduleId' => $schedule->scheduleId,
            ])
            ->assertSessionHasNoErrors();

        // --- Clinic (Phase 7) ---
        $this->actingAs($staff['clinic'])
            ->post(route('clinic.record', $enrollment), [
                'heightCm' => 165,
                'weightKg' => 60,
                'bloodPressure' => '120/80',
                'philhealthNumber' => 'PH-'.uniqid(),
                'philhealthRegistered' => true,
                'assessmentNotes' => 'E2E clinic assessment',
                'findings' => 'Fit',
                'assessmentDate' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        // --- ID (Phase 8) ---
        $this->actingAs($staff['id'])
            ->post(route('id.create', $enrollment), [
                'requestReason' => 'newStudent',
                'emergencyContactName' => 'Emergency Contact',
                'emergencyContactNumber' => '09171234569',
                'bloodType' => 'O+',
                'cardPhotoPath' => null,
                'producedByVendor' => null,
            ])
            ->assertSessionHasNoErrors();

        $idRequest = $enrollment->fresh()->idrequests->first();
        $this->assertNotNull($idRequest, 'ID request should be created');

        $this->actingAs($staff['id'])
            ->post(route('id.produce', $idRequest), [
                'qrCode' => 'E2E-QR-'.uniqid(),
                'securityPhotoPath' => null,
            ])
            ->assertSessionHasNoErrors();

        $studentId = $idRequest->fresh()->studentids->first();
        $this->assertNotNull($studentId, 'Student ID card should be produced');

        $this->actingAs($staff['id'])
            ->post(route('id.validate', $studentId))
            ->assertSessionHasNoErrors();

        return $enrollment->fresh();
    }

    #[Test]
    public function first_year_with_two_stage_entrance_exam_completes_full_pipeline(): void
    {
        // --- Admission (BSCrim requires 2-stage entrance exam) ---
        $admission = $this->createAdmission(['applicantType' => 'firstYear', 'courseId' => 3]);
        $this->verifyAllRequirements($admission);

        // Stage 1: General entrance exam (Guidance, office 7)
        $this->actingAs($this->staffForOffice(7))
            ->post(route('exam.general.record'), [
                'studentId' => $admission->studentId,
                'courseId' => $admission->courseId,
                'termId' => $admission->termId,
                'examResult' => 'pass',
                'examDate' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        // Stage 2: Course-specific entrance exam (Department, office 4) → auto-approves admission
        $this->actingAs($this->staffForOffice(4))
            ->post(route('exam.course-specific.record'), [
                'studentId' => $admission->studentId,
                'courseId' => $admission->courseId,
                'termId' => $admission->termId,
                'examResult' => 'pass',
                'examDate' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $admission->refresh();
        $this->assertEquals('approved', $admission->admissionStatus->value, 'Admission auto-approved after passing course-specific exam');

        // --- Enrollment (system-created) ---
        $enrollment = $this->createEnrollment($admission, 'firstYear', 1);

        // --- Evaluation (office 4) ---
        $evaluator = $this->staffForOffice(4);

        $this->actingAs($evaluator)
            ->put(route('evaluation.profile.capture', $enrollment), [
                'lastName' => 'Walkthrough',
                'firstName' => 'Student',
                'middleName' => 'E',
                'suffix' => 'N/A',
                'gender' => 'male',
                'birthdate' => '2004-01-01',
                'birthplace' => 'Test City',
                'citizenship' => 'Filipino',
                'religionId' => 1,
                'civilStatus' => 'single',
                'contactNumber' => '09171234567',
                'telephoneNumber' => null,
                'email' => 'e2e_'.uniqid().'@example.com',
                'addresses' => [
                    ['addressType' => 'home', 'houseBuildingNo' => '123', 'street' => 'Test St', 'sitioPurok' => 'Purok 1', 'barangay' => 'Test Barangay', 'cityMunicipality' => 'Test City', 'district' => 'District 1', 'province' => 'Test Province', 'region' => 'Region XI', 'zipCode' => '8000', 'country' => 'Philippines'],
                    ['addressType' => 'current', 'houseBuildingNo' => '456', 'street' => 'Current St', 'sitioPurok' => 'Purok 2', 'barangay' => 'Current Barangay', 'cityMunicipality' => 'Current City', 'district' => 'District 2', 'province' => 'Current Province', 'region' => 'Region XI', 'zipCode' => '8001', 'country' => 'Philippines'],
                ],
                'guardians' => [
                    ['relationship' => 'mother', 'fullName' => 'Mother Guardian', 'contactNumber' => '09171234568', 'email' => 'guardian_mother@example.com', 'isEmergencyContact' => true, 'isAuthorizedToActOnBehalf' => true],
                ],
                'semestersCompleted' => 0,
                'yearsInInstitution' => 0,
                'academicStanding' => 'regular',
                'formIssuedDate' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        // Propose subjects (transitions pending → evaluated)
        $subjectIds = DB::table('subjects')->limit(3)->pluck('subjectId')->all();
        $this->actingAs($evaluator)
            ->post(route('evaluation.subjects.propose', $enrollment), [
                'subjects' => collect($subjectIds)->map(fn ($id) => ['subjectId' => $id])->all(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertEquals(
            EnrollmentStatus::Evaluated,
            $enrollment->fresh()->enrollmentStatus,
            'Enrollment should be evaluated after subject proposal'
        );

        // Sign evaluation (creates workflow, signs office-4 step)
        $this->actingAs($evaluator)
            ->post(route('evaluation.sign', $enrollment))
            ->assertSessionHasNoErrors();

        $workflow = $enrollment->fresh()->enrollmentworkflow;
        $this->assertNotNull($workflow, 'Workflow should be created on evaluation sign');
        $this->assertEquals(7, $workflow->workflowsteps()->count(), 'First-year workflow should have 7 steps');

        // --- Walk the rest of the pipeline ---
        $final = $this->walkPipeline($enrollment, [
            'assessment' => $this->staffForOffice(2),
            'accounting' => $this->staffForOffice(2),
            'registrar' => $this->staffForOffice(1),
            'blocking' => $this->staffForOffice(5),
            'clinic' => $this->staffForOffice(11),
            'id' => $this->staffForOffice(22),
        ]);

        // --- Final assertions ---
        $this->assertEquals(EnrollmentStatus::Enrolled, $final->enrollmentStatus);
        $this->assertEquals(WorkflowStatus::Completed, $final->enrollmentworkflow->workflowStatus);
        $this->assertEquals(7, $final->enrollmentworkflow->workflowsteps()->where('stepStatus', 'completed')->count());
        $this->assertNotNull($final->clinicrecords->first(), 'Clinic record should exist');
        $this->assertNotNull($final->idrequests->first()->studentids->first(), 'Student ID should be validated');
    }

    #[Test]
    public function continuing_student_with_retention_exam_and_clearance_completes_pipeline(): void
    {
        // --- Admission (BSAIS requires retention exam) ---
        $admission = $this->createAdmission(['applicantType' => 'continuing', 'courseId' => 5]);
        $this->verifyAllRequirements($admission);

        // Retention exam (Guidance, office 7)
        $this->actingAs($this->staffForOffice(7))
            ->post(route('exam.retention.record'), [
                'studentId' => $admission->studentId,
                'courseId' => $admission->courseId,
                'termId' => $admission->termId,
                'examResult' => 'pass',
                'examDate' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        // Approve admission (no entrance exam for continuing)
        $this->actingAs($this->staffForOffice(6))
            ->post(route('admission.approve', $admission))
            ->assertSessionHasNoErrors();

        $admission->refresh();
        $this->assertEquals('approved', $admission->admissionStatus->value);

        // --- Clearance (mandatory for continuing) ---
        $period = Clearanceperiods::where('periodStatus', 'open')->first();
        $this->assertNotNull($period, 'An open clearance period must exist');

        $this->actingAs($this->staffForOffice(1))
            ->post(route('clearance.slip.generate'), [
                'studentId' => $admission->studentId,
                'clearancePeriodId' => $period->clearancePeriodId,
            ])
            ->assertSessionHasNoErrors();

        $clearance = Studentclearances::where('studentId', $admission->studentId)
            ->where('clearancePeriodId', $period->clearancePeriodId)
            ->first();
        $this->assertNotNull($clearance, 'Clearance slip should be generated');

        // Approve all clearance requirements
        foreach ($clearance->approvals as $approval) {
            $this->actingAs($this->staffForOffice($approval->requirement->officeId))
                ->post(route('clearance.approve', $approval), ['status' => 'approved'])
                ->assertSessionHasNoErrors();
        }

        // Record desk receipt (Registrar desk)
        $this->actingAs($this->staffForOffice(1))
            ->post(route('clearance.receipt.record', $clearance))
            ->assertSessionHasNoErrors();

        $clearance->refresh();
        $this->assertEquals('approved', $clearance->overallStatus->value);
        $this->assertNotNull($clearance->receivedBy, 'Desk receipt should be recorded');

        // --- Enrollment (system-created) ---
        $enrollment = $this->createEnrollment($admission, 'continuing', 2);

        // --- Evaluation (office 4) ---
        $evaluator = $this->staffForOffice(4);

        $this->actingAs($evaluator)
            ->put(route('evaluation.profile.capture', $enrollment), [
                'lastName' => 'Walkthrough',
                'firstName' => 'Continuing',
                'middleName' => 'C',
                'suffix' => 'N/A',
                'gender' => 'female',
                'birthdate' => '2002-01-01',
                'birthplace' => 'Test City',
                'citizenship' => 'Filipino',
                'religionId' => 1,
                'civilStatus' => 'single',
                'contactNumber' => '09171234567',
                'telephoneNumber' => null,
                'email' => 'e2e_'.uniqid().'@example.com',
                'addresses' => [
                    ['addressType' => 'home', 'houseBuildingNo' => '123', 'street' => 'Test St', 'sitioPurok' => 'Purok 1', 'barangay' => 'Test Barangay', 'cityMunicipality' => 'Test City', 'district' => 'District 1', 'province' => 'Test Province', 'region' => 'Region XI', 'zipCode' => '8000', 'country' => 'Philippines'],
                    ['addressType' => 'current', 'houseBuildingNo' => '456', 'street' => 'Current St', 'sitioPurok' => 'Purok 2', 'barangay' => 'Current Barangay', 'cityMunicipality' => 'Current City', 'district' => 'District 2', 'province' => 'Current Province', 'region' => 'Region XI', 'zipCode' => '8001', 'country' => 'Philippines'],
                ],
                'guardians' => [
                    ['relationship' => 'father', 'fullName' => 'Father Guardian', 'contactNumber' => '09171234568', 'email' => 'guardian_father@example.com', 'isEmergencyContact' => true, 'isAuthorizedToActOnBehalf' => true],
                ],
                'semestersCompleted' => 2,
                'yearsInInstitution' => 1,
                'academicStanding' => 'regular',
                'formIssuedDate' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $subjectIds = DB::table('subjects')->limit(3)->pluck('subjectId')->all();
        $this->actingAs($evaluator)
            ->post(route('evaluation.subjects.propose', $enrollment), [
                'subjects' => collect($subjectIds)->map(fn ($id) => ['subjectId' => $id])->all(),
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($evaluator)
            ->post(route('evaluation.sign', $enrollment))
            ->assertSessionHasNoErrors();

        $workflow = $enrollment->fresh()->enrollmentworkflow;
        $this->assertNotNull($workflow);
        $this->assertEquals(6, $workflow->workflowsteps()->count(), 'Continuing workflow should have 6 steps (no assessment)');

        // --- Walk the rest of the pipeline (no assessment step) ---
        $final = $this->walkPipeline($enrollment, [
            'assessment' => $this->staffForOffice(2),
            'accounting' => $this->staffForOffice(2),
            'registrar' => $this->staffForOffice(1),
            'blocking' => $this->staffForOffice(5),
            'clinic' => $this->staffForOffice(11),
            'id' => $this->staffForOffice(22),
        ]);

        $this->assertEquals(EnrollmentStatus::Enrolled, $final->enrollmentStatus);
        $this->assertEquals(WorkflowStatus::Completed, $final->enrollmentworkflow->workflowStatus);
        $this->assertEquals(6, $final->enrollmentworkflow->workflowsteps()->where('stepStatus', 'completed')->count());
    }

    #[Test]
    public function transferee_with_credit_transfer_completes_pipeline(): void
    {
        // --- Admission (non-exam course) ---
        $admission = $this->createAdmission(['applicantType' => 'transferee', 'courseId' => 1]);
        $this->verifyAllRequirements($admission);

        $this->actingAs($this->staffForOffice(6))
            ->post(route('admission.approve', $admission))
            ->assertSessionHasNoErrors();

        $admission->refresh();
        $this->assertEquals('approved', $admission->admissionStatus->value);

        // --- Enrollment (system-created) ---
        $enrollment = $this->createEnrollment($admission, 'transferee', 2);

        // --- Evaluation (office 4) with credit transfer ---
        $evaluator = $this->staffForOffice(4);

        $this->actingAs($evaluator)
            ->put(route('evaluation.profile.capture', $enrollment), [
                'lastName' => 'Walkthrough',
                'firstName' => 'Transferee',
                'middleName' => 'T',
                'suffix' => 'N/A',
                'gender' => 'female',
                'birthdate' => '2003-01-01',
                'birthplace' => 'Test City',
                'citizenship' => 'Filipino',
                'religionId' => 1,
                'civilStatus' => 'single',
                'contactNumber' => '09171234567',
                'telephoneNumber' => null,
                'email' => 'e2e_'.uniqid().'@example.com',
                'addresses' => [
                    ['addressType' => 'home', 'houseBuildingNo' => '123', 'street' => 'Test St', 'sitioPurok' => 'Purok 1', 'barangay' => 'Test Barangay', 'cityMunicipality' => 'Test City', 'district' => 'District 1', 'province' => 'Test Province', 'region' => 'Region XI', 'zipCode' => '8000', 'country' => 'Philippines'],
                    ['addressType' => 'current', 'houseBuildingNo' => '456', 'street' => 'Current St', 'sitioPurok' => 'Purok 2', 'barangay' => 'Current Barangay', 'cityMunicipality' => 'Current City', 'district' => 'District 2', 'province' => 'Current Province', 'region' => 'Region XI', 'zipCode' => '8001', 'country' => 'Philippines'],
                ],
                'guardians' => [
                    ['relationship' => 'guardian', 'fullName' => 'Guardian Name', 'contactNumber' => '09171234568', 'email' => 'guardian_other@example.com', 'isEmergencyContact' => true, 'isAuthorizedToActOnBehalf' => true],
                ],
                'semestersCompleted' => 1,
                'yearsInInstitution' => 0,
                'academicStanding' => 'regular',
                'formIssuedDate' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        // Process credit transfer
        $creditedSubjectId = DB::table('subjects')->value('subjectId');
        $this->actingAs($evaluator)
            ->post(route('evaluation.credits.process', $enrollment), [
                'credits' => [
                    [
                        'previousSubjectName' => 'Old School Subject',
                        'creditedToSubjectId' => $creditedSubjectId,
                        'creditedUnits' => 3,
                        'institutionName' => 'Old University',
                        'institutionType' => 'college',
                        'grade' => 85,
                        'remarks' => 'E2E credit transfer',
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertGreaterThan(0, $enrollment->fresh()->creditedsubjects()->count(), 'Credited subjects should exist');

        // Propose subjects + sign
        $subjectIds = DB::table('subjects')->limit(3)->pluck('subjectId')->all();
        $this->actingAs($evaluator)
            ->post(route('evaluation.subjects.propose', $enrollment), [
                'subjects' => collect($subjectIds)->map(fn ($id) => ['subjectId' => $id])->all(),
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($evaluator)
            ->post(route('evaluation.sign', $enrollment))
            ->assertSessionHasNoErrors();

        $workflow = $enrollment->fresh()->enrollmentworkflow;
        $this->assertNotNull($workflow);
        $this->assertEquals(7, $workflow->workflowsteps()->count(), 'Transferee workflow should have 7 steps (assessment included)');

        // --- Walk the rest of the pipeline ---
        $final = $this->walkPipeline($enrollment, [
            'assessment' => $this->staffForOffice(2),
            'accounting' => $this->staffForOffice(2),
            'registrar' => $this->staffForOffice(1),
            'blocking' => $this->staffForOffice(5),
            'clinic' => $this->staffForOffice(11),
            'id' => $this->staffForOffice(22),
        ]);

        $this->assertEquals(EnrollmentStatus::Enrolled, $final->enrollmentStatus);
        $this->assertEquals(WorkflowStatus::Completed, $final->enrollmentworkflow->workflowStatus);
        $this->assertEquals(7, $final->enrollmentworkflow->workflowsteps()->where('stepStatus', 'completed')->count());
    }

    #[Test]
    public function shifter_with_credit_transfer_completes_pipeline(): void
    {
        // --- Admission (non-exam course) ---
        $admission = $this->createAdmission(['applicantType' => 'shifter', 'courseId' => 1]);
        $this->verifyAllRequirements($admission);

        $this->actingAs($this->staffForOffice(6))
            ->post(route('admission.approve', $admission))
            ->assertSessionHasNoErrors();

        $admission->refresh();
        $this->assertEquals('approved', $admission->admissionStatus->value);

        // --- Enrollment (system-created) ---
        $enrollment = $this->createEnrollment($admission, 'shifter', 2);

        // --- Evaluation (office 4) with credit transfer ---
        $evaluator = $this->staffForOffice(4);

        $this->actingAs($evaluator)
            ->put(route('evaluation.profile.capture', $enrollment), [
                'lastName' => 'Walkthrough',
                'firstName' => 'Shifter',
                'middleName' => 'S',
                'suffix' => 'N/A',
                'gender' => 'male',
                'birthdate' => '2003-01-01',
                'birthplace' => 'Test City',
                'citizenship' => 'Filipino',
                'religionId' => 1,
                'civilStatus' => 'single',
                'contactNumber' => '09171234567',
                'telephoneNumber' => null,
                'email' => 'e2e_'.uniqid().'@example.com',
                'addresses' => [
                    ['addressType' => 'home', 'houseBuildingNo' => '123', 'street' => 'Test St', 'sitioPurok' => 'Purok 1', 'barangay' => 'Test Barangay', 'cityMunicipality' => 'Test City', 'district' => 'District 1', 'province' => 'Test Province', 'region' => 'Region XI', 'zipCode' => '8000', 'country' => 'Philippines'],
                    ['addressType' => 'current', 'houseBuildingNo' => '456', 'street' => 'Current St', 'sitioPurok' => 'Purok 2', 'barangay' => 'Current Barangay', 'cityMunicipality' => 'Current City', 'district' => 'District 2', 'province' => 'Current Province', 'region' => 'Region XI', 'zipCode' => '8001', 'country' => 'Philippines'],
                ],
                'guardians' => [
                    ['relationship' => 'mother', 'fullName' => 'Mother Guardian', 'contactNumber' => '09171234568', 'email' => 'guardian_mother@example.com', 'isEmergencyContact' => true, 'isAuthorizedToActOnBehalf' => true],
                ],
                'semestersCompleted' => 1,
                'yearsInInstitution' => 1,
                'academicStanding' => 'regular',
                'formIssuedDate' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        // Process credit transfer
        $creditedSubjectId = DB::table('subjects')->value('subjectId');
        $this->actingAs($evaluator)
            ->post(route('evaluation.credits.process', $enrollment), [
                'credits' => [
                    [
                        'previousSubjectName' => 'Previous Program Subject',
                        'creditedToSubjectId' => $creditedSubjectId,
                        'creditedUnits' => 3,
                        'institutionName' => 'SEAIT',
                        'institutionType' => 'college',
                        'grade' => 88,
                        'remarks' => 'E2E shifter credit transfer',
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        // Propose subjects + sign
        $subjectIds = DB::table('subjects')->limit(3)->pluck('subjectId')->all();
        $this->actingAs($evaluator)
            ->post(route('evaluation.subjects.propose', $enrollment), [
                'subjects' => collect($subjectIds)->map(fn ($id) => ['subjectId' => $id])->all(),
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($evaluator)
            ->post(route('evaluation.sign', $enrollment))
            ->assertSessionHasNoErrors();

        $workflow = $enrollment->fresh()->enrollmentworkflow;
        $this->assertNotNull($workflow);
        $this->assertEquals(6, $workflow->workflowsteps()->count(), 'Shifter workflow should have 6 steps (no assessment)');

        // --- Walk the rest of the pipeline (no assessment step) ---
        $final = $this->walkPipeline($enrollment, [
            'assessment' => $this->staffForOffice(2),
            'accounting' => $this->staffForOffice(2),
            'registrar' => $this->staffForOffice(1),
            'blocking' => $this->staffForOffice(5),
            'clinic' => $this->staffForOffice(11),
            'id' => $this->staffForOffice(22),
        ]);

        $this->assertEquals(EnrollmentStatus::Enrolled, $final->enrollmentStatus);
        $this->assertEquals(WorkflowStatus::Completed, $final->enrollmentworkflow->workflowStatus);
        $this->assertEquals(6, $final->enrollmentworkflow->workflowsteps()->where('stepStatus', 'completed')->count());
    }
}